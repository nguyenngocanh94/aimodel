"""Abstract base classes for content generation adapters."""

from abc import ABC, abstractmethod

from kol_creator.models.content import ContentResult


class ImageAdapter(ABC):
    """Abstract base class for image generation adapters."""

    @abstractmethod
    async def generate(
        self,
        prompt: str,
        reference_images: list[str] | None = None,
        options: dict | None = None,
    ) -> ContentResult:
        """Generate an image from a prompt.

        Args:
            prompt: The image generation prompt.
            reference_images: Optional list of reference image paths/URLs.
            options: Optional provider-specific options.

        Returns:
            ContentResult with the generation output.
        """

    @abstractmethod
    def name(self) -> str:
        """Return the name of this adapter."""

    @abstractmethod
    def supports_reference_images(self) -> bool:
        """Return whether this adapter supports reference images."""


class VideoAdapter(ABC):
    """Abstract base class for video generation adapters."""

    @abstractmethod
    async def generate(
        self,
        prompt: str,
        reference_images: list[str] | None = None,
        options: dict | None = None,
    ) -> ContentResult:
        """Generate a video from a prompt.

        Args:
            prompt: The video generation prompt.
            reference_images: Optional list of reference image paths/URLs.
            options: Optional provider-specific options.

        Returns:
            ContentResult with the generation output.
        """

    @abstractmethod
    def name(self) -> str:
        """Return the name of this adapter."""

    @abstractmethod
    def max_duration_seconds(self) -> int:
        """Return the maximum video duration in seconds."""


class CLIToolAdapter(ABC):
    """Abstract base class for CLI tool adapters (e.g., Gemini CLI, Claude Code)."""

    @abstractmethod
    async def execute(self, command: str, args: list[str] | None = None) -> str:
        """Execute a CLI command.

        Args:
            command: The command to execute.
            args: Optional list of arguments.

        Returns:
            The command output as a string.
        """
