# Student Service Hub — Web2 Full Project

Dự án Web2: **PHP + MySQL + Session + CRUD + Python AI microservice**.

Ứng dụng chính vẫn là PHP để đúng yêu cầu môn Web2. Python chỉ là service phụ cho phần AI Chat, giống kiến trúc DSS: PHP gửi câu hỏi + bài học liên quan + lịch sử chat sang Python, Python gọi LLM và trả câu trả lời về PHP.

## 1. Công nghệ

- PHP 8.x
- MySQL / MariaDB
- PDO
- PHP Session
- HTML/CSS/JavaScript
- Python FastAPI cho AI service
- OpenAI / Gemini / Ollama / Mock provider

## 2. Actor

- Admin: quản lý lecturers, students, courses, classes, learning content, enroll student.
- Lecturer: xem lớp được phân công, xem danh sách sinh viên, xem lịch sử chat sinh viên, quản lý lịch học, xem alerts và analytics.
- Student: xem lớp, lịch học, chat với AI, xem lịch sử chat.

## 3. Cài đặt PHP Web App

Copy folder này vào:

```txt
C:\xampp\htdocs\learning_management_full
```

Bật Apache + MySQL trong XAMPP.

Import database theo thứ tự:

```txt
database/schema.sql
database/seed.sql
```

Mở web:

```txt
http://localhost/learning_management_full/login.php
```

Tài khoản demo:

```txt
Admin:    admin@uni.edu.vn / 123456
Lecturer: huong.tt@uni.edu.vn / 123456
Student:  an.ph@student.uni.edu.vn / 123456
```

## 4. Chạy Python AI Service

Mở terminal mới:

```bat
cd C:\xampp\htdocs\learning_management_full\ai_service
start_ai_service.bat
```

Lần đầu chạy xong, mở file:

```txt
ai_service/.env
```

Cấu hình provider:

### Dùng mock để test không cần API key

```env
LLM_PROVIDER=mock
```

### Dùng OpenAI

```env
LLM_PROVIDER=openai
OPENAI_API_KEY=your_new_api_key_here
OPENAI_MODEL=gpt-4o-mini
```

Sau khi sửa `.env`, tắt terminal AI service rồi chạy lại `start_ai_service.bat`.

Kiểm tra service:

```txt
http://127.0.0.1:8010/health
```

## 5. Chat flow

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

## 6. File quan trọng

```txt
config/db.php                 Kết nối MySQL bằng PDO
includes/auth.php             Session + role guard
student/chat.php              Chat UI + lưu DB + gọi AI service
services/ai_client.php        PHP client gọi Python AI service
ai_service/main.py            FastAPI endpoint /ask
ai_service/llm.py             OpenAI/Gemini/Ollama/Mock provider
database/schema.sql           11 bảng chính
database/seed.sql             Dữ liệu demo
```

## 7. Lưu ý bảo mật

Không đưa API key vào GitHub, report, slide hoặc chat. Chỉ đặt API key trong:

```txt
ai_service/.env
```

File này đã được ignore bởi `.gitignore`.

## 8. Thêm bài học / knowledge cho chatbot

Tạo môn học trước ở:

```txt
Admin → Courses
```

Sau đó thêm knowledge cho chatbot ở:

```txt
Admin → Learning Content
```

Có 2 cách thêm:

```txt
Cách 1: Paste trực tiếp nội dung bài học vào Lesson content.
Cách 2: Upload file TXT/MD/CSV/HTML/code/PDF.
```

Lưu ý: chatbox không đọc từ tên file. Chatbox đọc từ cột `lessons.content` trong MySQL. Vì vậy sau khi upload, hệ thống sẽ cố gắng trích xuất text và lưu vào `lessons.content`.

PDF upload cần Python AI service đang chạy:

```bat
cd C:\xampp\htdocs\learning_management_full\ai_service
start_ai_service.bat
```

Nếu PDF không trích xuất được, hãy copy phần nội dung quan trọng và paste vào ô `Lesson content`.
