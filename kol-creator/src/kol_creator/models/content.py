"""Content request and result models."""

from enum import Enum
from pathlib import Path

from pydantic import BaseModel, Field


class ContentType(str, Enum):
    """Type of content to generate."""

    IMAGE = "image"
    VIDEO = "video"


class ContentRequest(BaseModel):
    """Request to generate content."""

    description: str = Field(description="Natural language description of what to generate")
    content_type: ContentType = Field(
        default=ContentType.IMAGE, description="Type of content to generate"
    )
    aspect_ratio: str = Field(default="16:9", description="Aspect ratio for the output")
    provider: str = Field(default="mock", description="Name of the adapter provider to use")


class ContentResult(BaseModel):
    """Result of content generation."""

    prompt_used: str = Field(description="The final prompt that was sent to the provider")
    provider: str = Field(description="Name of the provider that generated the content")
    output_path: Path = Field(description="Path to the generated output file")
    metadata: dict = Field(default_factory=dict, description="Additional metadata from the provider")
