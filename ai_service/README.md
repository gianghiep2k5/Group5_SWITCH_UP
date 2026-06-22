# Python AI Service

This service is optional but recommended for the chatbot.

## Run

```bat
cd C:\xampp\htdocs\learning_management_full\ai_service
copy .env.example .env
notepad .env
start_ai_service.bat
```

Open http://127.0.0.1:8010/health to test.

## Endpoints

- `POST /ask`: generate chatbot answer from question + lesson contexts + history.
- `POST /extract`: extract text from uploaded lesson files. PHP uses this when Admin uploads PDF knowledge in Learning Content.

## File extraction

PDF extraction requires `pypdf`, included in `requirements.txt`. For PDF upload from PHP, start this service first.
