<?php

class UserRepository {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function findById($id) {
        $sql = "SELECT * FROM users WHERE user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findAllAmbassadors() {
        // Users who can be added to clubs
        $sql = "SELECT * FROM users WHERE status = 'active'";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
}
