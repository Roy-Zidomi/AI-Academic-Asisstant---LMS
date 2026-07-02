"""AI Service — Chat Data Models (Pydantic)."""

from typing import Optional
from pydantic import BaseModel, Field


class CourseContext(BaseModel):
    """Course context for academic relevance."""
    course_id: Optional[int] = None
    course_name: Optional[str] = None
    course_topic: Optional[str] = None


class HistoryMessage(BaseModel):
    """Previous chat message."""
    role: str = Field(..., pattern="^(user|assistant)$")
    content: str


class ChatOptions(BaseModel):
    """Optional inference parameters."""
    model: Optional[str] = None
    temperature: Optional[float] = Field(None, ge=0.0, le=1.0)
    max_tokens: Optional[int] = Field(None, ge=100, le=4096)


class ChatRequest(BaseModel):
    """Chat request payload."""
    user_id: int = Field(..., gt=0)
    session_id: Optional[int] = Field(0, ge=0)
    course_context: Optional[CourseContext] = None
    message: str = Field(..., min_length=3, max_length=2000)
    history: Optional[list[HistoryMessage]] = Field(None, max_length=10)
    options: Optional[ChatOptions] = None


class TokenUsage(BaseModel):
    """Token usage statistics."""
    input: int = 0
    output: int = 0
    total: int = 0


class ChatResponseData(BaseModel):
    """Chat response data."""
    response: str
    model: str
    tokens: TokenUsage
    response_time_seconds: float


class ResponseMeta(BaseModel):
    """Response metadata."""
    request_id: str
    timestamp: str
    disclaimer: str = (
        "This response is AI-generated and may contain inaccuracies. "
        "Please verify important information with your lecturer or official sources."
    )


class ChatResponse(BaseModel):
    """Chat API response envelope."""
    success: bool = True
    data: ChatResponseData
    meta: ResponseMeta
