# Node Framework: mood-sequencer

> Part of AiModel-645.9 — Research node frameworks for vibe-controlled AI workflow design
> Split from original 645.9.9 (beat-planner-shot-compiler)
> Variant of story-writer — see story-writer-framework.md for shared patterns

## Overview

Mood-sequencer is selected by the planner when `vibe_mode` is `aesthetic_mood`, `asmr`, or any vibe that prioritizes sensory experience over narrative or information.

**How it differs from story-writer and beat-planner:** Minimal or no talking. The viewer stays because they feel something — calm, satisfaction, beauty — not because of a story or information. The output is a sensory flow: visual moment → visual moment → peak → fade. Words are almost absent; visual and audio direction dominate.

## Shared patterns with story-writer

The following are identical to story-writer and documented there:
- **Dual output** (human_script + moments) with same consistency rule
- **Conversational human gate** (pick / edit / prompt-back with conflict warnings)
- **Moments structure** (same JSON schema, different `purpose` values)
- **Human_script is source of truth** — moments re-derived if edited

Note: human_script for mood-sequencer is shorter (50-100 words) and reads more like a visual treatment than a narrative.

## What's different

### Purpose values for moments

Story-writer uses: `hook | setup | tension | twist | payoff | closing`

Mood-sequencer uses: `atmosphere_set | sensory_build | sensory_peak | sensory_proof | atmosphere_close`

### Config knobs

| Knob | Type | What it controls |
|------|------|-----------------|
| `sensory_focus` | enum | `texture` (product texture close-ups), `ritual` (the full routine moment), `environment` (the space around the product) |
| `audio_priority` | enum | `silence_room_tone` (almost no sound), `asmr_sounds` (product sounds amplified), `ambient_music` (lo-fi, chill, minimal) |
| `text_density` | enum | `none` (pure visual), `product_name_only` (end card), `minimal_poetic` (short evocative phrases) |
| `pacing` | enum | `slow_meditative` (long holds, gentle transitions), `rhythmic` (cut to music beat), `flowing` (smooth continuous motion) |
| `mood_versions_for_human` | int (default 2) | How many versions for human selection |
| `max_moments` | int (default 4) | Fewer moments — each is a visual beat, not a narrative beat |
| `target_duration_sec` | int (default 25) | Mood pieces tend to be shorter |

### Example config: aesthetic night routine

```yaml
sensory_focus: ritual
audio_priority: asmr_sounds
text_density: product_name_only
pacing: slow_meditative
mood_versions_for_human: 2
max_moments: 4
target_duration_sec: 25
```

### Vibe impact

Mood-sequencer is **vibe-critical in a different way** than story-writer. The risk is not ad-drift (there are barely words to sound ad-like). The risk is **aesthetic drift** — the mood fails to feel authentic, peaceful, or satisfying. Overly polished = feels commercial. Too raw = feels amateur. The sweet spot is "beautiful but attainable."

### Anti-patterns

The Cocoon experiment did not attempt an aesthetic mood output. But format F04 (Texture ASMR Edu) in the format-library-matcher output was the closest — it ranked 5th because the scoring penalized formats with low educational value. In the new design, mood-sequencer is selected by the planner when the vibe explicitly wants aesthetic, so it would not compete against edu formats.

---

**Reference artifacts:**
- Format F04 in `test_prompts/formated_library_matcher.json` (closest existing reference, ranked low because pipeline was edu-biased)
