"""AI Service — Quiz Generation Endpoint."""

from fastapi import APIRouter, Depends
import structlog

from app.api.v1.dependencies import verify_api_key, check_rate_limit
from app.models.quiz import QuizRequest, QuizResponse
from app.services.quiz_service import QuizService

router = APIRouter()
logger = structlog.get_logger()


@router.post("/generate-quiz", response_model=QuizResponse, tags=["Quiz"])
async def generate_quiz(
    request: QuizRequest,
    _api_key: str = Depends(verify_api_key),
):
    """
    AI Quiz Generator — Generate quiz questions from course material.
    Supports multiple choice, true/false, and essay questions.
    """
    await check_rate_limit(request.user_id, "quiz")
    service = QuizService()
    result = await service.process(request)
    return result
