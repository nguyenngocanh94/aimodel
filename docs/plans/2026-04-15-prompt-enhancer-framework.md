# Node Framework: prompt-enhancer

> Part of AiModel-645.9 — Research node frameworks for vibe-controlled AI workflow design

## 1. Purpose & Pipeline Position

Prompt-enhancer sits after shot-compiler and directly before the video generation API call. It is the **last node before video generation**.

```
shot-compiler → neutral clip specs
    ↓
prompt-enhancer ← THIS NODE: translates to API-specific prompts
    ↓
VIDEO GENERATION API (Runway / Kling / Hailuo / Sora)
    ↓
assembly
    ↓
edit-audio-caption-finalizer
```

**Purpose:** Take provider-neutral clip specs from shot-compiler and translate them into optimized prompts for the specific video generation API being used. Each provider has different prompt patterns, strengths, weaknesses, and syntax. This node knows the target provider's quirks and writes prompts that produce the best results on that specific platform.

**Why it exists as a separate node:** Shot-compiler makes creative decisions (what to show, how to frame). Prompt-enhancer makes technical decisions (how to ask this specific API for what we want). Keeping them separate means:
- Switching providers only changes one node, not the whole pipeline
- Shot-compiler's output stays stable and human-readable regardless of provider
- You can A/B test providers on the same clip specs
- When a new provider launches, you write one adapter — everything upstream is untouched

## 2. Input Schema

### From upstream: shot-compiler

```json
{
  "clip_pack": {
    "render_settings": {
      "aspect_ratio": "9:16",
      "resolution": "1080x1920",
      "fps": 24,
      "duration_rule_sec": "<6",
      "num_variants_per_clip": 2
    },
    "global_consistency": {
      "subject_lock": "string",
      "wardrobe_lock": "string",
      "environment_lock": "string",
      "lighting_lock": "string",
      "product_lock": "string"
    },
    "negative_prompt_en": "string",
    "clips": [
      {
        "clip_id": "string",
        "duration_sec": 0,
        "camera": "string",
        "camera_move": "string",
        "action": "string",
        "prompt_en": "string — neutral scene description",
        "continuity_tags": ["string"],
        "safety_notes": ["string"]
      }
    ],
    "qc_gate": { ... }
  }
}
```

### From planner: vibe config

```json
{
  "vibe_config": {
    "video_provider": "runway | kling | hailuo | sora",
    "generation_quality": "draft | standard | high",
    "reference_image_mode": "none | face_lock | style_reference | both"
  }
}
```

## 3. Output Schema

```json
{
  "api_ready_clips": [
    {
      "clip_id": "string",
      "provider": "runway | kling | hailuo | sora",
      "api_prompt": "string — provider-optimized prompt",
      "api_negative_prompt": "string — provider-specific negative prompt",
      "api_params": {
        "duration_sec": 0,
        "aspect_ratio": "string",
        "resolution": "string",
        "fps": 0,
        "motion_intensity": "string|null",
        "camera_control": "string|null",
        "reference_image_url": "string|null",
        "seed": "int|null"
      },
      "num_variants": 2,
      "source_clip_spec": {
        "clip_id": "string",
        "prompt_en": "string — original neutral prompt for reference"
      }
    }
  ],
  "qc_gate": {
    "must_pass": ["string"],
    "reject_if": ["string"]
  }
}
```

The `source_clip_spec.prompt_en` preserves the original neutral description for human debugging. If the generated video looks wrong, you can compare the neutral prompt vs the API prompt to see if the translation was the problem.

## 4. Config Knobs

| Knob | Type | What it controls |
|------|------|-----------------|
| `video_provider` | enum | Which API to target. Determines which prompt translation rules to use. |
| `generation_quality` | enum | `draft` (fast, lower quality, for testing), `standard` (good balance), `high` (best quality, slower, more expensive) |
| `reference_image_mode` | enum | `none` (text-only prompts), `face_lock` (attach reference face for consistency), `style_reference` (attach style image), `both` |
| `provider_prompt_version` | string (default "latest") | Which version of prompt rules to use for this provider. Providers update their models — prompt techniques that worked last month may not work today. |

## 5. Vibe Impact Classification

**Classification: vibe-neutral.** This node does not make creative decisions. It translates creative decisions into technical prompts. The vibe was decided upstream. This node's job is to faithfully reproduce the shot-compiler's intent on the target API.

However, **prompt quality directly affects video quality.** A bad translation produces ugly video even from a perfect clip spec. So while this node is vibe-neutral in terms of creative control, it is quality-critical in terms of output.

## 6. Provider-specific Knowledge

Each provider adapter needs to know:

### Prompt patterns
- What sentence structure produces best results
- Whether to use cinematic language, simple descriptions, or keyword-style prompts
- How to describe camera movements in this provider's language
- Maximum prompt length before quality degrades

### Strengths and weaknesses
- What this provider renders well (e.g., close-ups, slow motion, nature)
- What it struggles with (e.g., hands, faces, text, multiple people)
- When to simplify the prompt vs when to add detail

### Reference images
- Whether the provider supports face/style reference
- How to format reference image inputs
- Whether reference images help or hurt for different shot types

### Negative prompts
- Provider-specific negative prompt patterns
- What artifacts this provider commonly produces (and how to suppress them)

### Technical params
- Supported resolutions, FPS, durations
- Motion control syntax (if supported)
- Seed handling for reproducibility

## 7. Example: Same Clip, Two Providers

**Neutral clip spec from shot-compiler:**
```
"Medium close-up of young Vietnamese woman holding amber glass dropper bottle 
with skeptical expression, hair clipped back, plain white t-shirt, warm ring 
light, minimalist skincare desk, 9:16 vertical"
```

**Prompt-enhancer for Runway:**
```
"Cinematic medium close-up portrait, young Vietnamese woman in her early 20s, 
hair neatly clipped back, wearing a plain white crew-neck tee. She holds a 
small amber glass dropper bottle at chest height, expression subtly skeptical, 
one eyebrow slightly raised. Warm key light from ring light camera-left, soft 
fill, shallow depth of field. Background: minimal cream-toned vanity desk, 
slightly out of focus. Shot on 35mm, vertical 9:16 composition. Natural skin 
texture, no heavy makeup, no filter."
```

**Prompt-enhancer for Kling:**
```
"A young Vietnamese woman holds a small amber glass bottle, looking at it 
skeptically. White t-shirt, warm indoor lighting, clean desk background. 
Close-up portrait, vertical format."
+ reference_image: face_lock_image.jpg
+ motion: minimal, static hold
```

Different prompt styles, same creative intent. Runway wants cinematic detail. Kling wants brevity + reference image. The shot-compiler didn't need to know this — prompt-enhancer handled it.

## 8. Anti-patterns

### AP1: Prompt too generic
Writing the same generic prompt for all providers. Each provider has sweet spots — exploiting them produces much better video.

### AP2: Prompt too long for provider
Some providers degrade with long prompts. Prompt-enhancer must know the sweet spot length per provider.

### AP3: Not using reference images when available
If the provider supports face/style locking and we have reference images from casting, not using them wastes consistency potential.

## 9. Vibe State Contract

### Reads
| Vibe dimension | Source | How this node uses it |
|---|---|---|
| `clip_pack` | shot-compiler | The neutral specs to translate |
| `global_consistency` | shot-compiler | Consistency rules to encode in prompts |
| `cast` | casting (via shot-compiler) | Reference images if available |

### Writes
| Vibe dimension | What it sets | Who reads it downstream |
|---|---|---|
| `api_ready_clips` | Provider-specific prompts ready for API call | Video generation API |
| `qc_gate` | Provider-specific quality checks | Assembly step (reject bad clips) |

---

**Reference artifacts:**
- Original render spec: `test_prompts/split_video.json` (prompt style was Runway-oriented)
- Shot-compiler framework: `docs/plans/2026-04-15-shot-compiler-framework.md`
