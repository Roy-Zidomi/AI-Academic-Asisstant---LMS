"""AI Service — Quiz Generation Business Logic."""

import base64
import json
import re
import time
import uuid

import httpx
import structlog
from fastapi import HTTPException

from app.config import settings
from app.core.text_extractor import TextExtractor
from app.models.quiz import (
    QuizRequest, QuizResponse, QuizData, QuizMetadata, GeneratedQuestion,
)

logger = structlog.get_logger()

QUIZ_MCQ_PROMPT = """You are an expert academic quiz question generator. Generate {num_questions} multiple choice questions from the provided learning material.

You MUST return your response as a JSON array ONLY (no other text):
[
  {{
    "type": "multichoice",
    "question": "Clear question text",
    "options": {{"A": "Option A text", "B": "Option B text", "C": "Option C text", "D": "Option D text"}},
    "correct_answer": "B",
    "explanation": "Brief explanation of why the correct answer is correct",
    "difficulty": "{difficulty}"
  }}
]

Rules:
- Questions must be based ONLY on the provided material
- Each question must have exactly 4 options (A, B, C, D)
- Exactly one option must be correct
- Distractors should be plausible but clearly incorrect
- Explanations must reference the source material
- Respond in the same language as the source material"""

QUIZ_TF_PROMPT = """You are an expert academic quiz question generator. Generate {num_questions} true/false questions from the provided learning material.

You MUST return your response as a JSON array ONLY (no other text):
[
  {{
    "type": "truefalse",
    "question": "A clear statement that is either true or false",
    "correct_answer": true,
    "explanation": "Brief explanation",
    "difficulty": "{difficulty}"
  }}
]

Rules:
- Statements must be clearly true or clearly false
- Avoid ambiguous or trick statements
- Mix true and false answers roughly equally
- Respond in the same language as the source material"""

QUIZ_ESSAY_PROMPT = """You are an expert academic quiz question generator. Generate {num_questions} essay questions from the provided learning material.

You MUST return your response as a JSON array ONLY (no other text):
[
  {{
    "type": "essay",
    "question": "Open-ended essay question text",
    "correct_answer": "",
    "explanation": "",
    "expected_answer_guidelines": "Key points the answer should cover",
    "rubric_hints": "Grading criteria hints",
    "difficulty": "{difficulty}"
  }}
]

Rules:
- Questions should require analysis, synthesis, or evaluation
- Questions must be answerable from the provided material
- Respond in the same language as the source material"""


class QuizService:
    """Handles AI quiz generation."""

    async def process(self, request: QuizRequest) -> QuizResponse:
        """Process a quiz generation request."""
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

        model = (
            request.options.model if request.options and request.options.model
            else settings.AI_DEFAULT_MODEL_QUIZ
        )
        temperature = (
            request.options.temperature if request.options and request.options.temperature is not None
            else settings.AI_QUIZ_TEMPERATURE
        )
        max_tokens = (
            request.options.max_tokens if request.options and request.options.max_tokens
            else settings.AI_QUIZ_MAX_TOKENS
        )
        source_text = text_content[:settings.AI_MAX_INPUT_LENGTH]

        # Generate questions per type
        all_questions: list[GeneratedQuestion] = []
        type_counts: dict[str, int] = {}
        types = request.settings.question_types
        num = request.settings.num_questions
        difficulty = request.settings.difficulty

        # Distribute questions across types
        per_type = max(1, num // len(types))
        remainder = num - per_type * len(types)

        for i, qtype in enumerate(types):
            count = per_type + (1 if i < remainder else 0)
            prompt_template = self._get_prompt_template(qtype)
            prompt = prompt_template.format(
                num_questions=count, difficulty=difficulty
            )
            prompt += f"\n\nMaterial Title: {request.material.filename}\n\nMaterial Content:\n{source_text}"

            logger.info("quiz_inference_start", model=model, qtype=qtype,
                         count=count, user_id=request.user_id,
                         source_length=len(text_content), prompt_material_length=len(source_text))

            try:
                async with httpx.AsyncClient(timeout=settings.AI_TIMEOUT_QUIZ) as client:
                    resp = await client.post(
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
                    resp.raise_for_status()
                    result = resp.json()
            except httpx.TimeoutException as exc:
                logger.warning(
                    "quiz_ollama_timeout",
                    model=model,
                    qtype=qtype,
                    timeout=settings.AI_TIMEOUT_QUIZ,
                    user_id=request.user_id,
                )
                raise HTTPException(
                    status_code=504,
                    detail={
                        "success": False,
                        "error": {
                            "code": "OLLAMA_TIMEOUT",
                            "message": (
                                "Ollama timed out while generating quiz questions. "
                                "Try fewer questions, select fewer question types, or use a smaller/faster model."
                            ),
                        },
                    },
                ) from exc
            except httpx.HTTPError as exc:
                logger.warning(
                    "quiz_ollama_request_failed",
                    model=model,
                    qtype=qtype,
                    error=str(exc),
                    user_id=request.user_id,
                )
                raise HTTPException(
                    status_code=502,
                    detail={
                        "success": False,
                        "error": {
                            "code": "OLLAMA_REQUEST_FAILED",
                            "message": f"Ollama request failed: {exc}",
                        },
                    },
                ) from exc

            raw = result.get("response", "")
            parsed_questions = self._parse_questions(raw, qtype)
            all_questions.extend(parsed_questions)
            type_counts[qtype] = len(parsed_questions)

        if not all_questions:
            raise HTTPException(
                status_code=422,
                detail={
                    "success": False,
                    "error": {
                        "code": "QUIZ_PARSE_EMPTY",
                        "message": (
                            "The AI model returned no valid quiz questions. "
                            "Try fewer questions, use another question type, or regenerate with clearer source material."
                        ),
                    },
                },
            )

        elapsed = time.time() - start_time

        logger.info("quiz_inference_complete", model=model,
                     total_generated=len(all_questions), duration_s=round(elapsed, 2))

        return QuizResponse(
            success=True,
            data=QuizData(
                questions=all_questions,
                metadata=QuizMetadata(
                    model=model,
                    total_generated=len(all_questions),
                    question_types_generated=type_counts,
                    tokens={"total": 0},
                    generation_time_seconds=round(elapsed, 2),
                ),
            ),
            meta={
                "request_id": request_id,
                "timestamp": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            },
        )

    def _get_prompt_template(self, qtype: str) -> str:
        """Get the prompt template for a question type."""
        templates = {
            "multichoice": QUIZ_MCQ_PROMPT,
            "truefalse": QUIZ_TF_PROMPT,
            "essay": QUIZ_ESSAY_PROMPT,
        }
        return templates.get(qtype, QUIZ_MCQ_PROMPT)

    def _parse_questions(self, raw: str, qtype: str) -> list[GeneratedQuestion]:
        """Parse generated questions from LLM output."""
        # Try direct JSON parse
        questions_data = None
        try:
            questions_data = json.loads(raw)
        except json.JSONDecodeError:
            pass

        # Try extracting JSON array from text
        if questions_data is None:
            match = re.search(r"\[.*\]", raw, re.DOTALL)
            if match:
                try:
                    questions_data = json.loads(match.group(0))
                except json.JSONDecodeError:
                    pass

        if not questions_data or not isinstance(questions_data, list):
            logger.warning("quiz_parse_failed", qtype=qtype, raw_length=len(raw))
            return []

        questions = []
        for q in questions_data:
            try:
                questions.append(GeneratedQuestion(
                    type=q.get("type", qtype),
                    question=q.get("question", ""),
                    options=q.get("options"),
                    correct_answer=q.get("correct_answer", ""),
                    explanation=q.get("explanation", ""),
                    difficulty=q.get("difficulty", "medium"),
                    expected_answer_guidelines=q.get("expected_answer_guidelines"),
                    rubric_hints=q.get("rubric_hints"),
                ))
            except Exception as e:
                logger.warning("quiz_question_parse_error", error=str(e))
                continue

        return questions
