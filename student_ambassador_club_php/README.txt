STUDENT AMBASSADOR CLUB MANAGEMENT - WORKSHEET 12

1. Import database
- Open phpMyAdmin
- Import student_ambassador_club_database.sql
- Database name: student_ambassador_club

2. Run project
- Copy folder student_ambassador_club_php to:
  C:\xampp\htdocs\
- Open browser:
  http://localhost/student_ambassador_club_php/

3. Demo functions
- Events CRUD:
  http://localhost/student_ambassador_club_php/events/index.php

- Event registration:
  http://localhost/student_ambassador_club_php/registrations/register.php

- Check-in:
  http://localhost/student_ambassador_club_php/checkin/checkin.php

4. Backend business rules
- Prevent duplicate event registration.
- Prevent registration when event capacity is full.
- Prevent duplicate check-in.
- Prevent event deletion if it has registrations or check-ins.

5. Main tables used today
- events
- event_registrations
- event_assignments
- checkin_logs
- users
- clubs
- club_members
