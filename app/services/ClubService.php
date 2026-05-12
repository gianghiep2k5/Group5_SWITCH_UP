<?php

class ClubService {
    private $clubRepo;
    private $userRepo;

    public function __construct() {
        $this->clubRepo = new ClubRepository();
        $this->userRepo = new UserRepository();
    }

    public function listClubs($filters = []) {
        return $this->clubRepo->findAll($filters);
    }

    public function getClubDetail($id) {
        $club = $this->clubRepo->findById($id);
        if (!$club) {
            throw new Exception("Club not found.");
        }
        $members = $this->clubRepo->getMembers($id);
        return ['club' => $club, 'members' => $members];
    }

    public function createClub($data) {
        // Enforce BR02: No duplicate club_code in clubs
        $existing = $this->clubRepo->findByCode($data['club_code']);
        if ($existing) {
            throw new DomainException("Club code already exists.");
        }

        return $this->clubRepo->insert($data);
    }

    public function updateClub($id, $data) {
        $club = $this->clubRepo->findById($id);
        if (!$club) {
            throw new Exception("Club not found.");
        }

        // BR02 check for update
        $existing = $this->clubRepo->findByCode($data['club_code']);
        if ($existing && $existing['club_id'] != $id) {
            throw new DomainException("Club code already exists.");
        }

        return $this->clubRepo->update($id, $data);
    }

    public function deactivateClub($id) {
        // Enforce BR08: Never hard-delete records with dependents
        $club = $this->clubRepo->findById($id);
        if (!$club) {
            throw new Exception("Club not found.");
        }
        return $this->clubRepo->setInactive($id);
    }

    public function addMember($clubId, $userId, $role) {
        // Enforce BR03: No duplicate (club_id, user_id) in club_members
        if ($this->clubRepo->memberExists($clubId, $userId)) {
            throw new DomainException("User is already a member of this club.");
        }

        // Generate a random or specific member code
        $memberCode = 'MEM' . strtoupper(uniqid());
        $joinedDate = date('Y-m-d');

        return $this->clubRepo->insertMember($clubId, $userId, $role, $memberCode, $joinedDate);
    }
}
