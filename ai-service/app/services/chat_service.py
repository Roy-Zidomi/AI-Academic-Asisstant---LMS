"""AI Service — Chat Business Logic."""

import time
import uuid

import httpx
import structlog

from app.config import settings
from app.models.chat import (
    ChatRequest, ChatResponse, ChatResponseData, ResponseMeta, TokenUsage,
)

logger = structlog.get_logger()

# Default system prompt for academic assistant
CHAT_SYSTEM_PROMPT = """You are an AI Academic Assistant for a university Learning Management System. Your role is to help students understand academic concepts clearly and accurately.

Rules:
- Provide accurate, educational explanations
- Use examples when they help understanding
- If you are unsure about something, clearly state your uncertainty
- Never provide direct exam or assignment answers
- Encourage critical thinking and deeper learning
- Respond in the same language as the student question
- Keep responses concise but thorough"""


class ChatService:
    """Handles AI academic chat processing."""

    async def process(self, request: ChatRequest) -> ChatResponse:
        """Process a chat request through Ollama."""
        start_time = time.time()
        request_id = str(uuid.uuid4())

        # Build prompt
        prompt = self._build_prompt(request)
        model = (
            request.options.model if request.options and request.options.model
            else settings.AI_DEFAULT_MODEL_CHAT
        )
        temperature = (
            request.options.temperature if request.options and request.options.temperature is not None
            else settings.AI_CHAT_TEMPERATURE
        )
        max_tokens = (
            request.options.max_tokens if request.options and request.options.max_tokens
            else settings.AI_CHAT_MAX_TOKENS
        )

        # Call Ollama
        logger.info("chat_inference_start", model=model, user_id=request.user_id)

        async with httpx.AsyncClient(timeout=settings.AI_TIMEOUT_CHAT) as client:
            ollama_response = await client.post(
                f"{settings.OLLAMA_BASE_URL}/api/generate",
                json={
                    "model": model,
                    "prompt": prompt,
                    "stream": False,
                    "options": {
                        "temperature": temperature,
                        "num_predict": max_tokens,
                    },
                },
            )
            ollama_response.raise_for_status()
            result = ollama_response.json()

        response_text = result.get("response", "")
        elapsed = time.time() - start_time

        # Token counts from Ollama response
        input_tokens = result.get("prompt_eval_count", 0)
        output_tokens = result.get("eval_count", 0)

        logger.info(
            "chat_inference_complete",
            model=model,
            user_id=request.user_id,
            input_tokens=input_tokens,
            output_tokens=output_tokens,
            duration_s=round(elapsed, 2),
        )

        return ChatResponse(
            success=True,
            data=ChatResponseData(
                response=response_text,
                model=model,
                tokens=TokenUsage(
                    input=input_tokens,
                    output=output_tokens,
                    total=input_tokens + output_tokens,
                ),
                response_time_seconds=round(elapsed, 2),
            ),
            meta=ResponseMeta(
                request_id=request_id,
                timestamp=time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            ),
        )

    def _build_prompt(self, request: ChatRequest) -> str:
        """Build the full prompt from system + context + history + user message."""
        parts = [CHAT_SYSTEM_PROMPT]

        # Add course context if provided
        if request.course_context:
            ctx = request.course_context
            context_parts = []
            if ctx.course_name:
                context_parts.append(f"Course: {ctx.course_name}")
            if ctx.course_topic:
                context_parts.append(f"Topic: {ctx.course_topic}")
            if context_parts:
                parts.append("\n" + "\n".join(context_parts))

        # Add conversation history
        if request.history:
            history_text = "\n\nPrevious conversation:"
            for msg in request.history[-settings.AI_CHAT_HISTORY_LIMIT:]:
                role_label = "Student" if msg.role == "user" else "Assistant"
                history_text += f"\n{role_label}: {msg.content}"
            parts.append(history_text)

        # Add current question
        parts.append(f"\n\nStudent Question: {request.message}")
        parts.append("\nAssistant:")

        return "\n".join(parts)
