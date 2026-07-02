"""
AI Service — Configuration Management
Loads settings from environment variables with sensible defaults.
"""

import logging
from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """Application settings loaded from environment variables."""

    # --- General ---
    ENVIRONMENT: str = "development"
    AI_LOG_LEVEL: str = "INFO"

    # --- AI Service ---
    AI_API_KEY: str = "ai_api_key_change_me_in_production"

    # --- Ollama ---
    OLLAMA_BASE_URL: str = "http://ollama:11434"
    AI_DEFAULT_MODEL_CHAT: str = "llama3"
    AI_DEFAULT_MODEL_SUMMARY: str = "llama3"
    AI_DEFAULT_MODEL_QUIZ: str = "llama3"

    # --- Database ---
    DATABASE_URL: str = "postgresql://moodle:moodle_secret_change_me@postgres:5432/moodle"

    # --- Redis ---
    REDIS_URL: str = "redis://:redis_secret_change_me@redis:6379/0"

    # --- Rate Limits ---
    AI_RATE_LIMIT_CHAT: int = 20
    AI_RATE_LIMIT_SUMMARY: int = 10
    AI_RATE_LIMIT_QUIZ: int = 5

    # --- Input Limits ---
    AI_MAX_INPUT_LENGTH: int = 2000
    AI_CHAT_HISTORY_LIMIT: int = 5

    # --- Inference Defaults ---
    AI_CHAT_TEMPERATURE: float = 0.7
    AI_CHAT_MAX_TOKENS: int = 2048
    AI_SUMMARY_TEMPERATURE: float = 0.5
    AI_SUMMARY_MAX_TOKENS: int = 4096
    AI_QUIZ_TEMPERATURE: float = 0.3
    AI_QUIZ_MAX_TOKENS: int = 4096

    # --- Timeouts (seconds) ---
    AI_TIMEOUT_CHAT: int = 120
    AI_TIMEOUT_SUMMARY: int = 300
    AI_TIMEOUT_QUIZ: int = 300

    @property
    def log_level_int(self) -> int:
        """Convert string log level to integer."""
        return getattr(logging, self.AI_LOG_LEVEL.upper(), logging.INFO)

    class Config:
        env_file = ".env"
        case_sensitive = True


# Singleton settings instance
settings = Settings()
