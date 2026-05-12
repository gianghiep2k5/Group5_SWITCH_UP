<?php

class ClubMember {
    public $member_id;
    public $club_id;
    public $user_id;
    public $member_code;
    public $position;
    public $joined_date;
    public $status;
    public $created_at;

    const POS_PRESIDENT = 'president';
    const POS_VICE_PRESIDENT = 'vice_president';
    const POS_TEAM_LEADER = 'team_leader';
    const POS_MEMBER = 'member';
}
