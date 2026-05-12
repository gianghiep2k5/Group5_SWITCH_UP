<?php

class Database {
    private static $instance = null;
    private $pdo;

    private $host = '127.0.0.1';
    private $user = 'root';
    private $pass = ''; // Set your database password here
    private $dbname = 'student_ambassador_club';

    private function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}
