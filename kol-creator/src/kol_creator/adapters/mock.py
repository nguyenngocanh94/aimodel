"""Mock adapters for testing without API keys."""

import uuid
from datetime import datetime, timezone
from pathlib import Path

from kol_creator.adapters.base import ImageAdapter, VideoAdapter
from kol_creator.models.content import ContentResult


class MockImageAdapter(ImageAdapter):
    """Mock image adapter that returns fake successful results."""

    async def generate(
        self,
        prompt: str,
        reference_images: list[str] | None = None,
        options: dict | None = None,
    ) -> ContentResult:
        mock_id = uuid.uuid4().hex[:8]
        output_path = Path(f"output/images/mock_{mock_id}.png")

        return ContentResult(
            prompt_used=prompt,
            provider=self.name(),
            output_path=output_path,
            metadata={
                "mock": True,
                "generation_id": mock_id,
                "timestamp": datetime.now(timezone.utc).isoformat(),
                "resolution": "1024x1024",
                "reference_images_used": len(reference_images) if reference_images else 0,
                "options": options or {},
            },
        )

    def name(self) -> str:
        return "mock"

    def supports_reference_images(self) -> bool:
        return True


class MockVideoAdapter(VideoAdapter):
    """Mock video adapter that returns fake successful results."""

    async def generate(
        self,
        prompt: str,
        reference_images: list[str] | None = None,
        options: dict | None = None,
    ) -> ContentResult:
        mock_id = uuid.uuid4().hex[:8]
        output_path = Path(f"output/videos/mock_{mock_id}.mp4")

        return ContentResult(
            prompt_used=prompt,
            provider=self.name(),
            output_path=output_path,
            metadata={
                "mock": True,
                "generation_id": mock_id,
                "timestamp": datetime.now(timezone.utc).isoformat(),
                "resolution": "1920x1080",
                "duration_seconds": 5,
                "fps": 30,
                "reference_images_used": len(reference_images) if reference_images else 0,
                "options": options or {},
            },
        )

    def name(self) -> str:
        return "mock"

    def max_duration_seconds(self) -> int:
        return 10
