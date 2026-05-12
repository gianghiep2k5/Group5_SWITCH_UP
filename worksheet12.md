WORKSHEET 12
Báo cáo Backend - Hệ thống Quản lý Câu lạc bộ Đại sứ Sinh viên
Phiên bản đã viết lại: bỏ mô hình ticket, thay bằng nhiệm vụ và phân công đại sứ
1. Căn cứ đề bài và định hướng chỉnh sửa
Đề bài yêu cầu nhóm xây dựng một hệ thống web có backend PHP và MySQL, có cơ sở dữ liệu quan hệ, CRUD, phân quyền, validation và ít nhất một module hoạt động được trong buổi thực hành. Chủ đề gốc là quản lý câu lạc bộ và sự kiện sinh viên. Với trường hợp nhóm chọn Câu lạc bộ Đại sứ Sinh viên, nghiệp vụ cần được diễn giải lại cho sát thực tế: phòng phụ trách tạo định hướng, sự kiện và nhiệm vụ; CLB Đại sứ Sinh viên nhận phân công, tham gia hoạt động, check-in và được ghi nhận điểm đóng góp.
Điểm chỉnh sửa quan trọng: không dùng tên consultation_tickets và ticket_assignments vì từ ticket dễ bị hiểu là vé sự kiện hoặc hệ thống chăm sóc khách hàng. Thay vào đó, báo cáo dùng ambassador_tasks và task_assignments để thể hiện đúng bản chất: nhiệm vụ đại sứ và phân công đại sứ.
2. Tổng quan hệ thống
Mục	Nội dung
Tên hệ thống	Hệ thống Quản lý Câu lạc bộ Đại sứ Sinh viên
Bối cảnh	Câu lạc bộ Đại sứ Sinh viên hỗ trợ nhà trường trong các hoạt động như ngày hội tuyển sinh, campus tour, đón tiếp học sinh, hỗ trợ sự kiện, truyền thông và kết nối sinh viên.
Đơn vị phụ trách	Phòng Công tác sinh viên, Phòng Tuyển sinh hoặc phòng/ban được nhà trường giao quản lý CLB.
Người dùng chính	Quản trị viên, phòng phụ trách, ban chủ nhiệm CLB, thành viên đại sứ sinh viên.
Mục tiêu	Quản lý thành viên CLB, sự kiện/hoạt động, đăng ký tham gia, phân công nhiệm vụ, check-in, tính điểm đóng góp và theo dõi kết quả hoạt động.
Công nghệ	PHP thuần, MySQL/MariaDB, HTML, CSS, JavaScript, PDO, cấu trúc MVC thủ công.

3. Phạm vi chức năng chính
•	Quản lý phòng/ban phụ trách, tài khoản người dùng, hồ sơ CLB và thành viên CLB.
•	Quản lý sự kiện, cho thành viên đăng ký tham gia, kiểm soát sức chứa và ghi nhận check-in.
•	Quản lý nhiệm vụ đại sứ do phòng phụ trách hoặc ban chủ nhiệm tạo ra.
•	Phân công nhiệm vụ cho thành viên đại sứ, cập nhật trạng thái thực hiện và ghi nhận kết quả.
•	Tính điểm đóng góp/điểm hoạt động dựa trên loại sự kiện, mức độ tham gia và trạng thái hoàn thành nhiệm vụ.
•	Phân quyền thao tác theo vai trò: admin, phòng phụ trách, ban chủ nhiệm CLB và thành viên.
4. Phân công bảng cơ sở dữ liệu
Thành viên	Bảng phụ trách	Module nghiệp vụ	Trách nhiệm chính
Thành viên 1	users, departments, clubs, club_members	Quản lý tổ chức CLB và thành viên	CRUD phòng ban, tài khoản, CLB và thành viên; kiểm tra email/mã sinh viên/mã CLB không trùng; chặn thêm trùng thành viên vào cùng CLB.
Thành viên 2	events, event_registrations, event_assignments, checkin_logs	Quản lý sự kiện và tham gia hoạt động	CRUD sự kiện, đăng ký, phân công vị trí trong sự kiện và check-in; kiểm tra sức chứa; không cho đăng ký/check-in trùng.
Thành viên 3	ambassador_tasks, task_assignments, activity_point_rules, student_points	Quản lý nhiệm vụ đại sứ và điểm đóng góp	CRUD nhiệm vụ, phân công nhiệm vụ, quy tắc điểm và điểm sinh viên; cập nhật trạng thái nhiệm vụ; tự cộng điểm khi hoàn thành hợp lệ.

5. Thiết kế bảng và quan hệ
Bảng	Mục đích	Khóa/quan hệ chính
departments	Lưu thông tin phòng/ban phụ trách CLB hoặc hoạt động.	department_id; 1-N với users, clubs, events, ambassador_tasks.
users	Lưu tài khoản người dùng: admin, cán bộ phụ trách, ban chủ nhiệm, thành viên.	user_id; N-1 với departments; 1-N với club_members, registrations, assignments.
clubs	Lưu thông tin Câu lạc bộ Đại sứ Sinh viên hoặc các CLB liên quan.	club_id; N-1 với departments; 1-N với club_members, events.
club_members	Lưu thành viên thuộc CLB và vai trò trong CLB.	membership_id; N-1 với users và clubs; UNIQUE(club_id, user_id).
events	Lưu hoạt động/sự kiện mà đại sứ tham gia hỗ trợ.	event_id; N-1 với clubs và departments; 1-N với registrations, assignments, checkins.
event_registrations	Lưu đăng ký tham gia sự kiện của thành viên.	registration_id; UNIQUE(event_id, user_id).
event_assignments	Lưu phân công vị trí/nhiệm vụ cụ thể trong từng sự kiện.	assignment_id; N-1 với events và users.
checkin_logs	Ghi nhận check-in thực tế khi thành viên tham gia sự kiện.	checkin_id; UNIQUE(registration_id) hoặc UNIQUE(event_id, user_id).
ambassador_tasks	Lưu nhiệm vụ đại sứ độc lập hoặc nhiệm vụ gắn với sự kiện.	task_id; N-1 với departments, clubs, events; do phòng phụ trách hoặc ban chủ nhiệm tạo.
task_assignments	Lưu phân công thành viên xử lý nhiệm vụ đại sứ.	task_assignment_id; N-1 với ambassador_tasks và users; UNIQUE(task_id, user_id).
activity_point_rules	Quy định điểm theo loại hoạt động/nhiệm vụ.	rule_id; dùng để tính điểm cho check-in và nhiệm vụ hoàn thành.
student_points	Tổng hợp điểm đóng góp của thành viên theo học kỳ.	point_id; UNIQUE(user_id, semester).

6. Module được chọn để làm trong Worksheet 12
Module nên triển khai đầu tiên là Quản lý tổ chức CLB và thành viên, gồm 4 bảng: departments, users, clubs và club_members. Đây là module nền tảng vì những bảng này được các module sự kiện, nhiệm vụ, phân công và điểm đóng góp sử dụng làm khóa ngoại.
Chức năng	Mô tả triển khai
List	Hiển thị danh sách phòng ban, CLB và thành viên CLB; có thể lọc theo phòng ban, CLB hoặc trạng thái.
Create	Thêm phòng ban, tài khoản, CLB và thêm thành viên vào CLB.
Read/Detail	Xem chi tiết CLB, thông tin người quản lý, danh sách thành viên và vai trò của từng thành viên.
Update	Sửa thông tin CLB, trạng thái thành viên, vai trò thành viên trong CLB.
Delete	Xóa mềm hoặc vô hiệu hóa CLB/thành viên; không xóa cứng nếu dữ liệu đã được dùng ở bảng sự kiện hoặc điểm.

7. Business rules xử lý ở backend
Mã rule	Quy tắc nghiệp vụ	Lý do cần xử lý ở backend
BR01	Không cho tạo hai tài khoản có cùng email hoặc cùng mã sinh viên.	Đảm bảo định danh người dùng duy nhất.
BR02	Không cho tạo hai CLB có cùng club_code.	Tránh trùng mã CLB khi quản lý và thống kê.
BR03	Một user không được thêm hai lần vào cùng một CLB.	Đảm bảo quan hệ user-CLB không bị trùng.
BR04	Chỉ phòng phụ trách hoặc ban chủ nhiệm CLB mới được tạo sự kiện/nhiệm vụ.	Đảm bảo phân quyền đúng nghiệp vụ.
BR05	Không cho đăng ký sự kiện khi đã đủ sức chứa hoặc sự kiện đã đóng.	Đảm bảo kiểm soát số lượng tham gia.
BR06	Không cho check-in trùng cho cùng một đăng ký hoặc cùng một user trong một sự kiện.	Đảm bảo điểm đóng góp không bị cộng sai.
BR07	Chỉ cộng điểm khi thành viên đã check-in hoặc nhiệm vụ được xác nhận hoàn thành.	Đảm bảo điểm phản ánh đóng góp thực tế.
BR08	Không cho xóa phòng ban/CLB/user nếu đã có bản ghi liên quan; chuyển sang inactive thay vì xóa cứng.	Bảo toàn toàn vẹn dữ liệu và lịch sử hoạt động.

8. Validation dữ liệu
Nhóm dữ liệu	Frontend validation	Backend validation
Tài khoản	Kiểm tra email đúng định dạng, họ tên không rỗng, mật khẩu đủ độ dài.	Kiểm tra email/mã sinh viên duy nhất; hash mật khẩu trước khi lưu.
CLB	Tên CLB và mã CLB bắt buộc.	Mã CLB duy nhất; department_id phải tồn tại.
Thành viên CLB	Chọn user, CLB và vai trò từ danh sách hợp lệ.	Không thêm trùng user vào cùng CLB; user và club phải tồn tại.
Sự kiện	Thời gian bắt đầu phải trước thời gian kết thúc; capacity là số dương.	Kiểm tra trạng thái sự kiện, capacity, quyền tạo/sửa sự kiện.
Nhiệm vụ đại sứ	Tiêu đề, hạn hoàn thành và mức ưu tiên không rỗng.	Người tạo phải có quyền; người được giao phải là thành viên CLB hợp lệ.
Điểm đóng góp	Điểm là số không âm.	Chỉ cập nhật điểm qua logic hệ thống, không nhập thủ công tùy tiện.

9. Cấu trúc thư mục backend đề xuất
student_ambassador_club/
|-- app/
|   |-- config/database.php
|   |-- controllers/
|   |   |-- DepartmentController.php
|   |   |-- UserController.php
|   |   |-- ClubController.php
|   |   |-- EventController.php
|   |   |-- TaskController.php
|   |   `-- PointController.php
|   |-- models/
|   |   |-- Department.php
|   |   |-- User.php
|   |   |-- Club.php
|   |   |-- ClubMember.php
|   |   |-- Event.php
|   |   |-- EventRegistration.php
|   |   |-- EventAssignment.php
|   |   |-- CheckinLog.php
|   |   |-- AmbassadorTask.php
|   |   |-- TaskAssignment.php
|   |   |-- ActivityPointRule.php
|   |   `-- StudentPoint.php
|   |-- repositories/
|   |   |-- ClubRepository.php
|   |   |-- EventRepository.php
|   |   `-- TaskRepository.php
|   `-- middleware/AuthMiddleware.php
|-- public/index.php
|-- public/assets/css/style.css
|-- public/assets/js/validation.js
|-- views/
|   |-- clubs/
|   |-- events/
|   |-- tasks/
|   `-- points/
`-- database/student_ambassador_club.sql
10. SQL schema tóm tắt
Phần dưới đây là schema tóm tắt để thể hiện khóa chính, khóa ngoại và ràng buộc chính. Khi nộp thực tế, nhóm nên tách thành file database/student_ambassador_club.sql.
CREATE DATABASE IF NOT EXISTS student_ambassador_club
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_ambassador_club;

CREATE TABLE departments (
  department_id INT AUTO_INCREMENT PRIMARY KEY,
  department_code VARCHAR(30) NOT NULL UNIQUE,
  department_name VARCHAR(150) NOT NULL,
  status ENUM('active','inactive') DEFAULT 'active'
);

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','department_staff','club_leader','ambassador') NOT NULL,
  student_code VARCHAR(30) UNIQUE,
  status ENUM('active','inactive') DEFAULT 'active',
  FOREIGN KEY (department_id) REFERENCES departments(department_id)
);

CREATE TABLE clubs (
  club_id INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT NOT NULL,
  club_code VARCHAR(30) NOT NULL UNIQUE,
  club_name VARCHAR(150) NOT NULL,
  description TEXT,
  status ENUM('active','inactive') DEFAULT 'active',
  FOREIGN KEY (department_id) REFERENCES departments(department_id)
);

CREATE TABLE club_members (
  membership_id INT AUTO_INCREMENT PRIMARY KEY,
  club_id INT NOT NULL,
  user_id INT NOT NULL,
  member_role ENUM('president','vice_president','team_leader','member') DEFAULT 'member',
  joined_at DATE,
  status ENUM('active','inactive') DEFAULT 'active',
  UNIQUE (club_id, user_id),
  FOREIGN KEY (club_id) REFERENCES clubs(club_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE events (
  event_id INT AUTO_INCREMENT PRIMARY KEY,
  club_id INT NOT NULL,
  department_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  event_type VARCHAR(60) NOT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME NOT NULL,
  capacity INT NOT NULL,
  status ENUM('draft','open','closed','completed','cancelled') DEFAULT 'draft',
  FOREIGN KEY (club_id) REFERENCES clubs(club_id),
  FOREIGN KEY (department_id) REFERENCES departments(department_id)
);

CREATE TABLE event_registrations (
  registration_id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  status ENUM('registered','cancelled','attended') DEFAULT 'registered',
  qr_code VARCHAR(120) UNIQUE,
  UNIQUE (event_id, user_id),
  FOREIGN KEY (event_id) REFERENCES events(event_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE event_assignments (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  position_name VARCHAR(100) NOT NULL,
  assignment_note TEXT,
  status ENUM('assigned','completed','cancelled') DEFAULT 'assigned',
  UNIQUE (event_id, user_id, position_name),
  FOREIGN KEY (event_id) REFERENCES events(event_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE checkin_logs (
  checkin_id INT AUTO_INCREMENT PRIMARY KEY,
  registration_id INT NOT NULL,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  checkin_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  checked_by INT NULL,
  UNIQUE (registration_id),
  FOREIGN KEY (registration_id) REFERENCES event_registrations(registration_id),
  FOREIGN KEY (event_id) REFERENCES events(event_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id),
  FOREIGN KEY (checked_by) REFERENCES users(user_id)
);

CREATE TABLE ambassador_tasks (
  task_id INT AUTO_INCREMENT PRIMARY KEY,
  department_id INT NOT NULL,
  club_id INT NOT NULL,
  event_id INT NULL,
  title VARCHAR(200) NOT NULL,
  task_type VARCHAR(60) NOT NULL,
  priority ENUM('low','medium','high') DEFAULT 'medium',
  due_date DATE,
  status ENUM('open','in_progress','completed','cancelled') DEFAULT 'open',
  created_by INT NOT NULL,
  FOREIGN KEY (department_id) REFERENCES departments(department_id),
  FOREIGN KEY (club_id) REFERENCES clubs(club_id),
  FOREIGN KEY (event_id) REFERENCES events(event_id),
  FOREIGN KEY (created_by) REFERENCES users(user_id)
);

CREATE TABLE task_assignments (
  task_assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  user_id INT NOT NULL,
  assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  status ENUM('assigned','in_progress','done','cancelled') DEFAULT 'assigned',
  result_note TEXT,
  UNIQUE (task_id, user_id),
  FOREIGN KEY (task_id) REFERENCES ambassador_tasks(task_id),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE activity_point_rules (
  rule_id INT AUTO_INCREMENT PRIMARY KEY,
  activity_type VARCHAR(60) NOT NULL UNIQUE,
  point_value INT NOT NULL,
  description TEXT,
  status ENUM('active','inactive') DEFAULT 'active'
);

CREATE TABLE student_points (
  point_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  semester VARCHAR(20) NOT NULL,
  total_points INT DEFAULT 0,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (user_id, semester),
  FOREIGN KEY (user_id) REFERENCES users(user_id)
);
11. Test cases cho demo cuối buổi
Test case	Dữ liệu thử	Kết quả mong đợi
TC01 - Thêm phòng ban	department_code = CTSV	Tạo thành công phòng Công tác sinh viên.
TC02 - Trùng email	Tạo 2 user cùng email	Backend từ chối user thứ hai và báo lỗi email đã tồn tại.
TC03 - Thêm thành viên CLB	Thêm user A vào CLB Đại sứ	Tạo bản ghi club_members thành công.
TC04 - Thêm trùng thành viên	Thêm lại user A vào cùng CLB	Backend từ chối do vi phạm UNIQUE(club_id, user_id).
TC05 - Đăng ký sự kiện đầy sức chứa	capacity = 1, đăng ký người thứ hai	Backend từ chối đăng ký vì sự kiện đã đủ chỗ.
TC06 - Check-in trùng	Quét cùng registration_id hai lần	Lần đầu thành công, lần hai bị từ chối.
TC07 - Hoàn thành nhiệm vụ	Đổi trạng thái task_assignment sang done	Hệ thống ghi nhận hoàn thành và cập nhật điểm nếu hợp lệ.

12. Minh chứng cần nộp
•	File nén source code: student_ambassador_club.zip.
•	File SQL: database/student_ambassador_club.sql.
•	Ảnh chụp màn hình: danh sách CLB, thêm CLB, thêm thành viên, sửa trạng thái, xóa/vô hiệu hóa, kiểm tra lỗi trùng dữ liệu.
•	Ghi chú phân công: mỗi thành viên nêu rõ 4 bảng phụ trách, phần CRUD đã làm và business rule đã xử lý.
•	Demo ngắn: chạy project local, kết nối database, thao tác CRUD và chứng minh ít nhất một business rule hoạt động.
13. Kết luận
Báo cáo đã được viết lại theo hướng phù hợp hơn với Câu lạc bộ Đại sứ Sinh viên. Thay vì dùng ticket tư vấn, hệ thống tập trung vào các nghiệp vụ cốt lõi của CLB: thành viên, sự kiện, phân công nhiệm vụ, check-in và điểm đóng góp. Cách thiết kế này rõ nghĩa hơn, sát với vai trò của phòng phụ trách và đáp ứng yêu cầu Worksheet 12 về backend, database quan hệ, CRUD, validation và business logic.
