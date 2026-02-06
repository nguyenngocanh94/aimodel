"""Adapter registry for mapping provider names to adapter instances."""

from kol_creator.adapters.base import ImageAdapter, VideoAdapter
from kol_creator.adapters.mock import MockImageAdapter, MockVideoAdapter


class AdapterRegistry:
    """Registry that maps provider names to adapter instances."""

    def __init__(self) -> None:
        self._image_adapters: dict[str, ImageAdapter] = {}
        self._video_adapters: dict[str, VideoAdapter] = {}
        self._register_defaults()

    def _register_defaults(self) -> None:
        """Register the built-in mock adapters."""
        self.register_image_adapter("mock", MockImageAdapter())
        self.register_video_adapter("mock", MockVideoAdapter())

    def register_image_adapter(self, name: str, adapter: ImageAdapter) -> None:
        """Register an image adapter under a provider name."""
        self._image_adapters[name] = adapter

    def register_video_adapter(self, name: str, adapter: VideoAdapter) -> None:
        """Register a video adapter under a provider name."""
        self._video_adapters[name] = adapter

    def get_image_adapter(self, name: str) -> ImageAdapter:
        """Get an image adapter by provider name.

        Raises:
            KeyError: If no adapter is registered under the given name.
        """
        if name not in self._image_adapters:
            available = ", ".join(self._image_adapters.keys()) or "none"
            raise KeyError(f"No image adapter registered for '{name}'. Available: {available}")
        return self._image_adapters[name]

    def get_video_adapter(self, name: str) -> VideoAdapter:
        """Get a video adapter by provider name.

        Raises:
            KeyError: If no adapter is registered under the given name.
        """
        if name not in self._video_adapters:
            available = ", ".join(self._video_adapters.keys()) or "none"
            raise KeyError(f"No video adapter registered for '{name}'. Available: {available}")
        return self._video_adapters[name]

    def list_image_adapters(self) -> list[str]:
        """List all registered image adapter names."""
        return list(self._image_adapters.keys())

    def list_video_adapters(self) -> list[str]:
        """List all registered video adapter names."""
        return list(self._video_adapters.keys())
