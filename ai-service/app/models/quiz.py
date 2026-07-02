"""AI Service — Quiz Data Models (Pydantic)."""

from typing import Optional, Union
from pydantic import BaseModel, Field

from app.models.summary import MaterialInput


class QuizSettings(BaseModel):
    """Quiz generation settings."""
    question_types: list[str] = Field(
        ...,
        min_length=1,
        description="Question types to generate: multichoice, truefalse, essay",
    )
    num_questions: int = Field(..., ge=1, le=50)
    difficulty: str = Field(..., pattern="^(easy|medium|hard|mixed)$")


class QuizOptions(BaseModel):
    """Optional inference parameters."""
    model: Optional[str] = None
    temperature: Optional[float] = Field(None, ge=0.0, le=1.0)
    max_tokens: Optional[int] = Field(None, ge=100, le=8192)


class QuizRequest(BaseModel):
    """Quiz generation request payload."""
    user_id: int = Field(..., gt=0)
    course_id: int = Field(..., gt=0)
    material: MaterialInput
    settings: QuizSettings
    options: Optional[QuizOptions] = None


class GeneratedQuestion(BaseModel):
    """A single generated question."""
    type: str
    question: str
    options: Optional[dict[str, str]] = None   # MCQ options: {"A": "...", "B": "...", ...}
    correct_answer: Union[str, bool]            # "B" for MCQ, True/False for TF
    explanation: str
    difficulty: str
    # Essay-specific
    expected_answer_guidelines: Optional[str] = None
    rubric_hints: Optional[str] = None


class QuizMetadata(BaseModel):
    """Quiz generation metadata."""
    model: str
    total_generated: int
    question_types_generated: dict[str, int]
    tokens: dict
    generation_time_seconds: float


class QuizData(BaseModel):
    """Quiz generation result."""
    questions: list[GeneratedQuestion]
    metadata: QuizMetadata


class QuizResponse(BaseModel):
    """Quiz API response envelope."""
    success: bool = True
    data: QuizData
    meta: dict
