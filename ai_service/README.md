# AI Service

Đây là service Python phụ cho chatbot. Ứng dụng Web2 chính vẫn là PHP + MySQL.

## Chạy nhanh trên Windows

```bat
cd C:\xampp\htdocs\learning_management_full\ai_service
start_ai_service.bat
```

Sau lần chạy đầu tiên, mở file `.env` và cấu hình:

```env
LLM_PROVIDER=openai
OPENAI_API_KEY=your_new_key_here
OPENAI_MODEL=gpt-4o-mini
```

Không commit file `.env` lên GitHub.

## Kiểm tra

Mở trình duyệt:

```txt
http://127.0.0.1:8010/health
```
