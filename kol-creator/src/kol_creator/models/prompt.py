"""S.A.P.E.L.T. prompt models."""

from pydantic import BaseModel, Field

from kol_creator.models.content import ContentType


class SAPELTPrompt(BaseModel):
    """Structured prompt following the S.A.P.E.L.T. framework."""

    subject: str = Field(description="The core subject with Textual DNA")
    action: str = Field(description="The pose, interaction, or activity")
    place: str = Field(description="The immediate location and props")
    environment: str = Field(description="The broader context, atmosphere, and background")
    lighting: str = Field(description="The specific lighting setup")
    technical: str = Field(description="Camera gear, film stock, resolution, artistic style")

    def to_prompt_string(self) -> str:
        """Combine all S.A.P.E.L.T. components into a single prompt string."""
        return f"{self.subject}, {self.action}, {self.place}. {self.environment}. {self.lighting}. {self.technical}."


class PromptRequest(BaseModel):
    """Request to generate an optimized prompt."""

    user_description: str = Field(description="Natural language description from the user")
    persona_name: str = Field(default="", description="Name of the KOL persona to use")
    target_provider: str = Field(default="mock", description="Target image/video provider")
    content_type: ContentType = Field(
        default=ContentType.IMAGE, description="Type of content to generate"
    )


class PromptResult(BaseModel):
    """Result of prompt generation."""

    optimized_prompt: str = Field(description="The final optimized prompt string")
    sapelt_breakdown: SAPELTPrompt = Field(description="The S.A.P.E.L.T. breakdown of the prompt")
    provider_specific_notes: str = Field(
        default="", description="Notes specific to the target provider"
    )
