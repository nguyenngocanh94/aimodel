# Node Framework: shot-compiler

> Part of AiModel-645.9 — Research node frameworks for vibe-controlled AI workflow design
> Split from original 645.9.9 (beat-planner-shot-compiler)

## 1. Purpose & Pipeline Position

Shot-compiler sits after casting and before native-edit-grammar. It receives the human-approved story/beats/mood moments AND the cast identity (virtual model, wardrobe, environment) and writes scene-by-scene production scripts.

```
story-writer / beat-planner / mood-sequencer
    ↓
ad-likeness-lint
    ↓
casting  ← selects virtual model, wardrobe, environment
    ↓
shot-compiler  ← THIS NODE: writes scene scripts referencing the cast
    ↓
native-edit-grammar
    ↓
render-spec-compiler
```

**Purpose:** Translate each moment into one or more concrete scenes with visual direction, camera behavior, voiceover text, on-screen text, and SFX hints — all referencing the specific cast identity. The output is the production script that render-spec-compiler converts into API-ready micro-clips.

**Key distinction from story/beat/mood nodes:** Those nodes decide WHAT happens (narrative). This node decides HOW it looks (production). The story says "character tries serum reluctantly." The shot-compiler says "medium close-up, she holds the bottle skeptically, dropper hovers over palm, ring light warm, cut to close-up of serum drops landing on skin."

**This node is the same regardless of which upstream variant was used.** Whether the moments came from story-writer, beat-planner, or mood-sequencer, shot-compiler reads the same `moments` schema and produces scene scripts.

## 2. Input Schema

### From upstream: story/beat/mood pack (human-approved)

```json
{
  "moments": [
    {
      "moment_id": "string",
      "purpose": "string",
      "emotional_direction": "string",
      "energy_level": "low | rising | peak | falling",
      "duration_target_sec": 0,
      "key_content": "string — what must happen",
      "product_role": "absent | background | natural | focal",
      "creative_space": "string — what is open to interpretation"
    }
  ],
  "narrative_arc": {
    "tension_curve": "string",
    "hook_payoff": "string",
    "ending_type": "string"
  }
}
```

### From upstream: casting

```json
{
  "cast": {
    "subject_profile": "string — age, appearance, personality feel",
    "wardrobe": "string",
    "environment": "string",
    "lighting": "string",
    "product_identity_lock": ["string — physical product traits that must stay consistent"],
    "props": ["string"]
  }
}
```

### From upstream: grounding

```json
{
  "grounding": {
    "allowed_phrasing_vi": [...],
    "forbidden_phrasing_vi": [...],
    "visually_provable_details": ["string"]
  }
}
```

### From planner: vibe config

```json
{
  "vibe_config": {
    "camera_style": "close_up_intimate | medium_casual | pov_first_person | mixed",
    "visual_polish_level": "raw_phone | natural_clean | polished_minimal",
    "transition_style": "hard_cut | jump_cut | smooth_zoom | none"
  }
}
```

## 3. Output Schema

```json
{
  "scene_pack": {
    "scenes": [
      {
        "scene_id": "string",
        "moment_id": "string — which moment this scene serves",
        "duration_sec": 0,
        "camera": "string — framing, angle, movement",
        "action": "string — what happens visually",
        "visual_prompt_en": "string — English prompt for video generation API",
        "voiceover_vi": "string|null — Vietnamese voiceover text if any",
        "on_screen_text_vi": "string|null — text overlays if any",
        "sfx_music_hint": "string — audio direction",
        "product_visibility": "absent | subtle_background | natural_use | hero_moment",
        "cast_reference": {
          "subject": "string — reference to cast.subject_profile",
          "wardrobe": "string — reference to cast.wardrobe",
          "environment": "string — reference to cast.environment"
        },
        "compliance_note": "string|null",
        "fallback_if_generation_fails": "string — simpler alternative"
      }
    ],
    "continuity_rules": [
      "string — rules that must hold across all scenes"
    ],
    "total_duration_sec": 0
  }
}
```

### Dual output

- **Human view:** The scenes read as a shot list — you can visualize the video scene by scene. "Scene 1: close-up of her holding the bottle, skeptical face. Scene 2: dropper releasing serum onto palm, macro shot."
- **Agent view:** Each scene has a concrete `visual_prompt_en` that render-spec-compiler can convert directly to API calls, plus `cast_reference` for continuity.

## 4. Config Knobs

| Knob | Type | What it controls |
|------|------|-----------------|
| `camera_style` | enum | `close_up_intimate` (tight framing, personal), `medium_casual` (waist-up, conversational), `pov_first_person` (viewer's perspective), `mixed` (varies per scene energy) |
| `visual_polish_level` | enum | `raw_phone` (imperfect, handheld, authentic), `natural_clean` (clean but not cinematic), `polished_minimal` (beautiful but restrained) |
| `transition_style` | enum | `hard_cut` (TikTok native), `jump_cut` (energy), `smooth_zoom` (aesthetic), `none` (continuous) |
| `scenes_per_moment` | int (default 1-2) | How many scenes per moment. More = more visual variety. Fewer = simpler production. |
| `text_overlay_density` | enum | `none`, `key_moments_only`, `throughout` | How much on-screen text |

### Example: funny storytelling

```yaml
camera_style: mixed
visual_polish_level: natural_clean
transition_style: jump_cut
scenes_per_moment: 1
text_overlay_density: key_moments_only
```

### Example: aesthetic mood

```yaml
camera_style: close_up_intimate
visual_polish_level: polished_minimal
transition_style: smooth_zoom
scenes_per_moment: 2
text_overlay_density: none
```

## 5. Vibe Impact Classification

**Classification: vibe-neutral to borderline vibe-critical.** Shot-compiler is primarily a translation layer — it converts narrative moments into visual specs. The creative decisions were made upstream (story/beats/mood + casting). However, camera style, polish level, and transition style affect how the video *feels*, so the planner controls these through vibe config.

The main risk: over-polishing. If `visual_polish_level` is too high, the video feels produced/commercial even if the story is authentic. The planner should match polish to vibe: raw vibes get `raw_phone`, aesthetic vibes get `polished_minimal`.

## 6. The 4 Node Analysis Questions

### Q1: How can the node manipulate or normalize its output?

Three levers:
- **Camera choices** — how each moment is framed visually. Same moment ("she tries the serum") looks very different as a close-up vs POV vs medium shot.
- **Scene splitting** — whether a moment becomes 1 scene or 2. Splitting adds visual variety and pacing options for native-edit-grammar.
- **Visual prompt writing** — the English prompt for video generation API. This is where the node's creative interpretation lives. The moment says "reluctant try, surprise." The visual prompt translates that into concrete visual direction.

### Q2: Should the node manipulate its output, or preserve its direct input?

This node **translates with creative interpretation**. It must faithfully serve the moment's `key_content` and `emotional_direction` but has freedom in HOW it visualizes them. It must use the cast identity exactly — same person, same wardrobe, same environment across all scenes.

Rule: **preserve the what (moments), interpret the how (visuals), lock the who (cast).**

### Q3: What data should the node extract from raw product information?

The node does not read the raw brief. It reads:
- From moments: what must happen per scene
- From cast: who and where
- From grounding: `visually_provable_details` (what can be shown on camera) and compliance notes
- From vibe_config: camera style, polish level, transitions

### Q4: How does this node's output impact downstream?

| Downstream node | What scenes control |
|---|---|
| native-edit-grammar | Reads scene durations, energy levels, transition hints to set edit rhythm |
| render-spec-compiler | Reads `visual_prompt_en`, duration, camera, cast_reference to create API-ready micro-clips |
| edit-audio-caption-finalizer | Reads `voiceover_vi`, `on_screen_text_vi`, `sfx_music_hint` for audio/caption packaging |

## 7. Anti-patterns from Cocoon experiment

### AP1: Shots over-specified, leaving no room for downstream creativity

The Cocoon shots included exact camera angles, exact text overlay content, exact SFX timing. This made render-spec-compiler a pure mechanical translator with no creative room. If the video model couldn't render the exact shot, the fallback was a static card.

**Fix:** `creative_space` field in moments gives shot-compiler room. And shot-compiler's `visual_prompt_en` is a direction, not a pixel-perfect specification.

### AP2: Shots were disconnected from any cast identity

The Cocoon experiment described "a young Vietnamese woman" in every shot independently. No shared cast reference. This led to potential continuity breaks in generated video — each clip could render a different-looking person.

**Fix:** Casting node upstream defines the identity once. Shot-compiler references `cast.subject_profile` in every scene. Render-spec-compiler uses this as a consistency lock.

## 8. Vibe State Contract

### Reads

| Vibe dimension | Source | How this node uses it |
|---|---|---|
| `story_moments` | story-writer / beat-planner / mood-sequencer | The narrative structure to visualize |
| `story_energy_curve` | story-writer / beat-planner / mood-sequencer | Energy level per moment → camera intensity |
| `character_implied` | story-writer / beat-planner / mood-sequencer | Who is in the story |
| `cast` | casting | Locked visual identity for the character |
| `format_archetype` | format-library-matcher | Visual style expectations (skit vs explainer vs ASMR) |

### Writes

| Vibe dimension | What it sets | Who reads it downstream |
|---|---|---|
| `scene_sequence` | Ordered scenes with visual specs | render-spec-compiler (API calls), native-edit-grammar (pacing) |
| `voiceover_text_sequence` | VO text per scene | edit-audio-caption-finalizer |
| `text_overlay_sequence` | On-screen text per scene | edit-audio-caption-finalizer |
| `audio_hints` | SFX and music direction per scene | edit-audio-caption-finalizer |
| `continuity_rules` | What must stay consistent across all scenes | render-spec-compiler (consistency enforcement) |

---

**Reference artifacts:**
- Original shot output: `test_prompts/beat_planner_shot_prompt_complier.json` (shots section)
- Render spec: `test_prompts/split_video.json` (how shots were translated to API clips)
