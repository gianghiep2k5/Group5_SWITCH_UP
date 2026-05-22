# Student Ambassador Club Management System

## Project Overview

A web-based management system for a university Student Ambassador Club. The system handles club membership, events, ambassador task assignments, check-ins, and contribution point tracking.

Built with plain PHP (manual MVC + layered patterns), MySQL/MariaDB, PDO, HTML, CSS, and JavaScript. No framework.

**Course:** INS3064 — Worksheet 12

---

## Architecture Overview

The system combines five design patterns in a deliberate stack. Every HTTP request flows through all layers in order:

```
Request
  → AuthMiddleware          [Middleware]   — role guard, session check (BR04)
  → Controller              [MVC]          — HTTP handling only, no SQL, no business logic
  → Service                 [Service Layer]— business rules (BR05, BR06, BR07, BR08)
  → Repository              [Repository]   — all SQL via PDO prepared statements
  → Model                   [MVC]          — data shape, validation constants
  → EventEmitter (async)    [Observer]     — fires side-effect events (e.g. checkin.recorded)
  → PointListener                          — reacts to events, runs Strategy
  → PointStrategy           [Strategy]     — pluggable point calculation per activity type
```

**Pattern responsibilities at a glance:**

| Pattern | Where | Owns |
|---|---|---|
| MVC | Controllers, Models, Views | HTTP dispatch, data shape, HTML rendering |
| Repository | `app/repositories/` | Every SQL query — nothing else touches PDO |
| Middleware | `app/middleware/` | Auth guard, role enforcement before controllers run |
| Service Layer | `app/services/` | Multi-table business rules, orchestration |
| Observer | `app/events/` | Decoupled side effects after state changes |
| Strategy | `app/strategies/` | Swappable point calculation logic per activity type |

---

## Directory Structure

```
student_ambassador_club/
├── app/
│   ├── config/
│   │   └── database.php                  # PDO singleton connection
│   │
│   ├── controllers/                      # [MVC] HTTP only — delegate to services
│   │   ├── DepartmentController.php
│   │   ├── UserController.php
│   │   ├── ClubController.php
│   │   ├── EventController.php
│   │   ├── TaskController.php
│   │   └── PointController.php
│   │
│   ├── models/                           # [MVC] Data shape + validation constants
│   │   ├── Department.php
│   │   ├── User.php
│   │   ├── Club.php
│   │   ├── ClubMember.php
│   │   ├── Event.php
│   │   ├── EventRegistration.php
│   │   ├── EventAssignment.php
│   │   ├── CheckinLog.php
│   │   ├── AmbassadorTask.php
│   │   ├── TaskAssignment.php
│   │   ├── ActivityPointRule.php
│   │   └── StudentPoint.php
│   │
│   ├── repositories/                     # [Repository] All SQL — one class per table group
│   │   ├── DepartmentRepository.php
│   │   ├── UserRepository.php
│   │   ├── ClubRepository.php
│   │   ├── EventRepository.php
│   │   ├── TaskRepository.php
│   │   └── PointRepository.php
│   │
│   ├── services/                         # [Service Layer] Business rules + orchestration
│   │   ├── ClubService.php               # BR01, BR02, BR03, BR08 (org module)
│   │   ├── EventService.php              # BR05, BR06 (event + checkin)
│   │   └── TaskService.php               # BR07 trigger — calls PointRepository + emits event
│   │
│   ├── middleware/                       # [Middleware] Runs before every controller action
│   │   └── AuthMiddleware.php            # BR04 — role check via $_SESSION['role']
│   │
│   ├── events/                           # [Observer] Event emitter + listeners
│   │   ├── EventEmitter.php              # Lightweight pub/sub (register + emit)
│   │   └── listeners/
│   │       └── PointListener.php         # Reacts to 'checkin.recorded', 'task.completed'
│   │
│   └── strategies/                       # [Strategy] Pluggable point calculation
│       ├── PointStrategyInterface.php    # interface calculate(array $context): int
│       ├── CheckinPointStrategy.php      # Points for event attendance
│       └── TaskCompletionStrategy.php    # Points for finishing a task
│
├── public/
│   ├── index.php                         # Single entry point — routes all requests
│   └── assets/
│       ├── css/style.css
│       └── js/validation.js
│
├── views/                                # [MVC] HTML templates — no SQL, no logic
│   ├── clubs/
│   ├── events/
│   ├── tasks/
│   └── points/
│
├── database/
│   └── student_ambassador_club.sql       # Full schema — import to set up DB
│
└── claude.md
```

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP (plain, no framework) |
| Database | MySQL / MariaDB |
| DB access | PDO with prepared statements (Repository layer only) |
| Architecture | MVC + Repository + Service + Middleware + Observer + Strategy |
| Frontend | HTML, CSS, vanilla JavaScript |
| Entry point | `public/index.php` (Front Controller pattern) |

---

## Design Patterns — Detailed Rules

### 1. MVC

- **Controllers** handle HTTP input/output only. They call a Service method, then pass the result to a view. No SQL, no business logic inside a controller.
- **Models** define the data shape (properties matching DB columns) and hold validation constants (e.g. allowed ENUM values). No SQL inside models.
- **Views** render HTML only. No PHP logic beyond simple loops and conditionals. Never call a Repository or Service from a view.

### 2. Repository

- One repository per table group (e.g. `ClubRepository` covers `clubs` and `club_members`).
- Every single SQL query lives in a repository. Nothing else in the codebase uses PDO directly.
- Methods return plain arrays or Model instances — never raw PDO result sets.
- Repositories have no knowledge of business rules; they execute exactly what they are asked.

```php
// Correct — repository is dumb, just runs SQL
class ClubRepository {
    public function findByCode(string $code): ?array { ... }
    public function insert(array $data): int { ... }
    public function setInactive(int $id): void { ... }  // BR08 support
}
```

### 3. Service Layer

- One service per module: `ClubService`, `EventService`, `TaskService`.
- Services enforce all business rules (BR01–BR08) before calling repositories.
- Services are the only layer that calls multiple repositories in one operation.
- Controllers call exactly one service method per action.

```php
// ClubService enforces BR03 before delegating to ClubRepository
public function addMember(int $clubId, int $userId, string $role): void {
    if ($this->clubRepo->memberExists($clubId, $userId)) {
        throw new DomainException('User is already a member of this club.');  // BR03
    }
    $this->clubRepo->insertMember($clubId, $userId, $role);
}

// TaskService enforces BR07 and fires the Observer event
public function completeTask(int $taskAssignmentId, int $userId): void {
    $this->taskRepo->markDone($taskAssignmentId);
    $this->emitter->emit('task.completed', [
        'user_id' => $userId,
        'task_assignment_id' => $taskAssignmentId,
    ]);  // PointListener picks this up — TaskService does not call PointRepository directly
}
```

### 4. Middleware

- `AuthMiddleware` runs before every controller action via `public/index.php`.
- Checks `$_SESSION['user_id']` and `$_SESSION['role']` against the route's required role.
- Enforces BR04: routes that create events or tasks require `department_staff` or `club_leader`.
- On failure, redirects to login or returns a 403 — the controller never executes.

```php
// In public/index.php — middleware runs first
AuthMiddleware::require('department_staff', 'club_leader');  // BR04
$controller->createEvent();
```

### 5. Observer (EventEmitter + Listeners)

- `EventEmitter` is a lightweight pub/sub: listeners register for named events; `emit()` calls them all.
- Used exclusively for **side effects** that happen after a primary state change — specifically point updates.
- This keeps `EventService` and `TaskService` unaware of point logic (Members 2 and 3 stay decoupled).
- `PointListener` handles both `'checkin.recorded'` and `'task.completed'`, selects the right Strategy, and writes to `student_points` via `PointRepository`.

```php
// EventEmitter — registered at boot in index.php
$emitter->on('checkin.recorded', [new PointListener($pointRepo), 'handle']);
$emitter->on('task.completed',   [new PointListener($pointRepo), 'handle']);

// PointListener — receives the event payload
public function handle(string $event, array $payload): void {
    $strategy = StrategyFactory::for($event);           // picks the right Strategy
    $points   = $strategy->calculate($payload);
    $this->pointRepo->addPoints($payload['user_id'], $payload['semester'], $points);
}
```

### 6. Strategy (Point Calculation)

- `PointStrategyInterface` defines a single method: `calculate(array $context): int`.
- Each activity type that earns points gets its own Strategy class.
- `StrategyFactory::for(string $eventName)` maps event names to Strategy instances.
- Adding a new point-earning activity type means adding one new Strategy class — no existing code changes.

```php
interface PointStrategyInterface {
    public function calculate(array $context): int;
}

class CheckinPointStrategy implements PointStrategyInterface {
    public function calculate(array $context): int {
        // Look up rule from activity_point_rules table or a local constant
        return ActivityPointRule::CHECKIN_POINTS;
    }
}

class TaskCompletionStrategy implements PointStrategyInterface {
    public function calculate(array $context): int {
        return ActivityPointRule::TASK_COMPLETION_POINTS;
    }
}
```

---

## Database

**Database name:** `student_ambassador_club`  
**Charset:** `utf8mb4`, collation `utf8mb4_unicode_ci`  
**Schema file:** `database/student_ambassador_club.sql`

### Tables and Relationships

| Table | Purpose | Key Relationships |
|---|---|---|
| `departments` | Managing departments | 1-N → `users`, `clubs`, `events`, `ambassador_tasks` |
| `users` | All user accounts (all roles) | N-1 → `departments`; 1-N → `club_members`, registrations, assignments |
| `clubs` | Club profiles | N-1 → `departments`; 1-N → `club_members`, `events` |
| `club_members` | Club membership + role | N-1 → `users`, `clubs`; `UNIQUE(club_id, user_id)` |
| `events` | Events ambassadors support | N-1 → `clubs`, `departments`; 1-N → registrations, assignments, checkins |
| `event_registrations` | Member sign-ups for events | `UNIQUE(event_id, user_id)` |
| `event_assignments` | Position assignments within events | N-1 → `events`, `users` |
| `checkin_logs` | Actual attendance records | `UNIQUE(registration_id)` |
| `ambassador_tasks` | Tasks for ambassadors | N-1 → `departments`, `clubs`, `events` (nullable) |
| `task_assignments` | Task-to-member assignments | `UNIQUE(task_id, user_id)` |
| `activity_point_rules` | Point values per activity type | Referenced by Strategy classes |
| `student_points` | Per-semester point totals | `UNIQUE(user_id, semester)` |

### User Roles (ENUM in `users.role`)

- `admin` — full system access
- `department_staff` — manages events and tasks for their department
- `club_leader` — manages their club's members and tasks
- `ambassador` — registers for events, receives task assignments

---

## Business Rules

Enforced at the **Service layer** (not controllers, not views). BR04 is enforced at the **Middleware** layer.

| Rule | Enforced In | Description |
|---|---|---|
| BR01 | `ClubService` / `UserRepository` | No duplicate `email` or `student_code` in `users` |
| BR02 | `ClubService` | No duplicate `club_code` in `clubs` |
| BR03 | `ClubService` | No duplicate `(club_id, user_id)` in `club_members` |
| BR04 | `AuthMiddleware` | Only `department_staff` or `club_leader` can create events and tasks |
| BR05 | `EventService` | Block registration when `capacity` is reached or event `status ≠ open` |
| BR06 | `EventService` | Block duplicate check-in for the same `registration_id` or `(event_id, user_id)` |
| BR07 | `TaskService` + `PointListener` | Points added only after valid check-in or `task_assignment.status = 'done'` |
| BR08 | All Services | Never hard-delete records with dependents — set `status = 'inactive'` |

---

## Validation Rules

### Frontend (`public/assets/js/validation.js`)
- Email must match a valid format
- Name/title fields must not be empty
- Password must meet minimum length
- Event start time must be before end time
- Capacity must be a positive integer

### Backend (Service layer + Models)
- Re-validate all frontend rules server-side — never trust client input
- Uniqueness checks before INSERT (BR01, BR02, BR03) — done in Services via Repositories
- Hash passwords with `password_hash()` before storing; verify with `password_verify()`
- Verify foreign key existence before INSERT (e.g. `department_id` exists)
- Role checked in `AuthMiddleware` before the controller runs (BR04)
- All DB access via PDO prepared statements — never interpolate user input into SQL

---

## Coding Conventions

- **Entry point:** All requests route through `public/index.php` — middleware runs here before dispatch
- **Layer discipline:** Controller → Service → Repository. Never skip a layer. Controllers do not call Repositories directly.
- **DB access:** PDO lives exclusively in Repositories. No PDO outside `app/repositories/`.
- **Password storage:** `password_hash()` / `password_verify()` — never store plain text
- **Soft delete:** `status = 'inactive'` — never `DELETE` a record that has foreign key dependents (BR08)
- **No SQL in views:** Views receive pre-fetched data arrays from controllers only
- **Observer events are strings:** Use dot-notation names — `'checkin.recorded'`, `'task.completed'`
- **Strategies are stateless:** Strategy classes hold no instance data; all context comes via `calculate(array $context)`
- **Naming:** snake_case for DB columns and PHP variables; PascalCase for class names; camelCase for methods
- **Error handling:** Services throw `DomainException` for business rule violations; controllers catch and pass message to view

---

## Team Module Ownership

Each member owns their module end-to-end across all layers.

| Member | Module | Layers Owned |
|---|---|---|
| Member 1 | Club Organization & Members | `ClubController`, `ClubService` (BR01–03, BR08), `ClubRepository`, `DepartmentRepository`, `UserRepository`, `Club*` models, `clubs/` views |
| Member 2 | Events & Participation | `EventController`, `EventService` (BR05, BR06), `EventRepository`, `Event*` + `CheckinLog` models, `events/` views; emits `checkin.recorded` |
| Member 3 | Tasks & Points | `TaskController`, `TaskService` (BR07), `TaskRepository`, `PointRepository`, `PointListener`, `CheckinPointStrategy`, `TaskCompletionStrategy`, `PointController`, `points/` views |

`AuthMiddleware` and `EventEmitter` are shared infrastructure — set up once in `public/index.php`.

---

## Module Priority (Implementation Order)

1. **Club Organization & Member Management** ← *start here (Worksheet 12 focus)*
   - Tables: `departments`, `users`, `clubs`, `club_members`
   - All other modules depend on these as foreign keys

2. **Event Management & Participation**
   - Tables: `events`, `event_registrations`, `event_assignments`, `checkin_logs`

3. **Ambassador Tasks & Contribution Points**
   - Tables: `ambassador_tasks`, `task_assignments`, `activity_point_rules`, `student_points`

---

## CRUD Operations — Module 1 (Worksheet 12)

| Operation | Controller | Service | Repository |
|---|---|---|---|
| List | Calls `ClubService::listClubs()` | Passes filters, returns array | `SELECT` with optional `WHERE` clause |
| Create | Calls `ClubService::createClub()` | Checks BR02, then inserts | `INSERT INTO clubs` |
| Read | Calls `ClubService::getClubDetail()` | Fetches club + member list | JOINs `clubs`, `club_members`, `users` |
| Update | Calls `ClubService::updateClub()` | Validates changes, checks BR08 scope | `UPDATE clubs SET ...` |
| Delete | Calls `ClubService::deactivateClub()` | Enforces BR08 — no hard delete | `UPDATE clubs SET status = 'inactive'` |

---

## Test Cases

| ID | Scenario | Input | Expected | Rule |
|---|---|---|---|---|
| TC01 | Add department | `department_code = CTSV` | Created successfully | — |
| TC02 | Duplicate email | Two users, same email | Rejected — "email already exists" | BR01 |
| TC03 | Add club member | User A → Ambassador Club | `club_members` row created | — |
| TC04 | Duplicate member | User A → same club again | Rejected — duplicate membership | BR03 |
| TC05 | Register for full event | `capacity = 1`, 2nd registration | Rejected — event at capacity | BR05 |
| TC06 | Duplicate check-in | Same `registration_id` scanned twice | 1st succeeds, 2nd rejected | BR06 |
| TC07 | Complete a task | `task_assignment.status` → `done` | Points updated via Observer + Strategy | BR07 |

---

## Submission Checklist

- [ ] `student_ambassador_club.zip` — full source code
- [ ] `database/student_ambassador_club.sql` — complete schema
- [ ] Screenshots: club list, add club, add member, edit status, deactivate, duplicate error
- [ ] Team assignment notes: each member lists their 4 tables, CRUD done, business rules handled, patterns used
- [ ] Live demo: local server running, DB connected, CRUD works, at least 1 business rule demonstrated