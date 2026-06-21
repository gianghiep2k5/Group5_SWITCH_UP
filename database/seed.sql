-- =====================================================================
-- Seed data demo cho ds_chatbot
-- Chạy SAU schema.sql
-- =====================================================================
USE ds_chatbot;

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM teacher_alerts;
DELETE FROM learning_analytics;
DELETE FROM chat_messages;
DELETE FROM chat_sessions;
DELETE FROM question_topics;
DELETE FROM lessons;
DELETE FROM schedules;
DELETE FROM class_students;
DELETE FROM classes;
DELETE FROM courses;
DELETE FROM users;
-- reset AUTO_INCREMENT về 1 cho các bảng có id cố định trong seed
ALTER TABLE users             AUTO_INCREMENT = 1;
ALTER TABLE courses           AUTO_INCREMENT = 1;
ALTER TABLE classes           AUTO_INCREMENT = 1;
ALTER TABLE class_students    AUTO_INCREMENT = 1;
ALTER TABLE schedules         AUTO_INCREMENT = 1;
ALTER TABLE lessons           AUTO_INCREMENT = 1;
ALTER TABLE question_topics   AUTO_INCREMENT = 1;
ALTER TABLE chat_sessions     AUTO_INCREMENT = 1;
ALTER TABLE chat_messages     AUTO_INCREMENT = 1;
ALTER TABLE learning_analytics AUTO_INCREMENT = 1;
ALTER TABLE teacher_alerts    AUTO_INCREMENT = 1;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. USERS  (password_hash giả định = bcrypt của "123456")
-- ---------------------------------------------------------------------
-- Admin
INSERT INTO users (id, full_name, email, password_hash, role, parent_id) VALUES
(1, 'Nguyễn Văn Admin', 'admin@uni.edu.vn',
 '$2y$12$q2Wb3pPtjGFeoGZ7zwrQO.6hbPD6KVuYRrpmsCWhwan161bmmMKOO', 'admin', NULL);

-- Giáo viên (parent_id = 1, do admin tạo)
INSERT INTO users (id, full_name, email, password_hash, role, parent_id) VALUES
(2, 'TS. Trần Thị Hương', 'huong.tt@uni.edu.vn',
 '$2y$12$q2Wb3pPtjGFeoGZ7zwrQO.6hbPD6KVuYRrpmsCWhwan161bmmMKOO', 'teacher', 1),
(3, 'ThS. Lê Quang Minh',  'minh.lq@uni.edu.vn',
 '$2y$12$q2Wb3pPtjGFeoGZ7zwrQO.6hbPD6KVuYRrpmsCWhwan161bmmMKOO', 'teacher', 1);

-- Sinh viên
INSERT INTO users (id, full_name, email, password_hash, role) VALUES
(10, 'Phạm Hoàng An',    'an.ph@student.uni.edu.vn',    '$2y$12$q2Wb3pPtjGFeoGZ7zwrQO.6hbPD6KVuYRrpmsCWhwan161bmmMKOO', 'student'),
(11, 'Vũ Thị Bình',       'binh.vt@student.uni.edu.vn',  '$2y$12$q2Wb3pPtjGFeoGZ7zwrQO.6hbPD6KVuYRrpmsCWhwan161bmmMKOO', 'student'),
(12, 'Đặng Quốc Cường',   'cuong.dq@student.uni.edu.vn', '$2y$12$q2Wb3pPtjGFeoGZ7zwrQO.6hbPD6KVuYRrpmsCWhwan161bmmMKOO', 'student'),
(13, 'Hoàng Thị Diệu',    'dieu.ht@student.uni.edu.vn',  '$2y$12$q2Wb3pPtjGFeoGZ7zwrQO.6hbPD6KVuYRrpmsCWhwan161bmmMKOO', 'student'),
(14, 'Lý Minh Đạt',       'dat.lm@student.uni.edu.vn',   '$2y$12$q2Wb3pPtjGFeoGZ7zwrQO.6hbPD6KVuYRrpmsCWhwan161bmmMKOO', 'student');

-- ---------------------------------------------------------------------
-- 2. COURSES
-- ---------------------------------------------------------------------
INSERT INTO courses (id, code, name, description, created_by) VALUES
(1, 'DS101', 'Nhập môn Data Science',
   'Python, Pandas, NumPy, Visualization, EDA cơ bản', 1),
(2, 'DS201', 'Machine Learning căn bản',
   'Hồi quy, phân loại, clustering, đánh giá mô hình', 1);

-- ---------------------------------------------------------------------
-- 3. CLASSES
-- ---------------------------------------------------------------------
INSERT INTO classes (id, course_id, teacher_id, name, semester, start_date, end_date) VALUES
(1, 1, 2, 'DS101 - K23A', '2025-2', '2026-02-10', '2026-06-10'),
(2, 1, 3, 'DS101 - K23B', '2025-2', '2026-02-10', '2026-06-10'),
(3, 2, 2, 'DS201 - K22A', '2025-2', '2026-02-10', '2026-06-10');

-- ---------------------------------------------------------------------
-- 4. CLASS_STUDENTS
-- ---------------------------------------------------------------------
INSERT INTO class_students (class_id, student_id) VALUES
(1, 10), (1, 11), (1, 12),     -- DS101-K23A
(2, 13), (2, 14),               -- DS101-K23B
(3, 10), (3, 13);               -- DS201-K22A

-- ---------------------------------------------------------------------
-- 5. SCHEDULES
-- ---------------------------------------------------------------------
INSERT INTO schedules (class_id, title, start_time, end_time, room) VALUES
(1, 'Buổi 1: Giới thiệu Data Science', '2026-02-10 07:30:00', '2026-02-10 09:30:00', 'A101'),
(1, 'Buổi 2: Pandas cơ bản',           '2026-02-17 07:30:00', '2026-02-17 09:30:00', 'A101'),
(1, 'Buổi 3: EDA & Visualization',     '2026-02-24 07:30:00', '2026-02-24 09:30:00', 'A101'),
(2, 'Buổi 1: Giới thiệu Data Science', '2026-02-11 13:00:00', '2026-02-11 15:00:00', 'B202'),
(3, 'Buổi 1: Linear Regression',       '2026-02-12 09:45:00', '2026-02-12 11:45:00', 'C303');

-- ---------------------------------------------------------------------
-- 6. LESSONS  (embedding_id = id giả lập bên Vector DB)
-- ---------------------------------------------------------------------
INSERT INTO lessons (id, course_id, title, content, order_index, embedding_id) VALUES
(1, 1, 'Python cơ bản cho DS',
   'Biến, kiểu dữ liệu, list/dict, list comprehension...', 1, 'vec_ds101_001'),
(2, 1, 'Pandas DataFrame',
   'DataFrame, Series, đọc CSV, filter, groupby, merge...', 2, 'vec_ds101_002'),
(3, 1, 'Visualization với Matplotlib',
   'plot, scatter, histogram, subplot...',                  3, 'vec_ds101_003'),
(4, 2, 'Linear Regression',
   'Hàm mất mát MSE, gradient descent, sklearn...',         1, 'vec_ds201_001'),
(5, 2, 'Logistic Regression & phân loại',
   'Sigmoid, cross-entropy, confusion matrix...',           2, 'vec_ds201_002');

-- ---------------------------------------------------------------------
-- 7. QUESTION_TOPICS
-- ---------------------------------------------------------------------
INSERT INTO question_topics (id, name, slug, description) VALUES
(1, 'Python',         'python',         'Cú pháp & cấu trúc dữ liệu Python'),
(2, 'Pandas',         'pandas',         'Xử lý dữ liệu với Pandas'),
(3, 'Visualization',  'visualization',  'Trực quan hoá dữ liệu'),
(4, 'Linear Regression','linear-regression', 'Hồi quy tuyến tính'),
(5, 'Classification', 'classification', 'Bài toán phân loại');


-- ---------------------------------------------------------------------
-- 8. CHAT_SESSIONS
-- ---------------------------------------------------------------------
INSERT INTO chat_sessions (id, student_id, course_id, title, started_at, ended_at, message_count) VALUES
(1, 10, 1, 'Hỏi về Pandas groupby',     '2026-03-01 20:10:00', '2026-03-01 20:35:00', 6),
(2, 10, 1, 'Đọc CSV bị lỗi encoding',   '2026-03-03 21:00:00', '2026-03-03 21:15:00', 4),
(3, 11, 1, 'List comprehension Python', '2026-03-02 19:30:00', '2026-03-02 19:50:00', 4),
(4, 12, 1, 'Vẽ biểu đồ matplotlib',     '2026-03-04 22:00:00', '2026-03-04 22:20:00', 4),
(5, 13, 2, 'Linear regression sklearn', '2026-03-05 20:00:00', '2026-03-05 20:30:00', 6),
(6, 10, 1, 'Lại hỏi groupby Pandas',    '2026-03-06 19:45:00', '2026-03-06 20:00:00', 4);

-- ---------------------------------------------------------------------
-- 9. CHAT_MESSAGES
-- ---------------------------------------------------------------------
-- Session 1: An hỏi groupby
INSERT INTO chat_messages (session_id, sender, content, topic_id, lesson_id, tokens_used, response_time_ms) VALUES
(1, 'student', 'Pandas groupby hoạt động như thế nào ạ?',          2, 2, NULL, NULL),
(1, 'bot',     'groupby chia DataFrame thành các nhóm theo cột...', 2, 2, 180, 1200),
(1, 'student', 'Cho em ví dụ với cột "city" được không?',           2, 2, NULL, NULL),
(1, 'bot',     'df.groupby("city")["sales"].sum() ...',             2, 2, 95,  900),
(1, 'student', 'Khác gì với pivot_table?',                          2, 2, NULL, NULL),
(1, 'bot',     'pivot_table aggregate được theo cả hàng & cột...',  2, 2, 140, 1100);

-- Session 2: An hỏi đọc CSV
INSERT INTO chat_messages (session_id, sender, content, topic_id, lesson_id, tokens_used, response_time_ms) VALUES
(2, 'student', 'Đọc file CSV tiếng Việt bị lỗi font ạ',             2, 2, NULL, NULL),
(2, 'bot',     'Thêm encoding="utf-8-sig" hoặc "cp1258"...',         2, 2, 80,  700),
(2, 'student', 'Vẫn lỗi, file có dấu phẩy trong cell',               2, 2, NULL, NULL),
(2, 'bot',     'Dùng quotechar và đổi sep nếu là TSV...',            2, 2, 110, 950);

-- Session 3: Bình hỏi list comprehension
INSERT INTO chat_messages (session_id, sender, content, topic_id, lesson_id, tokens_used, response_time_ms) VALUES
(3, 'student', 'List comprehension là gì?',                          1, 1, NULL, NULL),
(3, 'bot',     '[expr for x in iterable if cond] - cú pháp ngắn...', 1, 1, 120, 850),
(3, 'student', 'Có nhanh hơn for loop thường không?',                1, 1, NULL, NULL),
(3, 'bot',     'Thường nhanh hơn ~30% vì tối ưu C-level...',         1, 1, 90,  700);

-- Session 4: Cường hỏi matplotlib
INSERT INTO chat_messages (session_id, sender, content, topic_id, lesson_id, tokens_used, response_time_ms) VALUES
(4, 'student', 'Làm sao vẽ histogram trong matplotlib?',             3, 3, NULL, NULL),
(4, 'bot',     'plt.hist(data, bins=20) ...',                        3, 3, 70,  600),
(4, 'student', 'Đổi màu thì sao?',                                    3, 3, NULL, NULL),
(4, 'bot',     'Truyền color="steelblue" hoặc edgecolor=...',         3, 3, 65,  550);

-- Session 5: Diệu hỏi linear regression
INSERT INTO chat_messages (session_id, sender, content, topic_id, lesson_id, tokens_used, response_time_ms) VALUES
(5, 'student', 'sklearn LinearRegression dùng sao ạ?',                4, 4, NULL, NULL),
(5, 'bot',     'from sklearn.linear_model import LinearRegression...',4, 4, 200, 1300),
(5, 'student', 'R^2 âm là sao?',                                      4, 4, NULL, NULL),
(5, 'bot',     'Mô hình tệ hơn baseline mean - kiểm tra feature.',    4, 4, 150, 1100),
(5, 'student', 'Cách chuẩn hoá feature?',                             4, 4, NULL, NULL),
(5, 'bot',     'StandardScaler hoặc MinMaxScaler từ sklearn...',      4, 4, 130, 1000);

-- Session 6: An hỏi LẠI groupby (kích hoạt cảnh báo lặp)
INSERT INTO chat_messages (session_id, sender, content, topic_id, lesson_id, tokens_used, response_time_ms) VALUES
(6, 'student', 'Em vẫn chưa hiểu groupby, giải thích lại ạ',          2, 2, NULL, NULL),
(6, 'bot',     'groupby tạo các nhóm dựa trên giá trị cột...',         2, 2, 175, 1150),
(6, 'student', 'agg và transform khác gì?',                            2, 2, NULL, NULL),
(6, 'bot',     'agg trả 1 giá trị/nhóm, transform giữ nguyên shape...',2, 2, 140, 1050);

-- ---------------------------------------------------------------------
-- 10. LEARNING_ANALYTICS  (gộp theo SV / lớp / ngày)
-- ---------------------------------------------------------------------
INSERT INTO learning_analytics
  (student_id, class_id, date, session_count, question_count, total_time_sec, top_topic_id, repeat_score) VALUES
(10, 1, '2026-03-01', 1, 3,  1500, 2, 0.20),
(10, 1, '2026-03-03', 1, 2,   900, 2, 0.30),
(10, 1, '2026-03-06', 1, 2,   900, 2, 0.85),  -- hỏi lặp -> high
(11, 1, '2026-03-02', 1, 2,  1200, 1, 0.10),
(12, 1, '2026-03-04', 1, 2,  1200, 3, 0.10),
(13, 3, '2026-03-05', 1, 3,  1800, 4, 0.15),
(14, 2, '2026-03-05', 0, 0,     0, NULL, 0.00); -- low_activity

-- ---------------------------------------------------------------------
-- 11. TEACHER_ALERTS
-- ---------------------------------------------------------------------
INSERT INTO teacher_alerts
  (teacher_id, class_id, student_id, topic_id, alert_type, message, severity) VALUES
(2, 1, 10, 2, 'repeat_question',
 'SV Phạm Hoàng An hỏi lại chủ đề "Pandas groupby" lần thứ 3 trong 1 tuần.', 'high'),
(2, 1, NULL, 2, 'class_struggle',
 'Lớp DS101-K23A có 60% câu hỏi tập trung vào "Pandas" - cần ôn lại.', 'medium'),
(3, 2, 14, NULL, 'low_activity',
 'SV Lý Minh Đạt không có phiên chat nào trong 7 ngày qua.', 'low');

-- =====================================================================
-- Một số query demo nhanh (chạy sau khi seed):
-- =====================================================================
-- Top chủ đề được hỏi nhiều nhất:
--   SELECT qt.name, COUNT(*) AS n
--   FROM chat_messages m JOIN question_topics qt ON qt.id = m.topic_id
--   WHERE m.sender = 'student'
--   GROUP BY qt.name ORDER BY n DESC;
--
-- Sinh viên hỏi lặp nhất tuần qua:
--   SELECT u.full_name, MAX(la.repeat_score) AS score
--   FROM learning_analytics la JOIN users u ON u.id = la.student_id
--   WHERE la.date >= CURDATE() - INTERVAL 7 DAY
--   GROUP BY u.id ORDER BY score DESC;
--
-- Cảnh báo chưa đọc của 1 GV:
--   SELECT * FROM teacher_alerts
--   WHERE teacher_id = 2 AND is_read = 0
--   ORDER BY created_at DESC;
