# Node Framework: beat-planner

> Part of AiModel-645.9 — Research node frameworks for vibe-controlled AI workflow design
> Split from original 645.9.9 (beat-planner-shot-compiler)
> Variant of story-writer — see story-writer-framework.md for shared patterns

## Overview

Beat-planner is selected by the planner when `vibe_mode` is `clean_education`, `how_to`, or any vibe that needs logical information structure rather than narrative.

**How it differs from story-writer:** Story-writer creates characters, conflict, and narrative arcs. Beat-planner creates logical sequences: hook → point → point → proof → close. The structure is informational, not narrative. The viewer stays because they're learning something useful, not because they want to see what happens next.

## Shared patterns with story-writer

The following are identical to story-writer and documented there:
- **Dual output** (human_script + moments) with same consistency rule
- **Conversational human gate** (pick / edit / prompt-back with conflict warnings)
- **Moments structure** (same JSON schema, different `purpose` values)
- **Human_script is source of truth** — moments re-derived if edited

## What's different

### Purpose values for moments

Story-writer uses: `hook | setup | tension | twist | payoff | closing`

Beat-planner uses: `hook | pain_point_connection | explain_key | explain_secondary | sensory_proof | closing`

### Config knobs

| Knob | Type | What it controls |
|------|------|-----------------|
| `information_depth` | enum | `surface` (1-2 key points, fast), `moderate` (2-3 points with explanation), `deep` (3-4 points with mechanism detail) |
| `proof_type` | enum | `visual_demo` (show texture/application), `data_highlight` (concentrations, stats), `both` |
| `explanation_style` | enum | `casual_friend` (like chatting), `ingredient_breakdown` (structured), `myth_vs_fact` (contrarian) |
| `beat_versions_for_human` | int (default 2) | How many versions for human selection |
| `max_moments` | int (default 7) | Maximum beats. Education tends to need more than story. |
| `target_duration_sec` | int (default 35) | Total duration target |
| `ending_type_preference` | enum | `call_to_action` (save/comment), `soft_summary`, `open_question` |

### Example config: clean ingredient education

```yaml
information_depth: moderate
proof_type: both
explanation_style: casual_friend
beat_versions_for_human: 2
max_moments: 6
target_duration_sec: 35
ending_type_preference: call_to_action
```

### Vibe impact

Beat-planner is **less vibe-critical** than story-writer. Educational content has lower drift risk because the structure is predictable and the viewer's expectation is clear: "teach me something." The main risk is producing content that feels like a product catalogue — `explanation_style` and the human gate prevent this.

### Anti-patterns from Cocoon experiment

The Cocoon experiment was effectively a beat-planner run (linear edu structure). The output was decent for clean education but was selected when the pipeline should have used story-writer instead. The anti-pattern was not in the beat structure itself — it was in using beats when the vibe demanded story.

**Fix:** The planner selects beat-planner only for education vibes. Story vibes get story-writer.

---

**Reference artifacts:**
- Original beat output: `test_prompts/beat_planner_shot_prompt_complier.json` (this IS what beat-planner would produce for clean_education vibe)
