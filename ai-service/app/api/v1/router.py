"""
AI Service — API v1 Router Aggregation
Combines all v1 endpoint routers.
"""

from fastapi import APIRouter

from app.api.v1.chat import router as chat_router
from app.api.v1.summary import router as summary_router
from app.api.v1.quiz import router as quiz_router
from app.api.v1.models import router as models_router

api_v1_router = APIRouter(prefix="/api/v1", tags=["AI Service v1"])

api_v1_router.include_router(chat_router)
api_v1_router.include_router(summary_router)
api_v1_router.include_router(quiz_router)
api_v1_router.include_router(models_router)
