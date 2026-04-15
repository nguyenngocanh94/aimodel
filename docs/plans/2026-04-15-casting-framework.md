# Node Framework: casting

> Part of AiModel-645.9 — Research node frameworks for vibe-controlled AI workflow design

## 1. Purpose & Pipeline Position

Casting sits after the story human gate and before shot-compiler. Like a real casting director, this node reads what the story needs and finds the right virtual actor from the persona library.

```
story-writer / beat-planner / mood-sequencer
    ↓
[HUMAN GATE: approve story]
    ↓
casting ← THIS NODE: finds virtual model that matches the story's character
    ↓
shot-compiler
    ↓
prompt-enhancer
    ↓
VIDEO GENERATION API
```

**Purpose:** Read the `character_implied` from the story moments, search the persona library for a matching virtual model, and output a locked cast identity that shot-compiler references in every scene.

**Why it's a separate node:** In the Cocoon experiment, character identity was embedded as `continuity_tokens` inside beat-planner-shot-compiler — an afterthought. In reality, casting is a distinct creative decision: WHO appears in the video affects everything visual. A persona's appearance, wardrobe style, environment preferences, and brand guidelines all constrain what shot-compiler can do.

**The existing codebase has:**
- 3 virtual personas in `resources/personas/` (Linh Vu, An Dau Day, Be May) with detailed YAML profiles
- A `CastSelection` interface in story-writer with `modelId`, `role`, `characterDescription`, `wardrobe`, `makeup`
- Skills for persona management (`model-manager.md`, `persona-manager.md`)

**What's missing:** A queryable persona library (currently YAML files only), matching logic, and the casting node itself. See bead for persona library infrastructure.

## 2. Input Schema

### From upstream: story/beat/mood moments (human-approved)

```json
{
  "character_implied": {
    "description": "string — e.g. '22yo Vietnamese girl, oily skin, lazy about skincare, funny personality'",
    "age_range": "string — e.g. '18-25'",
    "gender": "string",
    "personality_traits": ["string"],
    "context_hints": ["string — e.g. 'school setting', 'skincare desk', 'bedroom at night'"]
  },
  "moments": [
    {
      "moment_id": "string",
      "key_content": "string",
      "product_role": "string",
      "creative_space": "string"
    }
  ]
}
```

### From upstream: intent-outcome-selector

```json
{
  "intent_pack": {
    "audience_context": "string — who is watching, informs who should be on screen",
    "creative_angle": "string"
  }
}
```

### From planner: vibe config

```json
{
  "vibe_config": {
    "visual_polish_level": "raw_phone | natural_clean | polished_minimal",
    "casting_mode": "library_match | library_or_create | any"
  }
}
```

### From persona library

```json
{
  "available_personas": [
    {
      "persona_id": "string",
      "name": "string",
      "age": "string",
      "appearance_dna": "string — textual description of visual identity",
      "signature_looks": ["string"],
      "color_palette": ["string"],
      "content_niche": "string",
      "brand_guidelines": {
        "suitable_categories": ["string"],
        "avoid_categories": ["string"]
      },
      "reference_images": ["string — URLs or paths to reference photos/renders"],
      "environment_preferences": ["string"]
    }
  ]
}
```

## 3. Output Schema

### When match found:

```json
{
  "cast": {
    "matched": true,
    "persona_id": "string",
    "persona_name": "string",
    "match_confidence": "high | medium | low",
    "match_reasoning": "string — why this persona fits the story's character",
    "subject_profile": "string — locked visual description from persona",
    "wardrobe": "string — selected from persona's signature looks, adapted to story context",
    "environment": "string — from story context_hints + persona environment preferences",
    "lighting": "string — derived from vibe visual_polish_level",
    "product_identity_lock": ["string — physical product traits from grounding"],
    "props": ["string — from story moments"],
    "reference_images": ["string — persona's reference images for video API face/style lock"],
    "continuity_rules": [
      "string — e.g. 'same hair style in all clips', 'same wardrobe throughout'"
    ]
  }
}
```

### When no match found (human gate):

```json
{
  "cast": {
    "matched": false,
    "character_needed": {
      "description": "string — what the story needs",
      "age_range": "string",
      "personality": "string",
      "visual_requirements": ["string"],
      "story_context": "string"
    },
    "closest_personas": [
      {
        "persona_id": "string",
        "persona_name": "string",
        "why_not_perfect": "string"
      }
    ],
    "human_gate": {
      "message": "No persona matches the story's character. Here's what we need:",
      "options": [
        "Pick a close match and adapt the story slightly",
        "Create a new persona for this character",
        "Provide reference images for a one-time character"
      ]
    }
  }
}
```

## 4. Config Knobs

| Knob | Type | What it controls |
|------|------|-----------------|
| `visual_polish_level` | enum | Affects environment and lighting selection. `raw_phone` → messy room, phone flash. `natural_clean` → tidy desk, ring light. `polished_minimal` → styled set, soft lighting. |
| `casting_mode` | enum | `library_match` — only pick from existing personas, fail if none match. `library_or_create` — try library first, prompt human to create if no match. `any` — accept any character, skip library matching. |
| `match_strictness` | enum | `strict` — persona must match age, gender, niche, and personality. `flexible` — match on age and gender, adapt rest. `vibe_only` — as long as the persona fits the vibe, details can differ. |

## 5. Vibe Impact Classification

**Classification: vibe-neutral to borderline vibe-critical.**

The casting choice doesn't change the story or the narrative. But it changes how the video FEELS visually. A polished persona in a styled environment feels different from a casual persona at a messy desk — even if they're delivering the same story.

The main vibe influence:
- `visual_polish_level` drives the environment and styling direction
- The persona's own brand guidelines may conflict with certain vibes (e.g., Be May is chibi/anime — won't work for raw authentic skincare)
- Reference images from the persona lock the visual identity for the entire video

## 6. The 4 Node Analysis Questions

### Q1: How can the node manipulate or normalize its output?

Two levers:
- **Matching logic** — how strictly the node matches story character to persona. Strict matching produces better fit but may fail to find anyone. Flexible matching always finds someone but the fit may be loose.
- **Adaptation** — when a persona is a partial match, the node adapts wardrobe and environment to fit the story context while keeping the persona's core visual identity. Linh Vu (fashion persona) cast in a skincare story might wear her signature style but in a bathroom setting instead of a fashion studio.

### Q2: Should the node manipulate its output, or preserve its direct input?

This node **selects and adapts**, not creates. It finds the best persona match and adapts their presentation to fit the story. It does not invent new characters (unless the human creates one through the gate). The persona's core identity (face, body type, age) stays locked — only wardrobe and environment adapt.

Rule: **select from library, adapt the context, lock the identity.**

### Q3: What data should the node extract?

- From `character_implied`: what the story needs visually
- From `audience_context`: who is watching (the cast should look like someone the audience relates to)
- From `moments`: what environments/props appear in the story
- From `persona library`: available personas with their visual DNA and brand guidelines
- From `grounding`: `visually_provable_details` for product_identity_lock

### Q4: How does this node's output impact downstream?

| Downstream node | What cast controls |
|---|---|
| shot-compiler | Every scene references the cast — subject, wardrobe, environment, lighting. Cast is the visual consistency anchor. |
| prompt-enhancer | Reference images from cast enable face-locking on providers that support it (Kling, etc.) |
| edit-audio-caption-finalizer | Persona voice/tone guidelines may influence VO direction |

## 7. Anti-patterns

### AP1: Character identity was an afterthought (Cocoon experiment)

In the Cocoon experiment, `continuity_tokens` were generated inside beat-planner-shot-compiler as a side output. "Young Vietnamese woman, 20-26, hair clipped back, white t-shirt" — a generic description with no reference to any specific persona. Result: each generated video clip could render a different-looking person because there was no reference image lock.

**Fix:** Casting is a dedicated step with persona matching and reference images. Shot-compiler and prompt-enhancer use these references for consistency.

### AP2: No persona library to draw from

The Cocoon experiment invented a character from scratch every time. No library, no reuse, no consistency across videos.

**Fix:** Persona library with searchable profiles. When the same persona appears in multiple videos, they look the same because the reference images are the same.

### AP3: No fallback for missing characters

If the story needs a character that doesn't exist in the library, the Cocoon experiment just described a generic person. No mechanism to create a new persona or ask the human for help.

**Fix:** Human gate when no match found. Show what's needed, show closest options, offer to create new persona or adapt existing one.

## 8. Contrasting Behavior Examples

### Vibe: funny genz storytelling + Cocoon serum

Story character_implied: "22yo Vietnamese girl, oily skin, lazy about skincare, funny personality, school/dorm context"

Casting searches library → finds Linh Vu (fashion, 22yo, photorealistic) — partial match on age/gender but niche is fashion not skincare, personality is stylish not lazy.

```json
{
  "matched": true,
  "match_confidence": "medium",
  "match_reasoning": "Age and gender match. Niche (fashion) differs from story context (skincare/school). Adapting: keep Linh Vu's face and core appearance, swap wardrobe to casual student look, environment to dorm/bedroom desk.",
  "wardrobe": "Oversized vintage tee, hair messy bun — adapted from Linh Vu's casual subcollection",
  "environment": "Small dorm desk, ring light, scattered skincare products — matches story school context"
}
```

### Vibe: aesthetic mood + Cocoon serum

Story character_implied: "25yo woman, minimalist aesthetic, calm presence, night routine energy"

Casting searches library → Linh Vu is closest but her energy is more vibrant/fashion than calm/minimal.

```json
{
  "matched": false,
  "character_needed": {
    "description": "25yo woman with calm, minimalist presence. Think soft lighting, clean skin, quiet energy.",
    "visual_requirements": ["natural makeup or none", "simple wardrobe", "serene expression"]
  },
  "closest_personas": [
    {
      "persona_name": "Linh Vu",
      "why_not_perfect": "Right age but personality is vibrant/stylish, story needs calm/minimal"
    }
  ],
  "human_gate": {
    "message": "No persona matches the calm minimalist character. Create a new persona or adapt Linh Vu?",
    "options": ["Adapt Linh Vu (tone down styling, calm wardrobe)", "Create new persona", "Provide reference images"]
  }
}
```

## 9. Agent-friendly Config Guide

### `casting_mode` — Where to find the cast?

- `library_match` — only use existing personas. Fail with human gate if none match. Best when you have a mature library and want brand consistency.
- `library_or_create` — try library first, if no match ask human to create or provide references. Default for most workflows.
- `any` — skip library matching. Generate a character description from the story and pass it to shot-compiler directly (no reference images). Fast but no cross-video consistency.

### `match_strictness` — How close does the persona need to be?

- `strict` — age, gender, niche, personality must all match. Few results but high quality fit.
- `flexible` — age and gender match, adapt everything else. More results, may need wardrobe/environment adaptation.
- `vibe_only` — as long as the persona's energy fits the vibe, details can differ. Most permissive.

For early library (few personas): use `flexible`. For mature library (many personas): use `strict`.

## 10. Vibe State Contract

### Reads

| Vibe dimension | Source | How this node uses it |
|---|---|---|
| `character_implied` | story-writer/beat-planner/mood-sequencer | What character the story needs |
| `audience_context` | intent-outcome-selector | Who is watching — cast should be relatable |
| `story_moments` | story-writer/beat-planner/mood-sequencer | What environments/props appear |
| `visual_polish_level` | planner config | Drives environment and lighting styling |
| `format_archetype` | format-library-matcher | Skit vs explainer vs ASMR affects casting needs |

### Writes

| Vibe dimension | What it sets | Who reads it downstream |
|---|---|---|
| `cast_identity` | Locked visual identity — subject, wardrobe, environment, lighting | shot-compiler (every scene), prompt-enhancer (reference images) |
| `reference_images` | Persona's reference photos for face/style locking | prompt-enhancer (API face-lock feature) |
| `continuity_rules` | What must stay consistent across all clips | shot-compiler, prompt-enhancer |
| `product_identity_lock` | Physical product traits that must stay consistent | shot-compiler, prompt-enhancer |

### Contract rule

Once casting outputs a cast identity, every downstream node must reference it. No scene can introduce a different-looking person, change the wardrobe mid-video, or switch environments without the story explicitly calling for it. The cast identity is the visual consistency anchor for the entire video.

---

**Reference artifacts:**
- Existing personas: `resources/personas/registry.yaml`, `resources/personas/linh-vu.yaml`, `resources/personas/an-dau-day.yaml`, `resources/personas/be-may.yaml`
- Existing CastSelection interface: `frontend/src/features/node-registry/templates/story-writer.ts`
- Skills: `skills/model-manager.md`, `skills/persona-manager.md`
- Continuity tokens example: `test_prompts/beat_planner_shot_prompt_complier.json` (continuity_tokens section)
