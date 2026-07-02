"""
AI Academic Assistant Service — Main Application Entry Point
AI-AA LMS — FastAPI AI Middleware

This service provides AI capabilities for the Moodle-based LMS:
- AI Academic Chat (Q&A with students)
- AI Material Summarizer
- AI Quiz Generator
"""

from contextlib import asynccontextmanager
import structlog
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.config import settings
from app.api.v1.router import api_v1_router
from app.middleware.error_handler import register_error_handlers
from app.middleware.logging import RequestLoggingMiddleware

# Configure structured logging
structlog.configure(
    processors=[
        structlog.contextvars.merge_contextvars,
        structlog.processors.add_log_level,
        structlog.processors.StackInfoRenderer(),
        structlog.dev.set_exc_info,
        structlog.processors.TimeStamper(fmt="iso"),
        structlog.dev.ConsoleRenderer() if settings.ENVIRONMENT == "development"
        else structlog.processors.JSONRenderer(),
    ],
    wrapper_class=structlog.make_filtering_bound_logger(settings.log_level_int),
    context_class=dict,
    logger_factory=structlog.PrintLoggerFactory(),
    cache_logger_on_first_use=True,
)

logger = structlog.get_logger()


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application lifespan — startup and shutdown events."""
    # --- Startup ---
    logger.info(
        "ai_service_starting",
        environment=settings.ENVIRONMENT,
        version="1.0.0",
        ollama_url=settings.OLLAMA_BASE_URL,
    )
    yield
    # --- Shutdown ---
    logger.info("ai_service_shutting_down")


# Initialize FastAPI application
app = FastAPI(
    title="AI Academic Assistant Service",
    description=(
        "AI middleware service for AI-AA LMS. Provides academic chat, "
        "material summarization, and quiz generation via local LLM (Ollama)."
    ),
    version="1.0.0",
    docs_url="/docs" if settings.ENVIRONMENT == "development" else None,
    redoc_url="/redoc" if settings.ENVIRONMENT == "development" else None,
    lifespan=lifespan,
)

# --- Middleware ---
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"] if settings.ENVIRONMENT == "development" else [],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
app.add_middleware(RequestLoggingMiddleware)

# --- Error Handlers ---
register_error_handlers(app)

# --- Routes ---
app.include_router(api_v1_router)


@app.get("/health", tags=["System"])
async def health_check():
    """Service health check endpoint."""
    import httpx

    # Check Ollama connectivity
    ollama_status = "disconnected"
    ollama_error = None
    models_loaded = []
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            resp = await client.get(f"{settings.OLLAMA_BASE_URL}/api/tags")
            if resp.status_code == 200:
                ollama_status = "connected"
                data = resp.json()
                models_loaded = [m["name"] for m in data.get("models", [])]
    except Exception as e:
        ollama_error = str(e)

    # Check Redis connectivity
    redis_status = "disconnected"
    try:
        import redis as redis_lib
        r = redis_lib.from_url(settings.REDIS_URL, socket_timeout=3)
        r.ping()
        redis_status = "connected"
        r.close()
    except Exception:
        pass

    # Check PostgreSQL connectivity
    pg_status = "disconnected"
    try:
        import asyncpg
        conn = await asyncpg.connect(settings.DATABASE_URL, timeout=3)
        await conn.fetchval("SELECT 1")
        await conn.close()
        pg_status = "connected"
    except Exception:
        pass

    overall = "healthy" if all([
        ollama_status == "connected",
        redis_status == "connected",
        pg_status == "connected",
    ]) else "degraded"

    return {
        "status": overall,
        "version": "1.0.0",
        "environment": settings.ENVIRONMENT,
        "services": {
            "ollama": {
                "status": ollama_status,
                "url": settings.OLLAMA_BASE_URL,
                **({"error": ollama_error} if ollama_error else {}),
            },
            "postgresql": {"status": pg_status},
            "redis": {"status": redis_status},
        },
        "models_loaded": models_loaded,
    }
