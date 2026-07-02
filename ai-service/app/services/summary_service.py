"""AI Service — Summary Business Logic."""

import base64
import json
import time
import uuid

import httpx
import structlog

from app.config import settings
from app.core.text_extractor import TextExtractor
from app.models.summary import (
    SummaryRequest, SummaryResponse, SummaryData, SummaryMetadata,
    Concept, GlossaryEntry,
)

logger = structlog.get_logger()

SUMMARY_SYSTEM_PROMPT = """You are an expert academic content summarizer. Generate a comprehensive summary of the provided learning material.

You MUST return your response in the following JSON format ONLY (no other text):
{
  "executive_summary": "A concise 2-3 paragraph summary of the entire material",
  "key_points": ["Point 1", "Point 2", "Point 3"],
  "important_concepts": [{"term": "Concept Name", "definition": "Clear definition"}],
  "glossary": [{"term": "Technical Term", "definition": "Brief definition"}],
  "study_guide": "A structured study outline with recommended learning sequence"
}

Rules:
- Extract the most important information
- Be accurate and faithful to the source material
- Use clear, student-friendly language
- Include 5-10 key points
- Include 3-7 important concepts
- Include relevant technical terms in the glossary
- Respond in the same language as the source material"""


class SummaryService:
    """Handles AI material summarization."""

    async def process(self, request: SummaryRequest) -> SummaryResponse:
        """Process a summary request."""
        start_time = time.time()
        request_id = str(uuid.uuid4())

        # Extract text from material
        file_bytes = base64.b64decode(request.material.content_base64)
        extractor = TextExtractor()
        text_content = extractor.extract(
            file_bytes,
            request.material.content_type,
            request.material.filename,
        )

        if not text_content or len(text_content.strip()) < 50:
            raise ValueError("Extracted text is too short or empty.")

        # Build prompt
        model = (
            request.options.model if request.options and request.options.model
            else settings.AI_DEFAULT_MODEL_SUMMARY
        )
        temperature = (
            request.options.temperature if request.options and request.options.temperature is not None
            else settings.AI_SUMMARY_TEMPERATURE
        )

        prompt = f"""{SUMMARY_SYSTEM_PROMPT}

Material Title: {request.material.filename}

Material Content:
{text_content[:12000]}"""

        logger.info("summary_inference_start", model=model, user_id=request.user_id,
                     text_length=len(text_content))

        # Call Ollama
        async with httpx.AsyncClient(timeout=settings.AI_TIMEOUT_SUMMARY) as client:
            resp = await client.post(
                f"{settings.OLLAMA_BASE_URL}/api/generate",
                json={
                    "model": model,
                    "prompt": prompt,
                    "stream": False,
                    "options": {
                        "temperature": temperature,
                        "num_predict": settings.AI_SUMMARY_MAX_TOKENS,
                    },
                },
            )
            resp.raise_for_status()
            result = resp.json()

        raw_response = result.get("response", "")
        elapsed = time.time() - start_time
        input_tokens = result.get("prompt_eval_count", 0)
        output_tokens = result.get("eval_count", 0)

        # Parse structured JSON output
        parsed = self._parse_summary(raw_response)

        logger.info("summary_inference_complete", model=model,
                     user_id=request.user_id, duration_s=round(elapsed, 2))

        return SummaryResponse(
            success=True,
            data=SummaryData(
                executive_summary=parsed.get("executive_summary", raw_response),
                key_points=parsed.get("key_points", []),
                important_concepts=[
                    Concept(**c) for c in parsed.get("important_concepts", [])
                ],
                glossary=[
                    GlossaryEntry(**g) for g in parsed.get("glossary", [])
                ],
                study_guide=parsed.get("study_guide", ""),
                metadata=SummaryMetadata(
                    model=model,
                    tokens={"input": input_tokens, "output": output_tokens,
                            "total": input_tokens + output_tokens},
                    generation_time_seconds=round(elapsed, 2),
                    source_characters=len(text_content),
                ),
            ),
            meta={
                "request_id": request_id,
                "timestamp": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            },
        )

    def _parse_summary(self, raw: str) -> dict:
        """Try to parse JSON from LLM output."""
        if not raw:
            return {"executive_summary": "", "key_points": [], "important_concepts": [],
                    "glossary": [], "study_guide": ""}

        import re

        # Helper function to clean and parse a string as JSON
        def try_parse(text: str) -> dict | None:
            # Normalize newlines
            cleaned = text.replace('\r\n', '\n')
            # Escape literal newlines inside double-quoted strings
            cleaned = re.sub(r'"(?:[^"\\]|\\.)*"', lambda m: m.group(0).replace('\n', '\\n'), cleaned, flags=re.DOTALL)
            try:
                return json.loads(cleaned)
            except json.JSONDecodeError:
                return None

        # 1. Try direct parse
        parsed = try_parse(raw)
        if parsed is not None:
            return parsed

        # 2. Try to extract JSON from markdown code block
        match = re.search(r"```(?:json)?\s*(\{.*?\})\s*```", raw, re.DOTALL)
        if match:
            parsed = try_parse(match.group(1))
            if parsed is not None:
                return parsed

        # 3. Try to find JSON object in text
        start = raw.find("{")
        end = raw.rfind("}") + 1
        if start != -1 and end > start:
            parsed = try_parse(raw[start:end])
            if parsed is not None:
                return parsed

        # Fallback: return raw as executive summary
        logger.warning("summary_parse_failed", raw_length=len(raw))
        return {"executive_summary": raw, "key_points": [], "important_concepts": [],
                "glossary": [], "study_guide": ""}
