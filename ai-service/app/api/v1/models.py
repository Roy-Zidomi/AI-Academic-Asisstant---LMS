"""AI Service — Models Endpoint."""

from fastapi import APIRouter, Depends
import httpx
import structlog

from app.api.v1.dependencies import verify_api_key
from app.config import settings

router = APIRouter()
logger = structlog.get_logger()


@router.get("/models", tags=["Models"])
async def list_models(_api_key: str = Depends(verify_api_key)):
    """List available LLM models from Ollama."""
    try:
        async with httpx.AsyncClient(timeout=10.0) as client:
            resp = await client.get(f"{settings.OLLAMA_BASE_URL}/api/tags")
            resp.raise_for_status()
            data = resp.json()

            models = []
            for model in data.get("models", []):
                models.append({
                    "name": model.get("name", ""),
                    "size": model.get("size", 0),
                    "modified_at": model.get("modified_at", ""),
                    "digest": model.get("digest", "")[:12],
                })

            return {
                "success": True,
                "data": {"models": models},
            }
    except httpx.ConnectError:
        return {
            "success": False,
            "error": {
                "code": "AI_SERVICE_UNAVAILABLE",
                "message": "Cannot connect to Ollama. Ensure the service is running.",
            },
        }
    except Exception as e:
        logger.error("list_models_error", error=str(e))
        return {
            "success": False,
            "error": {
                "code": "INTERNAL_ERROR",
                "message": "Failed to retrieve model list.",
            },
        }
