"""KOL persona models."""

from pydantic import BaseModel, Field


class KOLPersona(BaseModel):
    """A KOL (Key Opinion Leader) persona definition."""

    name: str = Field(description="Display name of the KOL persona")
    description: str = Field(description="Short bio or description of the persona")
    textual_dna: str = Field(
        description="Textual DNA anchor for character consistency across generated images"
    )
    style_guide: str = Field(description="Fashion and aesthetic style guide for the persona")
    reference_images: list[str] = Field(
        default_factory=list,
        description="Paths or URLs to reference images for this persona",
    )


class KOLProfile(BaseModel):
    """A KOL profile combining persona with generated assets metadata."""

    persona: KOLPersona
    generated_assets: dict[str, list[str]] = Field(
        default_factory=dict,
        description="Mapping of asset type to list of generated asset paths",
    )
