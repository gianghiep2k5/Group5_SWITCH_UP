-- =====================================================================
-- AI Chatbot hỗ trợ học tập môn Data Science
-- Database Schema (MySQL 8.0+ / MariaDB 10.5+)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS ds_chatbot
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE ds_chatbot;

-- ---------------------------------------------------------------------
-- 1. users  — Admin / Giáo viên / Sinh viên
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(150)        NOT NULL,
    email           VARCHAR(150)        NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NOT NULL,
    role            ENUM('admin','teacher','student') NOT NULL,
    parent_id       BIGINT UNSIGNED     NULL, -- teacher.parent_id -> admin.id
    avatar_url      VARCHAR(255)        NULL,
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    last_login_at   DATETIME            NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_parent
        FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_users_role (role),
    INDEX idx_users_parent (parent_id)
);

-- ---------------------------------------------------------------------
-- 2. courses  — Môn học (Data Science, ...)
-- ---------------------------------------------------------------------
CREATE TABLE courses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(30)         NOT NULL UNIQUE,   -- DS101
    name            VARCHAR(150)        NOT NULL,
    description     TEXT                NULL,
    created_by      BIGINT UNSIGNED     NOT NULL,          -- admin
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_creator
        FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ---------------------------------------------------------------------
-- 3. classes  — Lớp học (1 môn -> nhiều lớp, mỗi lớp 1 GV phụ trách)
-- ---------------------------------------------------------------------
CREATE TABLE classes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       BIGINT UNSIGNED     NOT NULL,
    teacher_id      BIGINT UNSIGNED     NOT NULL,
    name            VARCHAR(120)        NOT NULL,          -- DS101-K23A
    semester        VARCHAR(20)         NULL,              -- 2025-1
    start_date      DATE                NULL,
    end_date        DATE                NULL,
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_classes_course
        FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_classes_teacher
        FOREIGN KEY (teacher_id) REFERENCES users(id),
    INDEX idx_classes_teacher (teacher_id),
    INDEX idx_classes_course  (course_id)
);

-- ---------------------------------------------------------------------
-- 4. class_students  — Sinh viên thuộc lớp nào (N-N)
-- ---------------------------------------------------------------------
CREATE TABLE class_students (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id        BIGINT UNSIGNED     NOT NULL,
    student_id      BIGINT UNSIGNED     NOT NULL,
    joined_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status          ENUM('active','dropped','completed') NOT NULL DEFAULT 'active',
    UNIQUE KEY uq_class_student (class_id, student_id),
    CONSTRAINT fk_cs_class
        FOREIGN KEY (class_id)   REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_student
        FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- 5. schedules  — Lịch học của lớp
-- ---------------------------------------------------------------------
CREATE TABLE schedules (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id        BIGINT UNSIGNED     NOT NULL,
    title           VARCHAR(200)        NOT NULL,
    start_time      DATETIME            NOT NULL,
    end_time        DATETIME            NOT NULL,
    room            VARCHAR(50)         NULL,
    note            TEXT                NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedules_class
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    INDEX idx_schedules_class_time (class_id, start_time)
);

-- ---------------------------------------------------------------------
-- 6. lessons  — Bài học của môn (dùng làm tri thức cho chatbot)
-- ---------------------------------------------------------------------
CREATE TABLE lessons (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id       BIGINT UNSIGNED     NOT NULL,
    title           VARCHAR(200)        NOT NULL,
    content         LONGTEXT            NULL,              -- markdown / text
    file_url        VARCHAR(255)        NULL,              -- pdf, slide
    order_index     INT                 NOT NULL DEFAULT 0,
    embedding_id    VARCHAR(64)         NULL,              -- id bên Vector DB
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lessons_course
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_lessons_course (course_id, order_index)
);

-- ---------------------------------------------------------------------
-- 7. question_topics  — Chủ đề câu hỏi (Python, Pandas, ML, ...)
-- ---------------------------------------------------------------------
CREATE TABLE question_topics (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)        NOT NULL UNIQUE,
    slug            VARCHAR(100)        NOT NULL UNIQUE,
    description     VARCHAR(255)        NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- ---------------------------------------------------------------------
-- 8. chat_sessions  — Phiên trò chuyện của sinh viên
-- ---------------------------------------------------------------------
CREATE TABLE chat_sessions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      BIGINT UNSIGNED     NOT NULL,
    course_id       BIGINT UNSIGNED     NULL,
    title           VARCHAR(200)        NULL,
    started_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at        DATETIME            NULL,
    message_count   INT                 NOT NULL DEFAULT 0,
    CONSTRAINT fk_cs_student2
        FOREIGN KEY (student_id) REFERENCES users(id)   ON DELETE CASCADE,
    CONSTRAINT fk_cs_course
        FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE SET NULL,
    INDEX idx_chat_sessions_student (student_id, started_at)
);

-- ---------------------------------------------------------------------
-- 9. chat_messages  — Từng câu hỏi / câu trả lời trong phiên
-- ---------------------------------------------------------------------
CREATE TABLE chat_messages (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id      BIGINT UNSIGNED     NOT NULL,
    sender          ENUM('student','bot') NOT NULL,
    content         LONGTEXT            NOT NULL,
    topic_id        BIGINT UNSIGNED     NULL,
    lesson_id       BIGINT UNSIGNED     NULL,
    tokens_used     INT                 NULL,
    response_time_ms INT                NULL,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msg_session
        FOREIGN KEY (session_id) REFERENCES chat_sessions(id)   ON DELETE CASCADE,
    CONSTRAINT fk_msg_topic
        FOREIGN KEY (topic_id)   REFERENCES question_topics(id) ON DELETE SET NULL,
    CONSTRAINT fk_msg_lesson
        FOREIGN KEY (lesson_id)  REFERENCES lessons(id)         ON DELETE SET NULL,
    INDEX idx_msg_session_time (session_id, created_at),
    INDEX idx_msg_topic        (topic_id)
);

-- ---------------------------------------------------------------------
-- 10. learning_analytics  — Thống kê tần suất học tập (SV / lớp / ngày)
-- ---------------------------------------------------------------------
CREATE TABLE learning_analytics (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      BIGINT UNSIGNED     NOT NULL,
    class_id        BIGINT UNSIGNED     NULL,
    date            DATE                NOT NULL,
    session_count   INT                 NOT NULL DEFAULT 0,
    question_count  INT                 NOT NULL DEFAULT 0,
    total_time_sec  INT                 NOT NULL DEFAULT 0,
    top_topic_id    BIGINT UNSIGNED     NULL,
    repeat_score    DECIMAL(5,2)        NOT NULL DEFAULT 0,
    UNIQUE KEY uq_la_student_class_date (student_id, class_id, date),
    CONSTRAINT fk_la_student
        FOREIGN KEY (student_id)   REFERENCES users(id)            ON DELETE CASCADE,
    CONSTRAINT fk_la_class
        FOREIGN KEY (class_id)     REFERENCES classes(id)          ON DELETE SET NULL,
    CONSTRAINT fk_la_topic
        FOREIGN KEY (top_topic_id) REFERENCES question_topics(id)  ON DELETE SET NULL,
    INDEX idx_la_date (date)
);

-- ---------------------------------------------------------------------
-- 11. teacher_alerts  — Cảnh báo gửi cho giáo viên
-- ---------------------------------------------------------------------
CREATE TABLE teacher_alerts (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id      BIGINT UNSIGNED     NOT NULL,
    class_id        BIGINT UNSIGNED     NULL,
    student_id      BIGINT UNSIGNED     NULL,
    topic_id        BIGINT UNSIGNED     NULL,
    alert_type      ENUM('repeat_question','class_struggle','low_activity','other')
                                        NOT NULL,
    message         VARCHAR(500)        NOT NULL,
    severity        ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    is_read         TINYINT(1)          NOT NULL DEFAULT 0,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at         DATETIME            NULL,
    CONSTRAINT fk_alert_teacher
        FOREIGN KEY (teacher_id) REFERENCES users(id)           ON DELETE CASCADE,
    CONSTRAINT fk_alert_class
        FOREIGN KEY (class_id)   REFERENCES classes(id)         ON DELETE SET NULL,
    CONSTRAINT fk_alert_student
        FOREIGN KEY (student_id) REFERENCES users(id)           ON DELETE SET NULL,
    CONSTRAINT fk_alert_topic
        FOREIGN KEY (topic_id)   REFERENCES question_topics(id) ON DELETE SET NULL,
    INDEX idx_alert_teacher_unread (teacher_id, is_read, created_at)
);
