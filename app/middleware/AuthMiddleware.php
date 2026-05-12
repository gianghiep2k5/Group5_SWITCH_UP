<?php

class AuthMiddleware {
    public static function require(...$roles) {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Unauthorized: Please log in.");
        }
        
        if (!isset($_SESSION['role'])) {
            throw new Exception("Unauthorized: Role not found.");
        }

        $userRole = $_SESSION['role'];
        
        // Admin can do everything unless strictly constrained, but for safety we check if role is in array
        if (!in_array($userRole, $roles) && $userRole !== 'admin') {
            throw new Exception("Forbidden: You do not have permission to perform this action.");
        }
    }
}
