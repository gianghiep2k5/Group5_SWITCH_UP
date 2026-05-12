<?php

class ClubRepository {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findAll($filters = []) {
        $sql = "SELECT c.*, d.department_name 
                FROM clubs c 
                LEFT JOIN departments d ON c.department_id = d.department_id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById($id) {
        $sql = "SELECT c.*, d.department_name 
                FROM clubs c 
                LEFT JOIN departments d ON c.department_id = d.department_id 
                WHERE c.club_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findByCode($code) {
        $sql = "SELECT * FROM clubs WHERE club_code = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    public function insert($data) {
        $sql = "INSERT INTO clubs (department_id, club_name, club_code, description, founded_date, status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['department_id'],
            $data['club_name'],
            $data['club_code'],
            $data['description'],
            $data['founded_date'],
            $data['status'] ?? Club::STATUS_ACTIVE
        ]);
        return $this->pdo->lastInsertId();
    }

    public function update($id, $data) {
        $sql = "UPDATE clubs 
                SET department_id = ?, club_name = ?, club_code = ?, description = ?, founded_date = ?, status = ? 
                WHERE club_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['department_id'],
            $data['club_name'],
            $data['club_code'],
            $data['description'],
            $data['founded_date'],
            $data['status'],
            $id
        ]);
    }

    public function setInactive($id) {
        $sql = "UPDATE clubs SET status = 'inactive' WHERE club_id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function memberExists($clubId, $userId) {
        $sql = "SELECT COUNT(*) FROM club_members WHERE club_id = ? AND user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clubId, $userId]);
        return $stmt->fetchColumn() > 0;
    }

    public function insertMember($clubId, $userId, $role, $memberCode, $joinedDate) {
        $sql = "INSERT INTO club_members (club_id, user_id, member_code, position, joined_date, status) 
                VALUES (?, ?, ?, ?, ?, 'active')";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$clubId, $userId, $memberCode, $role, $joinedDate]);
    }

    public function getMembers($clubId) {
        $sql = "SELECT cm.*, u.full_name, u.email 
                FROM club_members cm 
                JOIN users u ON cm.user_id = u.user_id 
                WHERE cm.club_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clubId]);
        return $stmt->fetchAll();
    }
}
