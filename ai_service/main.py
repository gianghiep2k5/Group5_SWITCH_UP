from __future__ import annotations

import logging
import os
import time
from typing import List, Literal

from dotenv import load_dotenv
from fastapi import FastAPI
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


@app.get("/health")
def health():
    provider = os.getenv("LLM_PROVIDER", "mock")
    return {"ok": True, "provider": provider}


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
