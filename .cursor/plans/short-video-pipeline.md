# Short-Form Video Pipeline Summary

Updated: 2026-04-13

## Purpose

This document captures the key architecture decisions from the planning conversation for an AI-powered short-form video generation system. The system is no longer framed as a KOC-only or product-only pipeline. It should support multiple video intents, with product-led content as an optional mode.

## Core Direction

- Build a native-feeling short-form video pipeline, not an ad generator by default.
- Support multiple intents: entertainment, relatability, education, aesthetic mood, storytelling, community-building, soft product support, and direct product introduction.
- Design for generation plus evaluation loops, not a single linear pass.
- Prioritize hook quality, continuity, edit grammar, and per-shot quality control.

## Recommended Pipeline

1. `Brief Ingest`
2. `Intent And Outcome Selector`
3. `Truth And Constraint Gate`
4. `Format Library Matcher`
5. `Hook And Angle Generator`
6. `Objective Fit And Native Lint`
7. `Beat Planner`
8. `Continuity Binder`
9. `Shot Prompt Compiler`
10. `Multi-Candidate Video Generation`
11. `Shot And Continuity QC`
12. `Edit Audio Caption Packaging`
13. `Whole Video Evaluator`
14. `Creative Learning Memory`

## Why The Earlier Product-Centric Framing Was Changed

- The system should not assume every video exists to sell or introduce a product.
- Product information may still be present, but it should be treated as optional context.
- The pipeline should first decide the objective of the video, then decide how much product or message should appear.

## Key Architectural Principles

- The first 1-3 seconds need an explicit hook strategy.
- Every downstream node should consume a grounded context sheet.
- The system should reject outputs that mismatch the chosen intent.
- Shot prompts must be written with current video-model limitations in mind.
- Rendering should produce multiple candidates per shot.
- Low-quality or inconsistent shots should be rejected before final assembly.
- Native short-form feel is created heavily in editing, captions, pacing, and audio.

## Truth And Constraint Gate

This replaced the narrower `Fact And Claim Gate`.

Its purpose is to create one grounded source of truth for the rest of the pipeline.

If the source includes products, claims, or regulated topics, it should output:

- hard facts
- allowed phrasing
- risky claims
- forbidden phrasing
- visually provable details

If the source is non-product content, it should output:

- story facts
- canon constraints
- taboo topics
- tone guardrails
- non-negotiable visual or narrative rules

## Vietnam Market Guidance

Target market: Vietnam.

This affects prompting in every node, not just final captions.

### Practical rules

- Do not treat localization as translation only.
- Separate three prompt layers:
  - creative thinking layer
  - render layer
  - audience-facing output layer
- The creative layer should be Vietnam-native in tone, situations, and cultural references.
- The render layer can still be English-first if the video model performs better in English.
- Final viewer-facing text, overlays, captions, and voice should be Vietnamese.

### Reusable market context object

```json
{
  "market": "vi-VN",
  "language": "Vietnamese",
  "dialect": "neutral",
  "tone": "casual, spoken, natural",
  "cultural_contexts": [
    "daily life",
    "work",
    "school",
    "home",
    "cafe",
    "hot weather",
    "commute"
  ],
  "avoid": [
    "translated English phrasing",
    "over-formal copy",
    "generic ad superlatives",
    "forced slang"
  ],
  "render_prompt_strategy": "English-first visual prompts, Vietnamese audience-facing text"
}
```

## Node Prompt Guidance

### `Brief And Context Analyser`

- Identify the platform, objective, topic, source assets, tone cues, constraints, and whether product or promotion is central, supporting, or absent.

### `Intent And Outcome Selector`

- Decide the primary video mode and desired viewer reaction.
- Example outcomes: stop, feel, learn, save, share, comment, click.

### `Truth And Constraint Gate`

- Preserve factual claims conservatively when claims exist.
- Otherwise output the story constraints and what must remain true.

### `Format Library Matcher`

- Rank formats by intent fit, native feel, production feasibility, and naturalness for the core idea.

### `Hook And Angle Generator`

- Open with tension, curiosity, contrast, humor, confession, or pattern interrupt.
- Only foreground a product or message when the objective requires it.

### `Objective Fit And Native Lint`

- Reject ad-like tone when the goal is story or relatability.
- Reject vague tone when the goal is explanation.
- Reject flat tone when the goal is entertainment.

### `Beat Planner`

- Each beat should introduce one new reason to keep watching.
- Avoid beats that simply restate the same point.

### `Shot Prompt Compiler`

- Prefer actions current video models can render reliably.
- Penalize fine text readability, dense crowds, complex manipulation, or precise lip sync dependence.

### `Edit Audio Caption Packaging`

- Captions should add subtext or emotion, not narrate everything already on screen.

## Character And Object Consistency

- Keep one primary subject focus per video unless the concept truly requires more.
- Use a continuity pack with fixed traits for subject, styling, environment, objects, and camera behavior.
- Reuse the same identity tokens or references across all shot prompts.
- Prefer short chained shots or reference-conditioned generation instead of fully independent renders.

## Important Failure Modes

- No clear viewer objective
- Weak or generic hook
- Tone mismatched to the intended format
- Overly polished or artificial-looking scenes
- Prompting actions the model cannot render well
- Cross-shot drift in subject, object, lighting, or setting
- Captions that over-explain
- Audio added too late, making the video feel assembled rather than authored

## Current Saved Plan

The current plan version is also available locally in:

- `/.cursor/plans/koc_video_pipeline_e8df1895.plan.md`

That plan was updated to reflect the broader short-form architecture, even though the filename still contains the earlier name.

