<?php

class DepartmentRepository {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findAll() {
        $sql = "SELECT * FROM departments";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
}
