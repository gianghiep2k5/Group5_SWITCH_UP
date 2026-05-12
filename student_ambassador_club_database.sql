-- Database for Student Ambassador Club Management
-- Worksheet 12 - PHP + MySQL Backend Task
-- Topic: Cau lac bo Dai su Sinh vien

DROP DATABASE IF EXISTS student_ambassador_club;
CREATE DATABASE student_ambassador_club
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE student_ambassador_club;

-- =====================================================
-- 1. PHONG/BAN PHU TRACH
-- =====================================================
CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(150) NOT NULL,
    department_code VARCHAR(30) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 2. TAI KHOAN HE THONG
-- role:
-- admin: quan tri he thong
-- department_staff: nhan su phong phu trach
-- club_leader: ban chu nhiem CLB
-- ambassador: thanh vien/dai su sinh vien
-- =====================================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30),
    role ENUM('admin', 'department_staff', 'club_leader', 'ambassador') NOT NULL DEFAULT 'ambassador',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_department
        FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 3. CAU LAC BO
-- =====================================================
CREATE TABLE clubs (
    club_id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    club_name VARCHAR(150) NOT NULL,
    club_code VARCHAR(30) NOT NULL UNIQUE,
    description TEXT,
    founded_date DATE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_clubs_department
        FOREIGN KEY (department_id)
        REFERENCES departments(department_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 4. THANH VIEN CAU LAC BO
-- =====================================================
CREATE TABLE club_members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    user_id INT NOT NULL,
    member_code VARCHAR(50) NOT NULL UNIQUE,
    position ENUM('president', 'vice_president', 'team_leader', 'member') NOT NULL DEFAULT 'member',
    joined_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'graduated') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_club_member UNIQUE (club_id, user_id),
    CONSTRAINT fk_members_club
        FOREIGN KEY (club_id)
        REFERENCES clubs(club_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_members_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 5. SU KIEN / HOAT DONG
-- created_by: nguoi tao su kien, thuong la phong phu trach hoac ban chu nhiem
-- =====================================================
CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    created_by INT NOT NULL,
    event_name VARCHAR(200) NOT NULL,
    event_type ENUM('open_day', 'campus_tour', 'workshop', 'training', 'admission_support', 'other') NOT NULL DEFAULT 'other',
    description TEXT,
    location VARCHAR(255),
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    capacity INT NOT NULL DEFAULT 30,
    status ENUM('draft', 'published', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_events_club
        FOREIGN KEY (club_id)
        REFERENCES clubs(club_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_events_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT chk_event_time CHECK (end_time > start_time),
    CONSTRAINT chk_event_capacity CHECK (capacity > 0)
) ENGINE=InnoDB;

-- =====================================================
-- 6. DANG KY THAM GIA SU KIEN
-- Business rule can implement in PHP:
-- - khong cho dang ky trung event_id + member_id
-- - khong cho dang ky neu su kien da du capacity
-- =====================================================
CREATE TABLE event_registrations (
    registration_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    member_id INT NOT NULL,
    registration_status ENUM('registered', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'registered',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    note TEXT,
    CONSTRAINT uq_event_member_registration UNIQUE (event_id, member_id),
    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_registrations_member
        FOREIGN KEY (member_id)
        REFERENCES club_members(member_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 7. PHAN CONG NHIEM VU TRONG SU KIEN
-- =====================================================
CREATE TABLE event_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    member_id INT NOT NULL,
    assigned_by INT NOT NULL,
    duty_name VARCHAR(150) NOT NULL,
    duty_description TEXT,
    assignment_status ENUM('assigned', 'accepted', 'completed', 'cancelled') NOT NULL DEFAULT 'assigned',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_event_member_duty UNIQUE (event_id, member_id, duty_name),
    CONSTRAINT fk_event_assignments_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_event_assignments_member
        FOREIGN KEY (member_id)
        REFERENCES club_members(member_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_event_assignments_assigned_by
        FOREIGN KEY (assigned_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 8. CHECK-IN SU KIEN
-- Business rule can implement in PHP:
-- - mot thanh vien chi duoc check-in 1 lan cho 1 su kien
-- =====================================================
CREATE TABLE checkin_logs (
    checkin_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    member_id INT NOT NULL,
    checked_by INT NOT NULL,
    checkin_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checkin_method ENUM('manual', 'qr_code') NOT NULL DEFAULT 'manual',
    note TEXT,
    CONSTRAINT uq_event_member_checkin UNIQUE (event_id, member_id),
    CONSTRAINT fk_checkin_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_checkin_member
        FOREIGN KEY (member_id)
        REFERENCES club_members(member_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_checkin_checked_by
        FOREIGN KEY (checked_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 9. NHIEM VU CUA DAI SU SINH VIEN
-- Nhiem vu co the do phong phu trach hoac ban chu nhiem CLB tao
-- =====================================================
CREATE TABLE ambassador_tasks (
    task_id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    created_by INT NOT NULL,
    task_title VARCHAR(200) NOT NULL,
    task_type ENUM('consultation_support', 'campus_tour_support', 'content_support', 'event_support', 'training', 'other') NOT NULL DEFAULT 'other',
    description TEXT,
    due_date DATETIME,
    priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tasks_club
        FOREIGN KEY (club_id)
        REFERENCES clubs(club_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_tasks_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 10. PHAN CONG NHIEM VU CHO DAI SU
-- =====================================================
CREATE TABLE task_assignments (
    task_assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    member_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('assigned', 'accepted', 'submitted', 'approved', 'rejected') NOT NULL DEFAULT 'assigned',
    report_text TEXT,
    completed_at DATETIME NULL,
    CONSTRAINT uq_task_member UNIQUE (task_id, member_id),
    CONSTRAINT fk_task_assignments_task
        FOREIGN KEY (task_id)
        REFERENCES ambassador_tasks(task_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_task_assignments_member
        FOREIGN KEY (member_id)
        REFERENCES club_members(member_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_task_assignments_assigned_by
        FOREIGN KEY (assigned_by)
        REFERENCES users(user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 11. QUY TAC TINH DIEM DONG GOP
-- =====================================================
CREATE TABLE activity_point_rules (
    rule_id INT AUTO_INCREMENT PRIMARY KEY,
    activity_type ENUM('event_checkin', 'event_assignment_completed', 'task_approved', 'training_completed') NOT NULL,
    rule_name VARCHAR(150) NOT NULL,
    points INT NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_activity_type_rule UNIQUE (activity_type),
    CONSTRAINT chk_rule_points CHECK (points >= 0)
) ENGINE=InnoDB;

-- =====================================================
-- 12. DIEM DONG GOP CUA THANH VIEN
-- total_points co the duoc cap nhat sau khi check-in/hoan thanh nhiem vu
-- =====================================================
CREATE TABLE student_points (
    point_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    semester VARCHAR(30) NOT NULL,
    total_points INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_member_semester UNIQUE (member_id, semester),
    CONSTRAINT fk_student_points_member
        FOREIGN KEY (member_id)
        REFERENCES club_members(member_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT chk_total_points CHECK (total_points >= 0)
) ENGINE=InnoDB;

-- =====================================================
-- SAMPLE DATA FOR TESTING
-- Mat khau demo khong phai hash that. Khi code PHP can dung password_hash().
-- =====================================================
INSERT INTO departments (department_name, department_code, description) VALUES
('Phong Cong tac Sinh vien', 'CTSV', 'Phong phu trach hoat dong sinh vien va cau lac bo'),
('Phong Tuyen sinh', 'TS', 'Phong phu trach cac hoat dong tuyen sinh va tu van');

INSERT INTO users (department_id, full_name, email, username, password_hash, phone, role, status) VALUES
(1, 'Admin System', 'admin@ischool.edu.vn', 'admin', 'demo_hash', '0900000001', 'admin', 'active'),
(1, 'Nguyen Thi Phu Trach', 'staff.ctsv@ischool.edu.vn', 'ctsv_staff', 'demo_hash', '0900000002', 'department_staff', 'active'),
(1, 'Tran Van Chu Nhiem', 'leader.ambassador@ischool.edu.vn', 'club_leader', 'demo_hash', '0900000003', 'club_leader', 'active'),
(NULL, 'Le Minh Dai Su', 'minh.ambassador@ischool.edu.vn', 'minh_ds', 'demo_hash', '0900000004', 'ambassador', 'active'),
(NULL, 'Pham Anh Dai Su', 'anh.ambassador@ischool.edu.vn', 'anh_ds', 'demo_hash', '0900000005', 'ambassador', 'active');

INSERT INTO clubs (department_id, club_name, club_code, description, founded_date, status) VALUES
(1, 'Cau lac bo Dai su Sinh vien', 'SAC', 'CLB ho tro su kien, campus tour va hoat dong tuyen sinh cua truong', '2024-09-01', 'active');

INSERT INTO club_members (club_id, user_id, member_code, position, joined_date, status) VALUES
(1, 3, 'SAC001', 'president', '2024-09-01', 'active'),
(1, 4, 'SAC002', 'member', '2024-09-15', 'active'),
(1, 5, 'SAC003', 'member', '2024-09-15', 'active');

INSERT INTO events (club_id, created_by, event_name, event_type, description, location, start_time, end_time, capacity, status) VALUES
(1, 2, 'Open Day 2026', 'open_day', 'Ngay hoi tu van va trai nghiem truong cho hoc sinh THPT', 'Main Hall', '2026-03-20 08:00:00', '2026-03-20 12:00:00', 30, 'published'),
(1, 2, 'Campus Tour for High School Students', 'campus_tour', 'Dai su sinh vien huong dan hoc sinh tham quan campus', 'Campus Lobby', '2026-03-25 09:00:00', '2026-03-25 11:00:00', 20, 'published');

INSERT INTO event_registrations (event_id, member_id, registration_status, note) VALUES
(1, 2, 'approved', 'Dang ky ho tro Open Day'),
(1, 3, 'approved', 'Dang ky ho tro Open Day');

INSERT INTO event_assignments (event_id, member_id, assigned_by, duty_name, duty_description, assignment_status) VALUES
(1, 2, 3, 'Huong dan khach moi', 'Don tiep va huong dan hoc sinh den khu vuc tu van', 'assigned'),
(1, 3, 3, 'Ho tro check-in', 'Ho tro check-in hoc sinh tham du', 'assigned');

INSERT INTO checkin_logs (event_id, member_id, checked_by, checkin_method, note) VALUES
(1, 2, 3, 'manual', 'Check-in dau gio');

INSERT INTO ambassador_tasks (club_id, created_by, task_title, task_type, description, due_date, priority, status) VALUES
(1, 2, 'Chuan bi noi dung gioi thieu CLB cho Open Day', 'content_support', 'Soan noi dung ngan gioi thieu vai tro cua Dai su Sinh vien', '2026-03-18 17:00:00', 'high', 'open'),
(1, 3, 'Tap huan ky nang campus tour', 'training', 'Tham gia buoi tap huan ky nang huong dan tham quan truong', '2026-03-22 17:00:00', 'medium', 'open');

INSERT INTO task_assignments (task_id, member_id, assigned_by, status) VALUES
(1, 2, 3, 'assigned'),
(2, 3, 3, 'assigned');

INSERT INTO activity_point_rules (activity_type, rule_name, points, description, status) VALUES
('event_checkin', 'Diem check-in tham gia su kien', 5, 'Thanh vien check-in hop le tai su kien se duoc cong diem', 'active'),
('event_assignment_completed', 'Diem hoan thanh nhiem vu trong su kien', 10, 'Thanh vien hoan thanh nhiem vu duoc phan cong trong su kien', 'active'),
('task_approved', 'Diem hoan thanh nhiem vu CLB', 10, 'Nhiem vu duoc duyet hoan thanh boi nguoi phu trach', 'active'),
('training_completed', 'Diem tham gia tap huan', 5, 'Thanh vien hoan thanh buoi tap huan', 'active');

INSERT INTO student_points (member_id, semester, total_points) VALUES
(1, 'Spring 2026', 0),
(2, 'Spring 2026', 5),
(3, 'Spring 2026', 0);
