# DANH SÁCH ĐỀ TÀI GỢI Ý – MULTIMEDIA DESIGN AND WEB DEVELOPMENT
## Trường Quốc tế – ISchool – Đại học Quốc gia Hà Nội (2026)

---

## YÊU CẦU CHUNG VÀ TIÊU CHÍ ĐÁNH GIÁ

### Quy định nhóm & quy mô đề tài

| Tiêu chí | Yêu cầu tối thiểu |
|---|---|
| **Số lượng thành viên** | Tối đa **03 người/nhóm** |
| **Số bảng mỗi người** | Tối thiểu **03 bảng** do mỗi thành viên phụ trách |
| **Chức năng CRUD** | Mỗi bảng phải có đầy đủ Create – Read – Update – Delete |
| **Liên kết dữ liệu** | Các bảng phải có **quan hệ logic** với nhau (1-N, N-N, v.v.) |
| **Cơ sở dữ liệu** | Tự thiết kế, chuẩn hóa đúng chuẩn 3NF, có ERD nộp kèm |

> **Ví dụ phân công nhóm 3 người:** Mỗi người thiết kế và chịu trách nhiệm hoàn thiện ít nhất 3 bảng và toàn bộ CRUD tương ứng. Các bảng của 3 người phải liên kết với nhau thành một hệ thống nghiệp vụ hoàn chỉnh (tối thiểu 9 bảng, khuyến khích 10–15 bảng).

---

### Yêu cầu kỹ thuật bắt buộc

- **Backend tối thiểu:** PHP thuần → Web Application PHP + HTML + CSS + JS  
- **Giao diện:** Cơ bản nhưng phải rõ ràng, sạch đẹp, responsive  
- **Phân quyền:** Có ít nhất 2 vai trò người dùng (Admin và User/Sinh viên/Giảng viên...)  
- **Validation:** Kiểm tra dữ liệu đầu vào ở cả frontend (JS) và backend (PHP)  
- **Database:** MySQL / MariaDB, có file `.sql` export nộp kèm  

---

### Thang điểm & điểm cộng

| Mức độ | Mô tả kỹ thuật | Điểm cơ sở |
|---|---|---|
| **Cơ bản** | PHP thuần, không dùng framework, MVC thủ công, HTML/CSS/JS cơ bản | Đủ điều kiện |
| **Trung bình** | Áp dụng mô hình **MVC** rõ ràng (tách Model/View/Controller), dùng PDO/MySQLi | +5–10% |
| **Khá** | Dùng **Singleton Pattern** cho kết nối DB, **Factory/Repository Pattern** cho Model | +10–15% |
| **Tốt** | Tích hợp **AJAX** cho thao tác CRUD không reload trang, cải thiện UX rõ rệt | +15–20% |
| **Xuất sắc** | Kết hợp nhiều pattern (MVC + Singleton + Observer/Strategy), REST API nội bộ, frontend dùng fetch/axios gọi API | +20–30% |

> **Lưu ý:** Sinh viên có thể tự đề xuất đề tài ngoài danh sách, tuy nhiên phải được **Giảng viên duyệt trước** khi bắt đầu thực hiện.

---


### 5. Web Quản lý Sự kiện & Câu lạc bộ Sinh viên (Club & Event Platform)

- **Bối cảnh:** Nhiều câu lạc bộ và sự kiện do sinh viên, phòng Công tác sinh viên tổ chức; cần quản lý đăng ký, điểm rèn luyện, chứng nhận tham gia.
- **Mục tiêu Web:** Cổng đăng ký sự kiện, quản lý thành viên CLB, ghi nhận điểm hoạt động ngoại khóa.
- **Yêu cầu chức năng (Backend Focus):**
  - **Event Registration & Check-in:** Sinh viên đăng ký sự kiện, backend sinh QR code; check-in ghi nhận và ngăn quét trùng, đảm bảo không vượt sức chứa.
  - **Activity Points:** Mỗi sự kiện có trọng số điểm rèn luyện; backend tự cộng vào hồ sơ SV, hỗ trợ export khi tính điểm cuối kỳ.
  - **Role & Permission:** Phân quyền Ban chủ nhiệm CLB, phòng CTSV, sinh viên; CLB chỉ chỉnh sửa sự kiện của mình.

**Gợi ý bảng CSDL (≥ 9 bảng):**

| Bảng | Mô tả | Người phụ trách |
|---|---|---|
| `users` | Tài khoản hệ thống | Thành viên 1 |
| `clubs` | Danh sách câu lạc bộ | Thành viên 1 |
| `club_members` | Thành viên của từng CLB | Thành viên 1 |
| `events` | Danh sách sự kiện | Thành viên 2 |
| `event_registrations` | SV đăng ký tham gia sự kiện | Thành viên 2 |
| `checkin_logs` | Log check-in sự kiện | Thành viên 2 |
| `activity_point_rules` | Cấu hình điểm rèn luyện theo loại sự kiện | Thành viên 3 |
| `student_points` | Tổng điểm rèn luyện của SV theo kỳ | Thành viên 3 |
| `certificates` | Chứng nhận tham gia được cấp | Thành viên 3 |

---

## BẢNG TÓM TẮT ĐIỂM THƯỞNG THEO MÔ HÌNH KỸ THUẬT

| Tính năng nâng cao | Điểm cộng | Ghi chú |
|---|---|---|
| Mô hình MVC rõ ràng | +5–10% | Tách thư mục Model/View/Controller |
| Singleton Pattern (DB Connection) | +5% | Chỉ tạo 1 instance kết nối DB |
| Repository/DAO Pattern | +5–10% | Tách logic truy vấn DB khỏi Controller |
| AJAX CRUD (không reload trang) | +10–15% | Dùng fetch/XMLHttpRequest |
| REST API + fetch/axios frontend | +15–20% | Backend trả JSON, frontend gọi API |
| Phân quyền RBAC nhiều tầng | +5–10% | Middleware kiểm tra quyền |
| Export PDF/Excel | +5% | Xuất báo cáo dữ liệu |
| Gửi email thông báo | +5% | PHPMailer hoặc SMTP thuần |
| Responsive UI (Bootstrap/Tailwind) | +5% | Giao diện đẹp trên mobile |
| Tự đề xuất đề tài (được GV duyệt) | Tùy theo độ phức tạp | Phải có văn bản GV phê duyệt |

---

*Tài liệu này được biên soạn dành cho môn MULTIMEDIA DESIGN AND WEB DEVELOPMENT – Trường Quốc tế ISchool – ĐHQGHN, 2026.*
