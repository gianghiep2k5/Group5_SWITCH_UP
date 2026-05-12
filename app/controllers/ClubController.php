<?php

class ClubController {
    private $clubService;
    private $departmentRepo;
    private $userRepo;

    public function __construct() {
        $this->clubService = new ClubService();
        $this->departmentRepo = new DepartmentRepository();
        $this->userRepo = new UserRepository();
    }

    public function index() {
        $clubs = $this->clubService->listClubs();
        require_once __DIR__ . '/../../views/clubs/index.php';
    }

    public function create() {
        $departments = $this->departmentRepo->findAll();
        require_once __DIR__ . '/../../views/clubs/create.php';
    }

    public function store() {
        try {
            $data = [
                'department_id' => $_POST['department_id'],
                'club_name' => $_POST['club_name'],
                'club_code' => $_POST['club_code'],
                'description' => $_POST['description'],
                'founded_date' => $_POST['founded_date'],
                'status' => $_POST['status'] ?? 'active'
            ];
            $this->clubService->createClub($data);
            header("Location: ?action=list_clubs");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
            $departments = $this->departmentRepo->findAll();
            require_once __DIR__ . '/../../views/clubs/create.php';
        }
    }

    public function show($id) {
        try {
            $data = $this->clubService->getClubDetail($id);
            $club = $data['club'];
            $members = $data['members'];
            $users = $this->userRepo->findAllAmbassadors();
            require_once __DIR__ . '/../../views/clubs/show.php';
        } catch (Exception $e) {
            echo htmlspecialchars($e->getMessage());
        }
    }

    public function edit($id) {
        try {
            $data = $this->clubService->getClubDetail($id);
            $club = $data['club'];
            $departments = $this->departmentRepo->findAll();
            require_once __DIR__ . '/../../views/clubs/edit.php';
        } catch (Exception $e) {
            echo htmlspecialchars($e->getMessage());
        }
    }

    public function update($id) {
        try {
            $data = [
                'department_id' => $_POST['department_id'],
                'club_name' => $_POST['club_name'],
                'club_code' => $_POST['club_code'],
                'description' => $_POST['description'],
                'founded_date' => $_POST['founded_date'],
                'status' => $_POST['status']
            ];
            $this->clubService->updateClub($id, $data);
            header("Location: ?action=list_clubs");
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
            $data_fetch = $this->clubService->getClubDetail($id);
            $club = $data_fetch['club'];
            // Fill club with posted data for repopulating form
            $club = array_merge($club, $data);
            $departments = $this->departmentRepo->findAll();
            require_once __DIR__ . '/../../views/clubs/edit.php';
        }
    }

    public function deactivate($id) {
        try {
            $this->clubService->deactivateClub($id);
            header("Location: ?action=list_clubs");
            exit;
        } catch (Exception $e) {
            echo htmlspecialchars($e->getMessage());
        }
    }

    public function addMember($clubId) {
        try {
            $userId = $_POST['user_id'];
            $role = $_POST['position'];
            $this->clubService->addMember($clubId, $userId, $role);
            header("Location: ?action=show_club&id=" . $clubId);
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
            // Need to pass error to show view
            $data = $this->clubService->getClubDetail($clubId);
            $club = $data['club'];
            $members = $data['members'];
            $users = $this->userRepo->findAllAmbassadors();
            require_once __DIR__ . '/../../views/clubs/show.php';
        }
    }
}
