# International School_VNUIS — Student Service Hub

Dự án Web2: PHP + MySQL + Session + CRUD + Python AI microservice.

Ứng dụng chính vẫn là PHP để đúng yêu cầu môn Web2. Python chỉ là service phụ cho phần AI Chat, giống kiến trúc DSS: PHP gửi câu hỏi + bài học liên quan + lịch sử chat sang Python, Python gọi LLM và trả câu trả lời về PHP.

1 . Công nghệ

- PHP 8.x
- MySQL / MariaDB
- PDO
- PHP Session
- HTML/CSS/JavaScript
- Python FastAPI cho AI service
- OpenAI / Gemini / Ollama / Mock provider

2 . Actor

- Admin: quản lý lecturers, students, courses, classes, learning content, enroll student.
- Lecturer: xem lớp được phân công, xem danh sách sinh viên, xem lịch sử chat sinh viên, quản lý lịch học, xem alerts và analytics.
- Student: xem lớp, lịch học, chat với AI, xem lịch sử chat.

3 . Chat flow

```txt
Student nhập câu hỏi
→ PHP kiểm tra session + role student
→ PHP lấy course/lớp của student
→ PHP lưu câu hỏi vào chat_messages
→ PHP tìm lesson liên quan trong MySQL
→ PHP lấy lịch sử chat gần nhất
→ PHP gọi Python AI Service /ask
→ Python gọi LLM provider
→ Python trả answer về PHP
→ PHP lưu answer vào chat_messages
→ PHP cập nhật learning_analytics
→ Nếu hỏi lặp topic >= 3 lần trong 7 ngày thì tạo teacher_alerts
```

Nếu Python service tắt hoặc API lỗi, hệ thống vẫn fallback về câu trả lời local retrieval từ bảng `lessons`, nên demo không bị chết.