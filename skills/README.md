# AI Influencer Skills Library

Skills for automating the AI virtual influencer content production workflow.

## Skill Map

```
                    ┌──────────────────┐
                    │  persona-manager  │  ← Identity layer (all skills reference this)
                    └────────┬─────────┘
                             │
         ┌───────────────────┼───────────────────┐
         │                   │                   │
┌────────▼───────┐  ┌───────▼────────┐  ┌───────▼────────┐
│  scriptwriting  │  │ image-prompting │  │  audio-voice   │
│  (words)       │  │ (visuals)      │  │  (sound)       │
└────────┬───────┘  ├────────────────┤  └───────┬────────┘
         │          │ video-prompting │          │
         │          │ (motion)       │          │
         │          └────────┬───────┘          │
         │                   │                  │
         └───────────────────┼──────────────────┘
                             │
                    ┌────────▼─────────┐
                    │ content-pipeline  │  ← Orchestrator (chains all skills)
                    └────────┬─────────┘
                             │
                    ┌────────▼─────────┐
                    │marketing-strategy │  ← Growth layer (calendars, trends, localization)
                    └──────────────────┘
```

## Skills

| Skill | Role | Key Frameworks |
|-------|------|---------------|
| [persona-manager](persona-manager.md) | Identity & consistency | Persona Document (YAML), Textual DNA, Voice DNA |
| [image-prompting](image-prompting.md) | Still image generation | SAPELT framework, Textual DNA anchoring |
| [video-prompting](video-prompting.md) | Video clip generation | SAPELTC framework, camera vocabulary |
| [scriptwriting](scriptwriting.md) | Scripts & captions | HOOK framework, caption architecture |
| [audio-voice](audio-voice.md) | Voice & sound design | Voice DNA, TTS optimization, mixing guides |
| [content-pipeline](content-pipeline.md) | End-to-end orchestration | 7-stage pipeline, quality gates, handoff protocol |
| [marketing-strategy](marketing-strategy.md) | Growth & localization | Cultural adaptation, content pillars, trend mapping |

## Typical Workflow

1. **Setup:** `persona-manager` → Create Persona Document
2. **Plan:** `marketing-strategy` → Weekly content calendar
3. **Produce:** `content-pipeline` → Full production per content idea
4. **Iterate:** Review performance → adjust strategy → repeat
