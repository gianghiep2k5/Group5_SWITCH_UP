"""Standalone LLM provider abstraction for the Web2 PHP project.

Run inside ai_service/. It intentionally has no dependency on the old DSS FastAPI app.
Switch provider with LLM_PROVIDER in .env: mock | openai | gemini | ollama.
"""
from __future__ import annotations

import asyncio
import logging
import os
from typing import Iterable, List, Optional, TypedDict

import httpx
from dotenv import load_dotenv

load_dotenv()
logger = logging.getLogger(__name__)


class ChatTurn(TypedDict):
    role: str  # "user" | "assistant"
    content: str


SYSTEM_PROMPT = (
    "You are an AI teaching assistant for a university-level Data Science course. "
    "Always respond in the same language as the user's question. "
    "Provide concise answers with Python examples when necessary. "
    "If 'Reference Material' is provided, prioritize using that content. "
    "Remember the context of previous questions in the same session to ensure consistent responses "
    "(for example, when a student says 'do problem 1', 'explain more', 'give another example'). "
    "When writing mathematical formulas, ALWAYS use standard LaTeX. "
    "Inline math must use the format \\(...\\). "
    "Block math must use the format \\[...\\]. "
    "DO NOT use [ ... ] to wrap formulas. "
    "When writing code, always use markdown code blocks with the syntax hint, for example ```python ... ```."
)


def env(name: str, default: str = "") -> str:
    return os.getenv(name, default).strip()


def build_prompt(question: str, lesson_contexts: List[str]) -> str:
    if lesson_contexts:
        ctx = "\n\n".join(f"[Reference {i + 1}] {c}" for i, c in enumerate(lesson_contexts))
        return (
            f"Reference Material:\n{ctx}\n\n"
            f"Student Question: {question}\n\n"
            "Answer in the same language as the student's question. "
            "If the question is in English, answer in English. "
            "If the question is in Vietnamese, answer in Vietnamese."
        )
    return (
        f"Student Question: {question}\n\n"
        "Answer in the same language as the student's question. "
        "If the question is in English, answer in English. "
        "If the question is in Vietnamese, answer in Vietnamese."
    )


async def _ask_mock(prompt: str, history: List[ChatTurn]) -> str:
    return (
        "[MOCK] AI service đang chạy nhưng chưa cấu hình API thật.\n"
        f"Prompt length: {len(prompt)} chars. History turns: {len(history)}."
    )


async def _ask_openai(prompt: str, history: List[ChatTurn]) -> str:
    api_key = env("OPENAI_API_KEY")
    model = env("OPENAI_MODEL", "gpt-4o-mini")
    if not api_key:
        return await _ask_mock(prompt, history)
    try:
        from openai import AsyncOpenAI

        client = AsyncOpenAI(api_key=api_key)
        messages: list[dict] = [{"role": "system", "content": SYSTEM_PROMPT}]
        for h in history:
            if h["role"] in {"user", "assistant"}:
                messages.append({"role": h["role"], "content": h["content"]})
        messages.append({"role": "user", "content": prompt})

        resp = await client.chat.completions.create(
            model=model,
            messages=messages,
            temperature=0.3,
        )
        return (resp.choices[0].message.content or "").strip() or "[OpenAI không trả về nội dung]"
    except Exception as e:
        logger.exception("OpenAI error")
        return f"[OpenAI lỗi] {e}"


async def _ask_gemini(prompt: str, history: List[ChatTurn]) -> str:
    api_key = env("GEMINI_API_KEY")
    model_name = env("GEMINI_MODEL", "gemini-1.5-flash")
    if not api_key:
        return await _ask_mock(prompt, history)
    try:
        import google.generativeai as genai

        genai.configure(api_key=api_key)
        model = genai.GenerativeModel(model_name, system_instruction=SYSTEM_PROMPT)
        gem_history = [
            {"role": "user" if h["role"] == "user" else "model", "parts": [h["content"]]}
            for h in history
            if h["role"] in {"user", "assistant"}
        ]
        chat = model.start_chat(history=gem_history)
        resp = await asyncio.to_thread(chat.send_message, prompt)
        return (resp.text or "").strip() or "[Gemini không trả về nội dung]"
    except Exception as e:
        logger.exception("Gemini error")
        return f"[Gemini lỗi] {e}"


async def _ask_ollama(prompt: str, history: List[ChatTurn]) -> str:
    base_url = env("OLLAMA_BASE_URL", "http://localhost:11434")
    model_name = env("OLLAMA_MODEL", "qwen2.5:3b")
    try:
        history_text = ""
        if history:
            lines = []
            for h in history:
                tag = "Student" if h["role"] == "user" else "Assistant"
                lines.append(f"{tag}: {h['content']}")
            history_text = "Recent conversation history:\n" + "\n".join(lines) + "\n\n"
        full_prompt = history_text + prompt
        async with httpx.AsyncClient(timeout=60) as client:
            r = await client.post(
                f"{base_url}/api/generate",
                json={
                    "model": model_name,
                    "system": SYSTEM_PROMPT,
                    "prompt": full_prompt,
                    "stream": False,
                },
            )
            r.raise_for_status()
            return (r.json().get("response") or "").strip() or "[Ollama không trả về nội dung]"
    except Exception as e:
        logger.exception("Ollama error")
        return f"[Ollama lỗi] {e}"


async def ask_llm(
    question: str,
    lesson_contexts: Optional[List[str]] = None,
    history: Optional[Iterable[ChatTurn]] = None,
) -> str:
    prompt = build_prompt(question, lesson_contexts or [])
    hist = list(history or [])
    provider = env("LLM_PROVIDER", "mock").lower()
    if provider == "openai":
        return await _ask_openai(prompt, hist)
    if provider == "gemini":
        return await _ask_gemini(prompt, hist)
    if provider == "ollama":
        return await _ask_ollama(prompt, hist)
    return await _ask_mock(prompt, hist)
