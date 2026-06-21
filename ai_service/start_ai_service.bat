@echo off
cd /d %~dp0
if not exist .venv (
    py -m venv .venv
)
call .venv\Scripts\activate
pip install -r requirements.txt
if not exist .env copy .env.example .env
echo.
echo AI service is starting at http://127.0.0.1:8010
echo Edit ai_service\.env to set LLM_PROVIDER and API key.
echo.
uvicorn main:app --reload --host 127.0.0.1 --port 8010
