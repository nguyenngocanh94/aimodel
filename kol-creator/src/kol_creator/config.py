"""Application configuration using Pydantic Settings."""

from pathlib import Path

from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """Application settings loaded from environment variables and .env file."""

    gemini_api_key: str = ""
    kling_api_key: str = ""
    openai_api_key: str = ""

    default_image_provider: str = "mock"
    default_video_provider: str = "mock"
    output_dir: Path = Path("output")

    model_config = {
        "env_file": ".env",
        "env_file_encoding": "utf-8",
    }


def get_settings() -> Settings:
    """Load and return application settings."""
    return Settings()
