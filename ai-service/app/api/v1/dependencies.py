"""AI Service — Shared API Dependencies (auth, rate limiting)."""

from fastapi import Header, HTTPException
import redis
import structlog

from app.config import settings

logger = structlog.get_logger()

# Redis client for rate limiting
_redis_client = None


def _get_redis():
    """Get or create Redis client singleton."""
    global _redis_client
    if _redis_client is None:
        try:
            _redis_client = redis.from_url(
                settings.REDIS_URL,
                decode_responses=True,
                socket_timeout=3,
            )
        except Exception as e:
            logger.warning("redis_connection_failed", error=str(e))
            return None
    return _redis_client


async def verify_api_key(x_api_key: str = Header(..., alias="X-API-Key")) -> str:
    """
    Validate the API key from the X-API-Key header.
    Raises 401 if invalid or missing.
    """
    if x_api_key != settings.AI_API_KEY:
        logger.warning("auth_invalid_api_key", provided_key=x_api_key[:8] + "...")
        raise HTTPException(
            status_code=401,
            detail={
                "success": False,
                "error": {
                    "code": "AUTH_INVALID_KEY",
                    "message": "Invalid or missing API key.",
                },
            },
        )
    return x_api_key


async def check_rate_limit(user_id: int, feature: str) -> None:
    """
    Check rate limit for a user on a specific feature.
    Uses Redis sliding window counter.
    Raises 429 if limit exceeded.
    """
    limits = {
        "chat": settings.AI_RATE_LIMIT_CHAT,
        "summary": settings.AI_RATE_LIMIT_SUMMARY,
        "quiz": settings.AI_RATE_LIMIT_QUIZ,
    }

    limit = limits.get(feature, 20)
    r = _get_redis()

    if r is None:
        # If Redis is down, allow request (fail-open for PoC)
        logger.warning("rate_limit_redis_unavailable", user_id=user_id, feature=feature)
        return

    key = f"ratelimit:{user_id}:{feature}"

    try:
        current = r.incr(key)
        if current == 1:
            r.expire(key, 3600)  # 1 hour window

        if current > limit:
            ttl = r.ttl(key)
            logger.info(
                "rate_limit_exceeded",
                user_id=user_id,
                feature=feature,
                current=current,
                limit=limit,
            )
            raise HTTPException(
                status_code=429,
                detail={
                    "success": False,
                    "error": {
                        "code": "RATE_LIMIT_EXCEEDED",
                        "message": f"Rate limit exceeded for {feature}. Maximum {limit} requests per hour.",
                    },
                },
                headers={
                    "X-RateLimit-Limit": str(limit),
                    "X-RateLimit-Remaining": "0",
                    "Retry-After": str(ttl if ttl > 0 else 3600),
                },
            )
    except HTTPException:
        raise
    except Exception as e:
        logger.warning("rate_limit_check_error", error=str(e), user_id=user_id)
        # Fail-open: allow request if Redis error
