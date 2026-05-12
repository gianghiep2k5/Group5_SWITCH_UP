<?php

class Club {
    public $club_id;
    public $department_id;
    public $club_name;
    public $club_code;
    public $description;
    public $founded_date;
    public $status;
    public $created_at;

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
}
