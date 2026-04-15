# Node Framework: story-writer

> Part of AiModel-645.9 — Research node frameworks for vibe-controlled AI workflow design
> Split from original 645.9.9 (beat-planner-shot-compiler)

## 1. Purpose & Pipeline Position

Story-writer sits after the human gate (hook selection) and before ad-likeness-lint. It is selected by the planner when `vibe_mode` is `funny_storytelling`, `raw_authentic`, or any vibe that needs narrative structure.

This node is one of three variants the planner chooses between:
- **story-writer** — for narrative vibes (funny, raw, story-led)
- **beat-planner** — for logical vibes (education, how-to)
- **mood-sequencer** — for sensory vibes (aesthetic, ASMR, mood)

**Purpose:** Take the human-approved hook and write a short story that pays off the hook's promise, stays within the vibe, and contains the product naturally within the narrative. Output a human-readable script + structured moments for downstream nodes.

**Why it's critical:** This is where the video's soul is written. The hook gets the viewer to stay for 3 seconds. The story is why they stay for 30. In the Cocoon experiment, there was no story — just a sequence of product explanations. The viewer had no reason to keep watching beyond "maybe the next ingredient is interesting."

## 2. Input Schema

### From human gate: selected hook

```json
{
  "selected_hook": {
    "hook_text_vi": "string",
    "hook_tension_mechanism": "string",
    "hook_promise": "string",
    "hook_energy_level": "string"
  }
}
```

### From upstream: intent-outcome-selector

```json
{
  "intent_pack": {
    "creative_angle": "string",
    "audience_painpoint": "string",
    "audience_context": "string",
    "vibe_promise": "string"
  }
}
```

### From upstream: truth-constraint-gate

```json
{
  "grounding": {
    "allowed_phrasing_vi": [{ "intent": "string", "phrase": "string" }],
    "forbidden_phrasing_vi": ["string"],
    "visually_provable_details": ["string"]
  }
}
```

### From vibe state: format-library-matcher

```json
{
  "vibe_state": {
    "format_archetype": "string",
    "narrative_shape": "string",
    "product_role_in_format": "string"
  }
}
```

### From planner: vibe config

```json
{
  "vibe_config": {
    "story_tension_curve": "slow_build | fast_hit | rollercoaster",
    "product_appearance_moment": "early | middle | twist | end",
    "humor_density": "none | punchline_only | throughout",
    "story_versions_for_human": 2,
    "max_moments": 6,
    "target_duration_sec": 35,
    "ending_type_preference": "twist_reveal | emotional_beat | soft_loop | call_to_action"
  }
}
```

## 3. Output Schema

```json
{
  "story_pack": {
    "versions": [
      {
        "version_id": "string",
        "human_script": "string — readable narrative, 150-300 words, Vietnamese",
        "moments": [
          {
            "moment_id": "string",
            "purpose": "hook | setup | tension | twist | payoff | closing",
            "emotional_direction": "string",
            "energy_level": "low | rising | peak | falling",
            "duration_target_sec": 0,
            "key_content": "string — what must happen, not how",
            "product_role": "absent | background | natural | focal",
            "creative_space": "string — what downstream nodes are free to interpret"
          }
        ],
        "narrative_arc": {
          "tension_curve": "string",
          "hook_payoff": "string — how the story pays off the hook's promise",
          "ending_type": "twist_reveal | emotional_beat | soft_loop | call_to_action"
        }
      }
    ],
    "human_gate": {
      "instruction": "Read both scripts. Pick the one that feels like a video you'd watch. Edit or prompt to adjust.",
      "consistency_rule": "human_script is source of truth. If edited or re-prompted, moments are re-derived."
    }
  }
}
```

### Dual output: human view + agent view

- **human_script** — readable story, 150-300 words. Human reads this to feel the video's vibe, atmosphere, pacing. If it reads like an ad, you know before any video is generated.
- **moments** — structured extraction of the same story. Each moment has purpose, emotional direction, energy level, and creative space. Downstream nodes (casting, shot-compiler) read this for structure while having room to interpret creatively.

**Consistency rule:** human_script is the source of truth. moments is derived from it. If they conflict, human_script wins and moments are re-derived.

### Conversational human gate

The human gate is not just pick/edit. It supports **prompt-back**:

```
Node generates 2 versions
    ↓
Human reads both human_scripts
    ↓
Option 1: Pick one → done
Option 2: Pick one + edit text → done
Option 3: Prompt feedback → node regenerates
    ↓
"sound good, but put it in school context, 12th grade girl"
    ↓
Node regenerates (keeps core structure + vibe, applies direction)
    ↓
Human reads again → pick / edit / prompt again
    ↓
Loop until satisfied
```

**Conflict handling:** If the user's prompt conflicts with vibe config or grounding constraints, the node warns before acting. Example: user says "make the product cure acne" → node warns "grounding forbids cure language, will use 'ho tro cai thien mun' instead. Continue?" User has final say but knows what changed.

## 4. Config Knobs the Planner Can Set

All product-independent. Set once when the workflow is designed.

| Knob | Type | What it controls |
|------|------|-----------------|
| `story_tension_curve` | enum | `slow_build` — tension grows gradually. `fast_hit` — peaks in first third. `rollercoaster` — multiple peaks. |
| `product_appearance_moment` | enum | When product enters the story. `early`, `middle`, `twist` (product IS the surprise), `end` (product is resolution). |
| `humor_density` | enum | `none`, `punchline_only` (concentrated at one moment), `throughout` (spread across story). |
| `story_versions_for_human` | int (default 2) | How many story versions for human selection. |
| `max_moments` | int (default 6) | Maximum story moments. Short-form TikTok: 4-6. |
| `target_duration_sec` | int (default 35) | Total video target. Distributed across moments. |
| `ending_type_preference` | enum | `twist_reveal`, `emotional_beat`, `soft_loop`, `call_to_action`. |

### Example: funny genz storytelling

```yaml
story_tension_curve: fast_hit
product_appearance_moment: twist
humor_density: throughout
story_versions_for_human: 2
max_moments: 5
target_duration_sec: 30
ending_type_preference: twist_reveal
```

### Example: raw authentic

```yaml
story_tension_curve: slow_build
product_appearance_moment: middle
humor_density: none
story_versions_for_human: 2
max_moments: 5
target_duration_sec: 35
ending_type_preference: emotional_beat
```

## 5. Vibe Impact Classification

**Classification: vibe-critical — the story is the video's soul.**

The hook gets the viewer to stay for 3 seconds. The story is why they stay for 30. If the story is flat, the hook was a false promise. If the story is engaging, even a mediocre hook gets forgiven.

In the Cocoon experiment, there was no story node. Beat-planner-shot-compiler produced a linear sequence of product explanations: hook, context, explain, explain, demo, disclaimer, CTA. No character, no conflict, no twist, no emotional arc. The viewer's only reason to keep watching was "maybe the next ingredient is interesting."

Story-writer exists specifically because entertainment vibes need actual stories, not information sequences. The planner selects this node when the vibe demands narrative.

## 6. The 4 Node Analysis Questions

### Q1: How can the node manipulate or normalize its output?

Two levers:

- **Story structure** — `story_tension_curve` and `ending_type_preference` constrain the narrative shape. `fast_hit` + `twist_reveal` produces a very different story than `slow_build` + `emotional_beat`.
- **Product placement** — `product_appearance_moment` controls where the product enters. `twist` means the product IS the surprise. `end` means the product is the resolution. Prevents the Cocoon pattern where product was the subject from second one.

### Q2: Should the node manipulate its output, or preserve its direct input?

This node **must create**. It receives a hook (one sentence) and produces a full story (150-300 words, 4-6 moments). It is the most generative node in the pipeline.

Constraints it must preserve:
- The hook is Moment 1 — the story must start from the human-approved hook, not rewrite it
- The vibe_promise must be paid off — if the promise is "you'll laugh," the story must deliver
- Grounding guardrails — no forbidden phrasing, no invented claims

Rule: **create the story, honor the hook, pay off the promise, respect the grounding.**

### Q3: What data should the node extract from raw product information?

The node does not read the raw brief. It reads:
- From human gate: `selected_hook` — the opening of the story
- From intent_pack: `creative_angle`, `audience_painpoint`, `vibe_promise` — what the story is about and what it must deliver
- From grounding: `visually_provable_details` — what can be shown (serum texture, dropper, application). These become natural story moments.
- From grounding: `allowed_phrasing_vi` — if the story mentions product benefits, must use approved language
- From vibe_state: `format_archetype`, `product_role_in_format` — story must fit the format shape

### Q4: How does this node's output impact downstream?

| Downstream node | What the story controls |
|---|---|
| ad-likeness-lint | Checks if the story reads like an ad or like real content. A story with character, conflict, and twist passes. A product walkthrough fails. |
| casting | The story implies a character — casting needs to know who is in the story (young woman, friend, POV self, school setting). |
| shot-compiler | Each moment becomes one or more scenes. `key_content` tells what must happen. `creative_space` tells what is open to interpretation. |
| native-edit-grammar | The `energy_level` curve across moments determines edit pacing — rising = faster cuts, peak = tight framing, falling = longer holds. |

## 7. Anti-patterns Observed

### AP1: No story existed — just product explanations in sequence

The Cocoon experiment produced: hook (ingredient numbers) → context (pain point) → explain (Niacinamide) → explain (BHA) → explain (NAG) → demo (texture) → disclaimer → CTA. Seven beats, zero narrative. No character, no conflict, no twist. Each beat's "new reason to watch" was just another ingredient.

**Fix:** Story-writer produces actual narrative: character, conflict, twist, payoff. Product lives inside the story.

### AP2: Every beat was about the product

In the Cocoon output, all 7 beats referenced the product directly. B1: product ingredients. B2: product pain point. B3-B4: product ingredients explained. B5: product demo. B6: product disclaimer. B7: product CTA. The video was a 35-second product walkthrough.

**Fix:** `product_appearance_moment` controls when and where the product enters. In a funny story, the product might only appear in moments 3-4 out of 5 — the rest is character and situation.

### AP3: No emotional arc

The Cocoon beats had flat energy: informative → informative → informative → soft close. No escalation, no tension, no release. The viewer never felt suspense, surprise, or delight.

**Fix:** `story_tension_curve` forces an arc. `fast_hit` demands early tension peak. `rollercoaster` demands multiple peaks. Every moment has an `energy_level` that must follow the curve.

## 8. Contrasting Behavior Examples

Same product (Cocoon serum), two story vibes.

### Funny storytelling

```yaml
story_tension_curve: fast_hit
product_appearance_moment: twist
humor_density: throughout
ending_type_preference: twist_reveal
```

human_script summary: Girl hates skincare. Best friend forces serum on her at 11pm. She mocks the name ("winter melon? sounds like food"). Tries it reluctantly — texture is surprisingly good. Next day skin is less oily. Friend texts every night: "did you apply?" She hates admitting it works.

Moments: confession → friend conflict → name mockery (comedy peak) → reluctant try (twist) → grudging admission (payoff)

### Raw authentic

```yaml
story_tension_curve: slow_build
product_appearance_moment: middle
humor_density: none
ending_type_preference: emotional_beat
```

human_script summary: Honest monologue. Oily skin since teenage years. Tried expensive products, nothing stuck. Found this serum randomly. Didn't expect much. Texture felt different — light, not heavy like others. Still using it weeks later. Not saying it fixed everything. But it's the first serum that didn't make my skin worse. That's enough for now.

Moments: vulnerable opening → history of failed products → discovery → honest reaction → measured conclusion (no hype)

Same product, completely different stories. The story structure, emotional arc, and product role all shift based on vibe config.

## 9. Agent-friendly Config Guide

### `story_tension_curve` — How does the story build?

- `slow_build` — tension grows gradually. Good for raw, authentic, emotional stories. Viewer gets pulled in slowly.
- `fast_hit` — tension peaks in the first third, then resolves. Good for funny, surprise-based stories. Hook and setup hit hard, then the story rides the momentum.
- `rollercoaster` — multiple tension peaks. Good for longer stories (40s+) or stories with multiple turns.

For TikTok under 30s, prefer `fast_hit`. For 30-45s, `slow_build` or `rollercoaster`. Default to `fast_hit` for entertainment vibes.

### `product_appearance_moment` — When does the product show up?

This is the anti-ad knob. The later the product appears, the less ad-like the video feels.

- `early` — product is there from the start. Use only for product-hero vibes.
- `middle` — product appears at midpoint. Natural for raw authentic stories.
- `twist` — product IS the surprise. Best for funny stories — "wait, this silly-named serum actually works?"
- `end` — product is the resolution/payoff. Good for problem-solution narratives.

Default to `twist` for funny vibes, `middle` for raw authentic.

### `humor_density` — How funny is it?

- `none` — serious tone throughout. For raw authentic, emotional stories.
- `punchline_only` — one big comedy moment, rest is setup. Focused impact.
- `throughout` — humor woven into every moment. For fully comedic vibes.

### `ending_type_preference` — How does it end?

- `twist_reveal` — ending surprises the viewer. "Wait, the funny-named serum is actually legit."
- `emotional_beat` — ending makes the viewer feel something. "It's the first serum that didn't make things worse."
- `soft_loop` — ending connects back to the hook, creating a loop feeling. Good for TikTok replay.
- `call_to_action` — ending prompts action (save, comment). Use sparingly — only when the story naturally leads to it.

## 10. Vibe State Contract

### Reads

| Vibe dimension | Source | How this node uses it |
|---|---|---|
| `format_archetype` | format-library-matcher | Story must fit the format shape (skit, confession, POV) |
| `narrative_shape` | format-library-matcher | Structural guideline (setup-twist-payoff, arc) |
| `product_role_in_format` | format-library-matcher | How prominent the product should be in the story |
| `creative_angle` | intent-outcome-selector | The concept the story is built around |
| `audience_painpoint` | intent-outcome-selector | The relatable pain/desire woven into the story |
| `vibe_promise` | intent-outcome-selector | What the story must deliver by the end |
| `selected_hook_text` | human gate | The opening — Moment 1 |
| `hook_promise` | human gate | What the hook promised the viewer — story must pay off |
| `hook_energy_level` | human gate | The energy the story starts at — must match or escalate |

### Writes

| Vibe dimension | What it sets | Who reads it downstream |
|---|---|---|
| `story_moments` | The structured moment sequence | casting (who is in the story), shot-compiler (what to shoot per moment) |
| `story_arc` | The tension curve and payoff structure | native-edit-grammar (edit pacing follows energy curve) |
| `character_implied` | Who the story is about — personality, situation, age, context | casting (selects virtual model to match) |
| `product_moments` | Which moments the product appears in and how | shot-compiler (product framing), ad-likeness-lint (product density check) |
| `story_energy_curve` | The energy level sequence across all moments | native-edit-grammar (cut rhythm, music energy, caption density) |

### Contract rules

1. **Hook payoff:** The story must pay off `hook_promise`. If hook promised a funny confession, the story must deliver comedy + confession resolution.
2. **Vibe promise delivery:** The story's emotional arc must lead to `vibe_promise`. If promise is "you'll laugh," the story must be funny, not just informative.
3. **Product role consistency:** The product's role in the story must match `product_role_in_format`. If format says "product is punchline," the story can't make it the hero.
4. **Human script authority:** If human edits the script, all downstream nodes respect the edited version. Moments are re-derived from the edited human_script.

---

**Reference artifacts:**
- Original beat/shot output: `test_prompts/beat_planner_shot_prompt_complier.json`
- Original prompt: `test_prompts/beat_planner_shot_prompt_compiler_prompt.md`
- Session diagnosis: `test_prompts/session_handoff_2026-04-13.md`
