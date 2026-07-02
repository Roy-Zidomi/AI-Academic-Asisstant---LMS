"""AI Service — Summary Endpoint."""

from fastapi import APIRouter, Depends
import structlog

from app.api.v1.dependencies import verify_api_key, check_rate_limit
from app.models.summary import SummaryRequest, SummaryResponse
from app.services.summary_service import SummaryService

router = APIRouter()
logger = structlog.get_logger()


@router.post("/summarize", response_model=SummaryResponse, tags=["Summary"])
async def summarize(
    request: SummaryRequest,
    _api_key: str = Depends(verify_api_key),
):
    """
    AI Material Summarizer — Generate a structured summary from course material.
    Supports PDF, PPTX, and plain text files.
    """
    await check_rate_limit(request.user_id, "summary")
    service = SummaryService()
    result = await service.process(request)
    return result
