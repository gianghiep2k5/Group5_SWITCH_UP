<?php

class User {
    public $user_id;
    public $department_id;
    public $full_name;
    public $email;
    public $username;
    public $password_hash;
    public $phone;
    public $role;
    public $status;
    public $created_at;

    const ROLE_ADMIN = 'admin';
    const ROLE_STAFF = 'department_staff';
    const ROLE_LEADER = 'club_leader';
    const ROLE_AMBASSADOR = 'ambassador';
}
