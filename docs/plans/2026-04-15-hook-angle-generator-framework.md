# Node Framework: hook-angle-generator

> Part of AiModel-645.9 — Research node frameworks for vibe-controlled AI workflow design

## 1. Purpose & Pipeline Position

Hook-angle-generator sits after format-library-matcher and before beat-planner-shot-compiler. A **human gate** follows this node — the human picks (and optionally edits) the final hook before the pipeline continues. Hook-novelty-scorer has been removed from the pipeline; the human gate absorbs that quality check.

**Purpose:** Take the format_shortlist (with hook style hints), the vibe_config (from planner), and the grounding (compliance guardrails), and generate concrete hooks and angles — the specific words, visuals, and tension that open the video. Present 3 hooks to the human (2 LLM-generated + 1 from hook library), the human picks one and optionally edits it.

**Why it matters for vibe:** The hook is the video. On TikTok/Reels, viewers decide in 1-3 seconds whether to stay or scroll. A great format with a boring hook = nobody watches. This node determines whether the vibe the planner intended actually reaches the viewer in the first seconds. It is the first node that produces actual creative content — everything before it was strategy, grounding, and selection.

**Role in the drift chain:** In the Cocoon experiment, this was the **second drift amplifier**. Format-library-matcher selected ingredient breakdown, then this node generated 10 hooks — all informative, none dramatic. The selected hook ("7% Niacinamide, 0.8% BHA, 4% NAG — combo nay?") was a curiosity-gap hook built on numbers. Informative, yes. Scroll-stopping for a genz viewer? No. The node had no vibe pressure to push toward entertainment, humor, or conflict.

## 2. Input Schema

### From upstream: format-library-matcher (via vibe state)

```json
{
  "format_shortlist": {
    "recommended_top_2": [
      {
        "format_id": "string",
        "format_name_vi": "string",
        "core_pattern": "string",
        "hook_style_options": ["string"],
        "product_visibility_mode": "string"
      }
    ]
  },
  "vibe_state": {
    "format_archetype": "string",
    "narrative_shape": "string",
    "product_role_in_format": "string",
    "entertainment_vs_info_balance": "string",
    "hook_style_candidates": ["string"]
  }
}
```

### From upstream: truth-constraint-gate

```json
{
  "grounding": {
    "allowed_phrasing_vi": [{ "intent": "string", "phrase": "string" }],
    "forbidden_phrasing_vi": ["string"],
    "risky_or_regulated": [{ "topic": "string", "why_risky": "string", "safe_reframe_vi": "string" }],
    "visually_provable_details": ["string"]
  }
}
```

### From upstream: intent-outcome-selector

```json
{
  "intent_pack": {
    "creative_angle": "string",
    "viewer_outcome_primary": "string",
    "audience_segment_focus": {
      "core_segment": "string",
      "awareness_level": "string",
      "purchase_temperature": "string"
    },
    "tone_and_style_constraints": {
      "tone": "string",
      "style": "string",
      "avoid": ["string"]
    }
  }
}
```

### From planner: vibe config

```json
{
  "vibe_config": {
    "vibe_mode": "string",
    "hook_tension_level": "low | medium | high | extreme",
    "hook_humor_level": "none | light | moderate | heavy",
    "hook_vibe_styles": ["confession", "hot_take", "absurd_question", "pattern_break", "conflict", "vulnerability"],
    "must_include_twist": true,
    "library_retrieval_count": 1,
    "llm_generation_count": 2,
    "hooks_presented_to_human": 3
  }
}
```

**Key design point:** The node builds its generation pool by combining `hook_style_candidates` (from format — the structure hint) with `hook_vibe_styles` (from planner — the energy hint). This is the "expand, not override" approach: the format's suggestions ensure the hook fits the format structure, the vibe additions ensure the hook has the right energy. The union gives the LLM a richer generation space.

## 3. Output Schema

```json
{
  "hook_pack": {
    "generation_context": {
      "formats_used": ["string"],
      "style_pool": {
        "from_format": ["string"],
        "from_vibe": ["string"],
        "combined": ["string"]
      },
      "vibe_mode": "string",
      "tension_level": "string",
      "humor_level": "string"
    },
    "hooks": [
      {
        "hook_id": "string",
        "format_id": "string",
        "hook_text_vi": "string",
        "hook_type": "string",
        "style_source": "format | vibe | combined",
        "first_3s_visual_direction": "string",
        "angle_statement": "string",
        "tension_mechanism": "string",
        "product_visibility": "string",
        "why_it_should_hold_attention": "string",
        "twist_element": "string|null",
        "compliance_check": {
          "risk_level": "low | medium | high",
          "passes_grounding": true,
          "notes": "string"
        },
        "scores": {
          "novelty": 0,
          "tension": 0,
          "vibe_fit": 0,
          "format_fit": 0,
          "compliance": 0
        }
      }
    ],
    "ranking": [
      {
        "hook_id": "string",
        "rank": 0,
        "overall_score": 0,
        "why_ranked_here": "string"
      }
    ],
    "recommended_primary": {
      "hook_id": "string",
      "reason": "string"
    },
    "recommended_backup": {
      "hook_id": "string",
      "reason": "string"
    },
    "rejected_hooks": [
      {
        "hook_text_vi": "string",
        "why_rejected": "string"
      }
    ]
  }
}
```

### Human gate interface

The human sees a simple selection:

```
Hook 1 (LLM):     "POV ban than ep ban thoa serum bi dao luc 11 dem"
Hook 2 (LLM):     "Ghet doc thanh phan lam... nhung lo nay bat minh phai doc"
Hook 3 (Library):  "Ai doi serum ma mui bi dao — nghe di nhung da cam on"

→ Pick: [1] [2] [3]
→ Edit (optional): [text field]
→ Confirm
```

The human-selected (and optionally edited) hook becomes the canonical version and flows downstream. It is also stored back into the hook library with a `human_approved` tag.

### Hook library storage

Every hook generated is stored in the database with tags for future retrieval:

```json
{
  "hook_text_vi": "string",
  "vibe_mode": "string",
  "format_archetype": "string",
  "hook_type": "string",
  "tension_mechanism": "string",
  "product_category": "string",
  "source": "llm_generated | library_rewritten",
  "human_approved": false,
  "human_edited": false,
  "created_at": "timestamp",
  "original_product": "string"
}
```

Library retrieval matches on: `vibe_mode` + `format_archetype` + `product_category`. Retrieved hooks are rewritten by the LLM for the current product — the structure and tension mechanism stay, the product details change.

Over time, the library accumulates human-validated hooks. Human-approved hooks are weighted higher in retrieval. Human-edited hooks are the highest quality — they represent both AI creativity and human judgment.

### Changes from Cocoon experiment

1. **`style_source`** — tracks whether the hook came from format hints, vibe hints, or a combination. Enables auditing which source produces better hooks.
2. **`tension_mechanism`** — every hook must explicitly name how it creates tension. "Ingredient tease" is not a tension mechanism. "Curiosity gap via unexpected ingredient combination" is.
3. **`twist_element`** — if planner set `must_include_twist: true`, each hook must describe the twist or be rejected.
4. **`scores` per hook** — scored on 5 dimensions for transparent ranking.
5. **`rejected_hooks`** — hooks that failed thresholds, visible for debugging.

## 4. Config Knobs the Planner Can Set

All product-independent. Set once when the workflow is designed.

| Knob | Type | What it controls |
|------|------|-----------------|
| `hook_tension_level` | enum | `low`, `medium`, `high`, `extreme`. How much conflict/surprise/provocation. |
| `hook_humor_level` | enum | `none`, `light`, `moderate`, `heavy`. Whether humor is a lever. |
| `hook_vibe_styles` | string[] | Vibe-driven styles added to format's suggestions to expand the creative pool. |
| `must_include_twist` | bool | If true, every hook must have an explicit twist or be rejected. |
| `library_retrieval_count` | int | Hooks pulled from library. Default 1. |
| `llm_generation_count` | int | Hooks LLM generates fresh. Default 2. |
| `hooks_presented_to_human` | int | Total hooks shown at human gate. Default 3. |

### Example: funny genz storytelling

```yaml
hook_tension_level: high
hook_humor_level: moderate
hook_vibe_styles: [confession, absurd_question, hot_take, humor_contrast]
must_include_twist: true
library_retrieval_count: 1
llm_generation_count: 2
hooks_presented_to_human: 3
```

### Example: clean product education

```yaml
hook_tension_level: medium
hook_humor_level: none
hook_vibe_styles: [curiosity_gap, stat_reveal]
must_include_twist: false
library_retrieval_count: 1
llm_generation_count: 2
hooks_presented_to_human: 3
```

## 5. Vibe Impact Classification

**Classification: vibe-critical — second drift amplifier, first real creative content.**

Format-library-matcher selects structure. This node writes the first creative words and visuals the viewer will see. If the hook is bland, the viewer scrolls — no amount of good beat-planning or edit-grammar downstream can recover a lost viewer.

In the Cocoon experiment, this node amplified the format drift: ingredient breakdown format led to ingredient-tease hooks led to a linear informative video.

With the new design, three defenses against drift:
1. Expanded style pool (format hints + vibe hints combined)
2. Tension/humor/twist requirements from planner config
3. Human gate as final quality check

## 6. The 4 Node Analysis Questions

### Q1: How can the node manipulate or normalize its output?

Three levers:

- **Style pool expansion** — combines format-suggested hook styles with vibe-demanded styles. The union of both pools gives the LLM a richer generation space than either alone. This is the main creative lever.
- **LLM generation constraints** — the prompt enforces `hook_tension_level`, `hook_humor_level`, and `must_include_twist`. Hooks that don't meet these are rejected before reaching the human gate.
- **Library retrieval** — the library hook is selected by matching vibe_mode + format_archetype + product_category tags, then rewritten by LLM for the current product. Provides a human-validated reference point.

### Q2: Should the node manipulate its output, or preserve its direct input?

This node **must create**, not preserve. It is the first generative node in the chain. It receives strategy (intent_pack, format_shortlist, vibe_config) and produces creative content (hook text, visual direction, angle).

However, it must preserve grounding constraints — no forbidden phrasing, no risky claims in the hook text. The hook is creative but compliance-bounded.

Rule: **create freely within the vibe, never violate the grounding.**

### Q3: What data should the node extract from raw product information?

The node does not read raw product info. It reads:

- From `format_shortlist`: hook_style_candidates, format_archetype, product_visibility_mode
- From `intent_pack`: creative_angle, audience segment, tone constraints
- From `grounding`: allowed/forbidden phrasing, visually provable details (for first_3s_visual_direction)
- From `vibe_config`: tension, humor, styles, twist requirement
- From `hook_library`: matching past hooks for retrieval + rewrite

The only product-adjacent data is `visually_provable_details` from grounding — to know what can actually be shown in the first 3 seconds (dropper bottle, texture on skin, etc.).

### Q4: How does this node's output impact downstream?

| Downstream node | What the selected hook controls |
|---|---|
| beat-planner-shot-compiler | The hook becomes Beat 1. Its tension mechanism defines the narrative promise that beats must pay off. |
| ad-likeness-lint | The hook sets the ad-likeness baseline — a confession hook signals creator-native, an ingredient-tease hook signals potential ad territory. |
| native-edit-grammar | Hook style implies edit style — a humor hook needs faster cuts and sound effects, a confession hook needs intimate pacing. |

**Drift risk:** Significantly reduced by the human gate. Even if the LLM generates bland hooks, the human can reject all three and request regeneration. The human gate is the hard stop against drift at this critical point.

## 7. Anti-patterns Observed

### AP1: All hooks were informative, none were dramatic

The Cocoon experiment generated 10 hooks. Types: `pain_point` (3x), `ingredient_tease` (2x), `curiosity_gap` (3x), `pov_visual` (1x), `text_overlay` (1x). Zero hooks used humor, conflict, confession, surprise, or storytelling. The node had no instruction to prioritize tension or entertainment.

**Fix:** `hook_tension_level`, `hook_humor_level`, and `hook_vibe_styles` from planner config. Plus expanded style pool.

### AP2: No hook was rejected for being boring

All 10 hooks passed. The compliance check only looked at grounding violations. There was no novelty or tension scoring. A hook like "Da dau mun an — ban da biet combo 7% Niacinamide va 0.8% BHA chua?" passed because it was factually correct, even though it reads like a product listing.

**Fix:** Human gate. You see 3 hooks and can reject all of them. No boring hook can sneak past a human who knows what "good vibe" feels like.

### AP3: The hook generation pool was limited to format-suggested styles only

Format F01 suggested: pain_point, ingredient_tease, curiosity_gap. The LLM generated only from those styles. No creative expansion. The format defined the ceiling, and the node didn't push beyond it.

**Fix:** Style pool expansion. Format hints + vibe hints combined into a richer pool.

### AP4: No hook library existed

Each run started with zero reference material. The LLM defaulted to the most obvious hook patterns for the format. No learning from past runs, no human-validated reference hooks.

**Fix:** Hook library with tagged storage. Every generated hook is stored. Human-selected hooks get a quality tag. Library provides 1 retrieval hook per run as a reference anchor, rewritten for the current product.

## 8. Contrasting Behavior Examples

Same product (Cocoon serum), two vibes.

### Vibe A: Funny genz storytelling

```yaml
hook_tension_level: high
hook_humor_level: moderate
hook_vibe_styles: [confession, absurd_question, hot_take, humor_contrast]
must_include_twist: true
```

Style pool:
- From format (skit): `[setup_punchline, character_intro, absurd_premise]`
- From vibe: `[confession, absurd_question, hot_take, humor_contrast]`
- Combined: `[setup_punchline, character_intro, absurd_premise, confession, absurd_question, hot_take, humor_contrast]`

3 hooks presented to human:

```
Hook 1 (LLM, confession + absurd_premise):
"Minh ghet skincare lam... cho den khi ban than di lo serum bi dao vao mat luc 11 dem"

Hook 2 (LLM, hot_take + humor_contrast):
"Serum mui bi dao nghe nhu do an — nhung da minh thich an mon nay"

Hook 3 (Library, rewritten):
"POV: ban mua serum vi ten nghe funny, khong ngo no hop da that"
```

### Vibe B: Clean product education

```yaml
hook_tension_level: medium
hook_humor_level: none
hook_vibe_styles: [curiosity_gap, stat_reveal]
must_include_twist: false
```

Style pool:
- From format (ingredient_breakdown): `[pain_point, ingredient_tease, curiosity_gap]`
- From vibe: `[curiosity_gap, stat_reveal]`
- Combined: `[pain_point, ingredient_tease, curiosity_gap, stat_reveal]`

3 hooks presented to human:

```
Hook 1 (LLM, ingredient_tease):
"7% Niacinamide + 0.8% BHA + 4% NAG — combo nay trong mot lo serum bi dao?"

Hook 2 (LLM, pain_point + curiosity_gap):
"Da dau mun an — ban da check thanh phan serum minh dang dung chua?"

Hook 3 (Library, rewritten):
"3 thanh phan nay o dung nong do — minh giai thich tai sao no quan trong"
```

Vibe B produces hooks almost identical to the Cocoon experiment — and that is correct. The problem was never that these hooks are bad. They are bad when the vibe asked for entertainment.

## 9. Agent-friendly Config Guide

### `hook_tension_level` — How hard should the hook grab?

Controls the intensity of the opening moment. Think about what the viewer feels in the first 2 seconds.

- `low` — gentle entry, soft curiosity. Viewer thinks "hmm, interesting." Good for aesthetic, ASMR, routine content.
- `medium` — clear hook with a question or relatable pain point. Viewer thinks "oh, that's me." Good for education, how-to.
- `high` — strong emotion, conflict, or surprise. Viewer thinks "wait, what?" Good for storytelling, confession, reaction.
- `extreme` — provocative, contrarian, or absurd. Viewer thinks "no way." Good for viral-first content, hot takes. Risk: can feel clickbait if not paid off.

If user says "funny," "viral," "genz" — use `high` or `extreme`. If user says "clean," "trustworthy," "informative" — use `medium`. Default to `high` when uncertain — it is easier to soften downstream than to inject tension later.

### `hook_humor_level` — Should the hook be funny?

- `none` — serious, earnest, or informational tone
- `light` — a smile, not a laugh. Wry observation, gentle self-deprecation.
- `moderate` — clearly funny. Absurd premise, exaggeration, relatable comedy.
- `heavy` — comedy-first. The hook's primary job is to make the viewer laugh. Product is secondary.

Match to vibe. Not every entertaining video needs humor — story tension, vulnerability, and surprise work without comedy.

### `hook_vibe_styles` — What energy should the hook carry?

Added to the format's hook suggestions to expand the creative pool. Pick styles that match the user's vibe description:

- `confession` — "I used to hate..." / "I never thought I'd..." Personal, vulnerable, relatable.
- `hot_take` — "Everyone says X but actually Y." Contrarian, debate-triggering.
- `absurd_question` — "Why does this serum smell like food and why does my skin love it?" Weird, curiosity-driven.
- `pattern_break` — Opens with something unexpected that breaks TikTok scroll pattern. Visual or textual surprise.
- `humor_contrast` — Sets up expectation, delivers opposite. Comedy structure.
- `vulnerability` — Raw, honest, emotional. "My skin was so bad I didn't want to leave the house."
- `conflict` — Two opposing ideas in tension. "Hated vs loved," "ugly vs effective," "weird vs works."

### `must_include_twist` — Does every hook need a turn?

If true, every hook must contain a reversal, surprise, or subverted expectation. Rejects straightforward hooks. Set true for entertainment vibes, false for education vibes.

### `library_retrieval_count` / `llm_generation_count` / `hooks_presented_to_human` — How many hooks, from where?

Default: 1 library + 2 LLM = 3 to human gate. Increase LLM count if you want more variety. Increase library count if the library is mature and has many high-quality hooks for this vibe.

## 10. Vibe State Contract

### Reads

| Vibe dimension | Source | How this node uses it |
|---|---|---|
| `format_archetype` | format-library-matcher | Determines format-side hook style suggestions |
| `narrative_shape` | format-library-matcher | Informs what kind of promise the hook must set up (twist-payoff vs linear vs arc) |
| `product_role_in_format` | format-library-matcher | Constrains how product appears in the hook (prop, punchline, hero, absent) |
| `hook_style_candidates` | format-library-matcher | Format-side hook styles, combined with vibe styles |
| `creative_angle` | intent-outcome-selector | The concept to hook around |
| `tone` | intent-outcome-selector | Casual, formal, raw — affects hook language |

### Writes

| Vibe dimension | What it sets | Who reads it downstream |
|---|---|---|
| `selected_hook_text` | The human-approved (optionally edited) hook text | beat-planner-shot-compiler |
| `hook_tension_mechanism` | How the hook creates tension (confession, conflict, curiosity, humor, etc.) | beat-planner (must pay off this tension), ad-likeness-lint |
| `hook_promise` | What the hook implicitly promises the viewer will get (a story, an answer, a laugh, a reveal) | beat-planner (beats must deliver on this promise) |
| `hook_product_visibility` | How visible the product is in the hook moment | beat-planner, native-edit-grammar |
| `hook_energy_level` | The intensity set by the hook — downstream must match or escalate, never drop | beat-planner, native-edit-grammar |

### Contract rule

Beat-planner must pay off `hook_promise`. If the hook promises a story ("Confession: I hated serum until..."), the beats must deliver a story arc, not switch to an ingredient list. If the hook promises a laugh, the beats must have comedic payoff. Breaking the hook promise = viewer feels baited.

This was another Cocoon problem — the hook teased curiosity about ingredients, and the beats delivered a list of ingredients. The promise was paid off, but only because both were equally flat. With a stronger hook promise (confession, conflict, humor), the beats are forced to rise to match.

---

**Reference artifacts:**
- Hook pack output: `test_prompts/objective_fit_native_lint.json` (contains hook_pack)
- Beat/shot output that hooks fed into: `test_prompts/beat_planner_shot_prompt_complier.json`
- Session diagnosis: `test_prompts/session_handoff_2026-04-13.md`
