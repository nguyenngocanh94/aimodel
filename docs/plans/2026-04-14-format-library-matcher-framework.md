# Node Framework: format-library-matcher

> Part of AiModel-645.9 — Research node frameworks for vibe-controlled AI workflow design

## 1. Purpose & Pipeline Position

Format-library-matcher sits after truth-constraint-gate and before hook-angle-generator. It is the first node in the chain that makes a **creative shape decision** — every node before it is about understanding and grounding; every node after it executes within the shape it chose.

**Purpose:** Take the intent_pack (from intent-outcome-selector, now vibe-translated), the grounding (from truth-constraint-gate), and the vibe_config (from planner), and select 2-3 format archetypes that the rest of the pipeline will execute.

**Why it matters for vibe:** This node is the **primary vibe amplifier or killer**. If it picks "ingredient breakdown" for a funny storytelling vibe, every downstream node will produce informative-but-flat content. If it picks "POV skit" or "reaction format," the whole chain shifts to entertainment. The format choice is the single highest-leverage creative decision in the pipeline after the planner's initial vibe config.

**Source of ad-drift in Cocoon experiment:** Ranked F01 (ingredient breakdown) at 91/100 because it perfectly matched the edu intent. The scoring weights favored `intent_fit` (30%) and `compliance_safety` (15%) — together 45% of the score went to "safe and on-strategy," leaving only 20% for `native_feel`. A boring-but-safe format will always outscore a risky-but-entertaining one with these weights.

## 2. Input Schema

The node receives three inputs:

### From upstream: intent-outcome-selector

```json
{
  "intent_pack": {
    "primary_intent": "string",
    "secondary_intent": "string|null",
    "viewer_outcome_primary": "string",
    "viewer_outcome_secondary": ["string"],
    "creative_angle": "string",
    "audience_segment_focus": {
      "core_segment": "string",
      "awareness_level": "string",
      "purchase_temperature": "string"
    },
    "platform_hypothesis": {
      "primary_platform": "string",
      "secondary_platforms": ["string"],
      "reasoning": "string"
    },
    "tone_and_style_constraints": {
      "tone": "string",
      "style": "string",
      "avoid": ["string"]
    }
  }
}
```

### From upstream: truth-constraint-gate

```json
{
  "grounding": {
    "hard_facts": [{ "fact": "string", "source_anchor": "string" }],
    "allowed_phrasing_vi": [{ "intent": "string", "phrase": "string" }],
    "forbidden_phrasing_vi": ["string"],
    "visually_provable_details": ["string"],
    "risky_or_regulated": [{ "topic": "string", "why_risky": "string", "safe_reframe_vi": "string" }]
  }
}
```

### From planner: vibe config (set at workflow creation, same for all products)

```json
{
  "vibe_config": {
    "vibe_mode": "funny_storytelling | clean_education | aesthetic_mood | raw_authentic",
    "entertainment_ratio": "pure_entertainment | entertainment_leading | balanced | info_leading",
    "format_preference_tags": ["string"],
    "format_avoid_tags": ["string"],
    "priority_sliders": {
      "virality_priority": 0.0,
      "brand_safety": 0.0,
      "production_ease": 0.0
    },
    "library_ratio": "library_only | library_first | hybrid",
    "min_novelty_threshold": 0.0,
    "max_formats_returned": 2
  }
}
```

**Key design point:** The scoring weights are no longer hardcoded. The planner controls them through `priority_sliders`, which the node internally maps to fine-grained scoring dimensions. A funny storytelling vibe pushes `virality_priority` high; the node translates that to high weights on native_feel, novelty, and vibe_fit internally.

## 3. Output Schema

```json
{
  "format_shortlist": {
    "selection_logic": {
      "primary_intent_used": "string",
      "vibe_mode_used": "string",
      "scoring_weights_applied": {
        "intent_fit": 0.0,
        "vibe_fit": 0.0,
        "native_feel": 0.0,
        "novelty": 0.0,
        "production_feasibility": 0.0,
        "compliance_safety": 0.0
      }
    },
    "formats": [
      {
        "format_id": "string",
        "format_name_vi": "string",
        "source": "library | llm_generated",
        "core_pattern": "string",
        "why_it_fits": "string",
        "vibe_alignment_note": "string",
        "best_for_outcome": ["string"],
        "hook_style_options": ["string"],
        "product_visibility_mode": "subtle_background | natural_use_first | balanced | hero_moment",
        "production_feasibility": {
          "score_1_to_5": 0,
          "why": "string",
          "requirements": ["string"],
          "risk_flags": ["string"]
        },
        "native_feel_score": 0,
        "novelty_score": 0,
        "compliance_safety_score": 0,
        "estimated_duration_range_sec": "string"
      }
    ],
    "ranking": [
      {
        "format_id": "string",
        "rank": 0,
        "overall_score_100": 0,
        "score_breakdown": {
          "intent_fit": 0,
          "vibe_fit": 0,
          "native_feel": 0,
          "novelty": 0,
          "production_feasibility": 0,
          "compliance_safety": 0
        },
        "why_ranked_here": "string"
      }
    ],
    "recommended_top_2": [
      {
        "format_id": "string",
        "reason": "string"
      }
    ],
    "formats_avoided_with_reason": [
      {
        "format_name_vi": "string",
        "why_not": "string"
      }
    ]
  }
}
```

Two additions from the Cocoon experiment output:

1. **`source` field** — tracks whether the format came from the curated library or was LLM-generated. Enables auditing: are library formats consistently beating LLM-generated ones?
2. **`vibe_alignment_note`** — each format must explicitly explain how it serves the vibe config, not just the intent. In the Cocoon experiment, `why_it_fits` only justified intent fit.

## 4. Config Knobs the Planner Can Set

All product-independent. These are set once when the workflow is designed and locked across product runs.

| Knob | Type | What it controls |
|------|------|-----------------|
| `vibe_mode` | enum | Overall creative feel. Drives library eligibility and LLM generation direction. |
| `entertainment_ratio` | enum | `pure_entertainment`, `entertainment_leading`, `balanced`, `info_leading`. Hard multiplier on scoring — prevents edu formats from winning when vibe demands entertainment. |
| `format_preference_tags` | string[] | Positive scoring bias toward format types. |
| `format_avoid_tags` | string[] | Hard filter. Excluded before scoring. |
| `priority_sliders` | object | Three high-level sliders (sum to 1.0): `virality_priority`, `brand_safety`, `production_ease`. Node internally maps to fine-grained scoring dimensions. |
| `library_ratio` | enum | `library_only`, `library_first`, `hybrid`. Controls LLM-generated format allowance. |
| `min_novelty_threshold` | float 0-1 | Floor. Rejects safe-but-boring after scoring. |
| `max_formats_returned` | int 2-5 | How many formats pass downstream. |

### Example: funny genz storytelling

```yaml
vibe_mode: funny_storytelling
entertainment_ratio: entertainment_leading
format_preference_tags: [skit, pov, reaction, confession]
format_avoid_tags: [ingredient_breakdown, expert_review, listicle]
priority_sliders: { virality_priority: 0.65, brand_safety: 0.20, production_ease: 0.15 }
min_novelty_threshold: 0.6
library_ratio: hybrid
max_formats_returned: 2
```

### Example: clean product education

```yaml
vibe_mode: clean_education
entertainment_ratio: info_leading
format_preference_tags: [ingredient_breakdown, how_to, myth_fact]
format_avoid_tags: [skit, meme, challenge]
priority_sliders: { virality_priority: 0.25, brand_safety: 0.45, production_ease: 0.30 }
min_novelty_threshold: 0.2
library_ratio: library_first
max_formats_returned: 2
```

Same node, different knob settings → completely different format rankings. Same product, different vibe.

## 5. Vibe Impact Classification

**Classification: vibe-critical — primary drift source.**

This is the single most consequential node for vibe in the entire pipeline. Every node before it produces strategy and constraints. Every node after it produces creative execution. Format-library-matcher sits at the boundary — it translates strategy into a creative shape that the rest of the chain fills in.

If this node picks wrong, nothing downstream can recover. Hook-angle-generator generates hooks that fit the wrong format. Beat-planner structures beats around the wrong narrative pattern. Shot-compiler renders the wrong visual style. The entire chain faithfully executes a bad creative direction.

In the Cocoon experiment:
- Node picked F01 (ingredient breakdown) at 91/100
- Hook-angle-generator generated ingredient-tease hooks (because format = ingredient breakdown)
- Beat-planner created linear explain-explain-explain beats (because format = ingredient breakdown)
- Final video = well-executed, compliant, informative, boring

The new `entertainment_ratio` + `priority_sliders` + `format_avoid_tags` make it structurally impossible for a safe-edu format to win when the vibe demands entertainment.

## 6. The 4 Node Analysis Questions

### Q1: How can the node manipulate or normalize its output?

Three manipulation levers:

- **Selection** — which formats make the shortlist. The curated library is pre-filtered by `format_avoid_tags` and `vibe_mode` eligibility before scoring even starts. Formats excluded here cannot win.
- **Scoring** — how formats are ranked. The `priority_sliders` map to internal weights, `entertainment_ratio` acts as a multiplier, `min_novelty_threshold` acts as a floor. Same library, different knob settings, completely different ranking.
- **Generation** — when `library_ratio` is `hybrid`, the node asks the LLM to propose 1-2 novel formats. The LLM prompt is constrained by `vibe_mode` and `entertainment_ratio`, so generated formats must fit the vibe. This is the least controlled lever — LLM can still drift toward generic formats.

In the Cocoon experiment, the node only had scoring (with hardcoded weights). Selection was implicit in the prompt instructions, and generation was fully LLM-controlled with no vibe constraint. All three levers now have explicit planner control.

### Q2: Should the node manipulate its output, or preserve its direct input more strictly?

This node **must** manipulate. Its entire job is to make a creative judgment — "given this strategy and vibe, which formats work best?" Passing the intent_pack through untransformed would defeat the purpose.

However, it should **not** invent new claims, modify grounding constraints, or reinterpret the audience segment. It reads those as fixed context. The only thing it decides is format shape.

Rule: **transform the creative direction, preserve the factual context.**

### Q3: What data should the node extract from raw product information and the user prompt?

The node does not read raw product info directly. It reads:

- From `intent_pack`: creative_angle, primary_intent, viewer outcomes, tone constraints, platform hypothesis
- From `grounding`: visually_provable_details (affects production feasibility — can we show this product on camera?), risky_or_regulated topics (affects which formats are safe)
- From `vibe_config`: all 8 knobs

The one product-adjacent thing it needs from grounding is **visual showability** — a serum with a beautiful dropper texture enables close-up ASMR formats, while a plain white pill bottle doesn't. This is product-specific but already abstracted through `visually_provable_details` in the grounding.

### Q4: How does this node's output impact downstream node choice, node configuration, and drift risk?

Downstream impact is **total**:

| Downstream node | What format choice controls |
|---|---|
| hook-angle-generator | Hook style options come directly from the selected format's `hook_style_options` field |
| hook-novelty-scorer | Novelty thresholds shift — a skit format demands more surprising hooks than an explainer |
| beat-planner-shot-compiler | Narrative structure — skit = setup-twist-payoff, explainer = linear, story = arc |
| ad-likeness-lint | Baseline expectation — what counts as "ad-like" depends on the format chosen |
| native-edit-grammar | Edit pace, caption style, transition style all follow the format's DNA |

**Drift risk:** If this node outputs a safe-but-boring format, the entire chain amplifies it. No downstream node can override a bad format choice — they can only execute within it. This is why `entertainment_ratio` and `min_novelty_threshold` exist: they prevent the drift at the source.

## 7. Anti-patterns Observed

### AP1: Hardcoded scoring weights favored safety over creativity

The original prompt fixed `intent_fit` at 30% and `compliance_safety` at 15% — together 45% of the score rewarded "safe and on-strategy." `native_feel` only got 20%. Result: F01 (ingredient breakdown) scored 91/100 by being maximally safe and maximally on-intent. F02 (POV routine) scored 85 despite having equal native_feel — it lost because it was slightly less "on-intent." The 6-point gap was entirely driven by the safety bias.

**Fix:** `priority_sliders` replace hardcoded weights. Planner controls the balance.

### AP2: No format was rejected for being boring

All 5 generated formats passed. There was no novelty floor. The node had `formats_to_avoid_now` in its output, but that was a post-hoc recommendation, not a scoring gate. Boring-but-compliant formats could and did win.

**Fix:** `min_novelty_threshold` + `entertainment_ratio` as hard gates.

### AP3: LLM generated all 5 formats with no library anchor

Every format was LLM-invented. Without a curated library, the LLM defaulted to the most obvious formats for skincare edu — ingredient breakdown, routine POV, myth-vs-fact, texture ASMR, checklist. All five were variations of "explain things about the product." None were entertainment-first formats like skit, reaction, challenge, confession, or story. The LLM's generation space was implicitly narrowed by the edu intent with no counterweight.

**Fix:** Curated library with `hybrid` mode ensures proven entertainment formats are always in the candidate pool.

## 8. Contrasting Behavior Examples

Same product (Cocoon serum), two different vibe configs.

### Vibe A: Funny genz storytelling

```yaml
vibe_mode: funny_storytelling
entertainment_ratio: entertainment_leading
format_preference_tags: [skit, pov, reaction, confession]
format_avoid_tags: [ingredient_breakdown, expert_review, listicle]
priority_sliders: { virality_priority: 0.65, brand_safety: 0.20, production_ease: 0.15 }
min_novelty_threshold: 0.6
library_ratio: hybrid
```

Expected top 2 formats:
- **"POV ban than ep ban skincare"** (POV your friend forces you into skincare) — skit format, one person plays both roles or talks to camera as the pushy friend. Product appears as the punchline prop, not the subject.
- **"Confession: minh ghet serum... cho den khi"** (Confession: I hated serum until...) — confession/story format, opens with contrarian hook, product arrives as the twist.

Ingredient breakdown is hard-filtered out by `format_avoid_tags`. Even if the LLM generates something like "fun ingredient quiz," it scores low because `entertainment_ratio: entertainment_leading` penalizes any format where information is the core structure.

### Vibe B: Direct product-centered intro

```yaml
vibe_mode: clean_education
entertainment_ratio: info_leading
format_preference_tags: [ingredient_breakdown, how_to, texture_demo]
format_avoid_tags: [skit, meme, challenge]
priority_sliders: { virality_priority: 0.25, brand_safety: 0.45, production_ease: 0.30 }
min_novelty_threshold: 0.2
library_ratio: library_first
```

Expected top 2 formats:
- **"Giai ma thanh phan serum bi dao"** (Decode ingredients) — exactly what the Cocoon experiment produced. For this vibe, it is the correct choice.
- **"Texture test: serum long nhe tham tren da"** (Texture close-up demo) — visual proof format, low risk, high production feasibility.

This is essentially what the Cocoon experiment produced — and for this vibe, **that is correct**. The ad-drift was not because ingredient breakdown is a bad format. It is bad when the vibe asked for entertainment but got education. With explicit vibe config, the same format is a valid choice when the vibe actually wants it.

**Core point:** The same node, same product, same library — completely different output based on planner config. The format itself is not the problem. The absence of vibe control was.

## 9. Agent-friendly Config Guide

This is what the planner agent reads when deciding how to configure this node.

### `vibe_mode` — What kind of video is this?

Sets the creative universe. Everything downstream follows from this choice. Pick the mode that matches the user's description of how the video should feel — not what it is about.

- `funny_storytelling` — video feels like a creator telling a funny story that happens to include a product
- `clean_education` — video feels like a trusted friend explaining something useful
- `aesthetic_mood` — video feels like a visual mood piece, minimal talking, texture and atmosphere
- `raw_authentic` — video feels unscripted, like someone just grabbed their phone and shared something real

### `entertainment_ratio` — Is this entertainment or information?

The single most important anti-ad-drift knob. Ask yourself: when a viewer watches this, should they feel entertained first or informed first?

- `pure_entertainment` — product is a prop or punchline, never the subject
- `entertainment_leading` — video entertains, product appears naturally inside the entertainment
- `balanced` — equal weight, good for formats like myth-vs-fact
- `info_leading` — video teaches, entertainment is the wrapping

If the user says anything like "funny," "story," "genz," "viral," "not an ad" — use `entertainment_leading` or higher. Default to `entertainment_leading` when uncertain — it is easier to add product info downstream than to remove ad-smell.

### `format_preference_tags` / `format_avoid_tags` — What shapes fit this vibe?

Preference tags boost, avoid tags hard-exclude. Translate the user's vibe description to format shapes:

- User says "funny" — prefer `[skit, reaction, confession, pov]`, avoid `[lecture, listicle, expert_review]`
- User says "aesthetic" — prefer `[texture_asmr, routine_pov, mood_reel]`, avoid `[skit, meme, talking_head]`
- User says "educational" — prefer `[ingredient_breakdown, how_to, myth_fact]`, avoid `[skit, challenge]`

### `priority_sliders` — What matters most?

Three sliders, sum to 1.0. Think of it as budget allocation:

- `virality_priority` — high means chase native feel, novelty, surprise. Set high for entertainment vibes.
- `brand_safety` — high means compliance, conservative claims, low regulatory risk. Set high for pharma/health/regulated products.
- `production_ease` — high means prefer formats that are simple to shoot and render. Set high when budget or timeline is tight.

Rule of thumb: for most creator-native TikTok content, `virality: 0.55+, safety: 0.25, ease: 0.20` is a good starting point.

### `library_ratio` — Trust the library or let the LLM experiment?

- `library_only` — safest, only proven formats. Use when brand is risk-averse.
- `library_first` — 4 library + 1 LLM-generated. Good default.
- `hybrid` — 3 library + 2 LLM-generated. Use when user wants something fresh or the library does not cover the vibe well.

### `min_novelty_threshold` — How boring is too boring?

Floor score from 0 to 1. Any format scoring below this on novelty is rejected regardless of other scores. Set 0.5+ for entertainment vibes. Set 0.2 for education vibes where predictable structure is fine.

### `max_formats_returned` — How many options downstream?

2 = focused, hook-angle-generator works with a clear direction. 4-5 = exploratory, more creative variety but more work downstream. Default 2 for most workflows.

## 10. Vibe State Contract

### Reads

| Vibe dimension | Source | How this node uses it |
|---|---|---|
| `vibe_mode` | planner config | Drives library filtering and LLM generation constraints |
| `entertainment_ratio` | planner config | Hard multiplier on scoring — edu formats penalized when entertainment demanded |
| `creative_angle` | intent-outcome-selector | Used to evaluate format-angle fit — does this format naturally support the chosen angle? |
| `tone` | intent-outcome-selector | Soft constraint — formal tone makes skit formats score lower, casual tone makes lecture formats score lower |

### Writes

| Vibe dimension | What it sets | Who reads it downstream |
|---|---|---|
| `format_archetype` | The selected format's core pattern (skit, pov, confession, explainer, etc.) | hook-angle-generator, beat-planner, native-edit-grammar, ad-likeness-lint |
| `narrative_shape` | Implied structure — linear, setup-twist-payoff, arc, loop | beat-planner-shot-compiler |
| `visual_style_hint` | Close-up product, talking head, POV hands, cinematic, raw phone | render-spec-compiler |
| `product_role_in_format` | How the product appears — hero, prop, punchline, context, absent | hook-angle-generator, ad-likeness-lint |
| `entertainment_vs_info_balance` | Passes through the realized balance after format selection — may differ slightly from the planner's target | ad-likeness-lint, hook-novelty-scorer |
| `hook_style_candidates` | Format-native hook options from the selected format | hook-angle-generator |

### Contract rule

Every downstream node must check `format_archetype` and `product_role_in_format` before generating output. If beat-planner produces a linear explain-explain-explain structure when `format_archetype` is `skit` and `narrative_shape` is `setup-twist-payoff`, that is a contract violation.

This is what was missing in the Cocoon experiment — there was no shared vibe state. Each node read the previous node's full JSON output and made its own interpretation. Now the vibe dimensions are explicit, named, and contractually binding across the chain.

---

**Reference artifacts:**
- Prompt: `test_prompts/formated_library_matcher_prompt.md`
- Output: `test_prompts/formated_library_matcher.json`
- Pipeline plan: `.cursor/plans/short-video-pipeline.md`
- Session diagnosis: `test_prompts/session_handoff_2026-04-13.md`
