from __future__ import annotations

import logging
import os
import tempfile
import time
from pathlib import Path
from typing import List, Literal

from dotenv import load_dotenv
from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field

from llm import ask_llm

load_dotenv()
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("ai_service")

app = FastAPI(title="Learning Management AI Service", version="1.0.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["*"],
    allow_headers=["*"],
)


class ChatTurn(BaseModel):
    role: Literal["user", "assistant"]
    content: str = Field(min_length=1)


class AskRequest(BaseModel):
    question: str = Field(min_length=1)
    lesson_contexts: List[str] = []
    history: List[ChatTurn] = []


class AskResponse(BaseModel):
    answer: str
    provider: str
    response_time_ms: int


class ExtractResponse(BaseModel):
    filename: str
    text: str
    chars: int


def _clean_extracted_text(text: str) -> str:
    text = text.replace("\r\n", "\n").replace("\r", "\n")
    lines = [line.strip() for line in text.split("\n")]
    cleaned: list[str] = []
    blank = False
    for line in lines:
        if not line:
            if not blank:
                cleaned.append("")
            blank = True
        else:
            cleaned.append(line)
            blank = False
    return "\n".join(cleaned).strip()


def _extract_pdf(path: Path) -> str:
    try:
        from pypdf import PdfReader
    except Exception as exc:
        raise RuntimeError("pypdf is not installed. Run: pip install -r requirements.txt") from exc

    reader = PdfReader(str(path))
    parts: list[str] = []
    for i, page in enumerate(reader.pages):
        try:
            parts.append(page.extract_text() or "")
        except Exception as exc:
            logger.warning("PDF page %s extraction failed: %s", i + 1, exc)
    return _clean_extracted_text("\n\n".join(parts))


@app.get("/health")
def health():
    provider = os.getenv("LLM_PROVIDER", "mock")
    return {"ok": True, "provider": provider}


@app.post("/extract", response_model=ExtractResponse)
async def extract_file(file: UploadFile = File(...)):
    """Extract plain text from an uploaded lesson file.

    PHP uses this endpoint when Admin uploads PDF knowledge for the chatbot.
    Text-like files are also supported for convenience.
    """
    filename = file.filename or "lesson_file"
    ext = Path(filename).suffix.lower().lstrip(".")
    allowed = {"txt", "md", "csv", "json", "py", "sql", "html", "htm", "pdf"}
    if ext not in allowed:
        raise HTTPException(400, f"Unsupported file type: {ext}")

    data = await file.read()
    if len(data) > 15 * 1024 * 1024:
        raise HTTPException(413, "File is too large. Maximum size is 15MB.")

    if ext in {"txt", "md", "csv", "json", "py", "sql", "html", "htm"}:
        text = data.decode("utf-8", errors="ignore")
        if ext in {"html", "htm"}:
            import re
            text = re.sub(r"<[^>]+>", " ", text)
        text = _clean_extracted_text(text)
    else:
        with tempfile.NamedTemporaryFile(delete=False, suffix="." + ext) as tmp:
            tmp.write(data)
            tmp_path = Path(tmp.name)
        try:
            text = _extract_pdf(tmp_path)
        finally:
            try:
                tmp_path.unlink(missing_ok=True)
            except Exception:
                pass

    if not text:
        raise HTTPException(422, "Could not extract text from this file. Please paste lesson content manually.")
    return ExtractResponse(filename=filename, text=text[:120000], chars=len(text))


@app.post("/ask", response_model=AskResponse)
async def ask(payload: AskRequest):
    start = time.perf_counter()
    history = [h.model_dump() for h in payload.history[-8:]]
    try:
        answer = await ask_llm(
            question=payload.question,
            lesson_contexts=payload.lesson_contexts[:4],
            history=history,
        )
    except Exception as e:  # final safety net
        logger.exception("AI service failed")
        answer = f"[AI service lỗi] {e}"
    elapsed_ms = int((time.perf_counter() - start) * 1000)
    return AskResponse(
        answer=answer,
        provider=os.getenv("LLM_PROVIDER", "mock"),
        response_time_ms=elapsed_ms,
    )
