"""AI Service — Chat Endpoint."""

from fastapi import APIRouter, Depends
import structlog

from app.api.v1.dependencies import verify_api_key, check_rate_limit
from app.models.chat import ChatRequest, ChatResponse
from app.services.chat_service import ChatService

router = APIRouter()
logger = structlog.get_logger()


@router.post("/chat", response_model=ChatResponse, tags=["Chat"])
async def chat(
    request: ChatRequest,
    _api_key: str = Depends(verify_api_key),
):
    """
    AI Academic Assistant — Chat endpoint.
    Accepts a student question and returns an AI-generated academic response.
    """
    await check_rate_limit(request.user_id, "chat")
    service = ChatService()
    result = await service.process(request)
    return result
