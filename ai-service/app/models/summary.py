"""AI Service — Summary Data Models (Pydantic)."""

from typing import Optional
from pydantic import BaseModel, Field


class MaterialInput(BaseModel):
    """Material file input."""
    filename: str = Field(..., max_length=255)
    content_type: str = Field(..., pattern="^(application/pdf|application/vnd\\.openxmlformats-officedocument\\.presentationml\\.presentation|text/plain)$")
    content_base64: str


class SummaryOptions(BaseModel):
    """Optional inference parameters."""
    model: Optional[str] = None
    temperature: Optional[float] = Field(None, ge=0.0, le=1.0)
    max_tokens: Optional[int] = Field(None, ge=100, le=8192)
    language: Optional[str] = Field("auto", pattern="^(auto|en|id)$")


class SummaryRequest(BaseModel):
    """Summary request payload."""
    user_id: int = Field(..., gt=0)
    course_id: int = Field(..., gt=0)
    material: MaterialInput
    options: Optional[SummaryOptions] = None


class Concept(BaseModel):
    """Important concept with definition."""
    term: str
    definition: str


class GlossaryEntry(BaseModel):
    """Glossary term with definition."""
    term: str
    definition: str


class SummaryMetadata(BaseModel):
    """Summary generation metadata."""
    model: str
    tokens: dict
    generation_time_seconds: float
    source_characters: int = 0


class SummaryData(BaseModel):
    """Summary result data."""
    executive_summary: str
    key_points: list[str]
    important_concepts: list[Concept]
    glossary: list[GlossaryEntry]
    study_guide: str
    metadata: SummaryMetadata


class SummaryResponse(BaseModel):
    """Summary API response envelope."""
    success: bool = True
    data: SummaryData
    meta: dict
