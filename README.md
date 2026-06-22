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
