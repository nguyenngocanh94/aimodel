# Node Framework: intent-outcome-selector

> Part of AiModel-645.9 — Research node frameworks for vibe-controlled AI workflow design

## 1. Purpose & Pipeline Position

Intent-outcome-selector sits after brief-ingest and before truth-constraint-gate. It is the **vibe translator** — the node where the planner's abstract vibe config meets the real product for the first time.

**Purpose:** Take the structured brief (from brief-ingest) and the vibe_config (from planner), and produce a creative seed: the angle, the audience pain, the audience context, and the vibe promise. This seed is what every downstream creative node builds on.

**What changed from the Cocoon experiment:** In the original design, this node *chose* the creative direction (it decided "education_soft_product_support" on its own). In the new design, the planner already chose the vibe. This node *translates* that vibe into concrete strategy grounded in this specific product and market. It no longer decides whether to be funny or educational — it figures out what "funny" means for this particular product.

**Light product-category awareness:** The node knows skincare has different creative opportunities than food or fashion — but this only affects how the vibe translates, not which vibe is chosen. "Funny storytelling" for skincare leads to a different angle than "funny storytelling" for coffee, even though the vibe is the same.

## 2. Input Schema

### From upstream: brief-ingest

```json
{
  "brief": {
    "product": {
      "brand": "string",
      "product_name_full": "string",
      "category": "string",
      "title_short": "string"
    },
    "claims_verbatim": ["string"],
    "benefits_structured": ["string"],
    "ingredients_highlights": [
      {
        "name": "string",
        "concentration": "string|null",
        "role_from_source": "string|null"
      }
    ],
    "target_user": {
      "skin_type": ["string"],
      "concerns": ["string"],
      "ideal_for": ["string"]
    },
    "sensory": {
      "texture": "string|null",
      "color": "string|null",
      "scent": "string|null"
    },
    "visual_facts": {
      "packaging_type": "string|null",
      "bottle_color": "string|null",
      "applicator": "string|null"
    },
    "market_context": {
      "market": "string",
      "audience_language": "string",
      "tone_target": "string"
    }
  }
}
```

### From planner: vibe config

```json
{
  "vibe_config": {
    "vibe_mode": "funny_storytelling | clean_education | aesthetic_mood | raw_authentic",
    "entertainment_ratio": "pure_entertainment | entertainment_leading | balanced | info_leading",
    "target_market": "vi-VN"
  }
}
```

**Key design point:** This node only reads two vibe knobs from the planner — `vibe_mode` and `entertainment_ratio`. It does not need format preferences, scoring weights, or hook tension settings. Those are for downstream nodes. This node just needs to know "what kind of video" and "entertainment or information first" to translate the vibe into a creative seed.

## 3. Output Schema

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

Four fields. Compare to the Cocoon experiment which output 12+ fields with nested objects.

**What each field does:**

- `creative_angle` — the concept that connects vibe + product. "Confession: minh ghet serum... nhung lo nay bat minh phai thu" or "Giai ma 3 thanh phan nong do cao trong serum bi dao." Downstream nodes build hooks, beats, and shots around this.
- `audience_painpoint` — the specific pain or desire this product touches for this audience. "Mun an li ti, da dau bet, tham mai khong mo." Hook-angle-generator uses this to write relatable hooks.
- `audience_context` — short description of who is watching. "Nu 18-28, da dau, da biet skincare co ban, active tren TikTok VN." Enough for hooks and beats to speak to the right person.
- `vibe_promise` — what the viewer should feel after watching. Not the planner's abstract vibe_mode, but a concrete promise grounded in this product. "Ban se cuoi va thay 'ua serum bi dao nghe di ma hop da that'" or "Ban se hieu ro tai sao 3 thanh phan nay o dung nong do giup da dau." Beat-planner uses this to know what payoff to deliver.

**What was removed and where it went:**

| Old field | Where it lives now |
|---|---|
| `primary_intent` / `secondary_intent` | Planner's `vibe_mode` |
| `viewer_outcome_primary/secondary` | Implicit in `vibe_promise` |
| `platform_hypothesis` | Planner's `target_market` + format-library-matcher |
| `product_visibility` / `hard_sell_level` | Planner config knobs on format-library-matcher |
| `tone_and_style_constraints` | Planner's vibe config, enforced by downstream nodes |
| `non_goals` | Ad-likeness-lint handles this |
| `success_criteria_for_next_steps` | Vibe state contract across nodes |
| `assumptions` | Removed — was defensive padding, not actionable |
| `compliance guardrails` | Truth-constraint-gate handles all compliance downstream |

## 4. Config Knobs the Planner Can Set

This node is slim — only two knobs from the planner, plus one optional.

| Knob | Type | What it controls |
|------|------|-----------------|
| `vibe_mode` | enum | The abstract creative feel to translate into a concrete angle for this product. |
| `entertainment_ratio` | enum | Whether the angle should lead with entertainment or information. Directly shapes creative_angle and vibe_promise. |
| `angle_generation_count` | int (default 1) | How many creative_angle options to generate. Default 1 — the node picks the best. Set to 3 if you want a human to choose (future option). |

### Example: funny genz storytelling + Cocoon serum

```yaml
vibe_mode: funny_storytelling
entertainment_ratio: entertainment_leading
```

Output:
```json
{
  "creative_angle": "Confession: minh ghet skincare... cho den khi ban than di lo serum bi dao vao mat luc 11 dem",
  "audience_painpoint": "Mun an li ti, da dau bet, tham mai khong mo — ma luoi skincare",
  "audience_context": "Nu 18-28, da dau, biet Niacinamide co ban, TikTok VN active, thich content funny hon giao duc",
  "vibe_promise": "Ban se cuoi va thay 'ua serum bi dao nghe di ma hop da that'"
}
```

### Example: clean education + Cocoon serum

```yaml
vibe_mode: clean_education
entertainment_ratio: info_leading
```

Output:
```json
{
  "creative_angle": "Giai ma 3 thanh phan nong do cao trong serum bi dao — tai sao combo nay hop da dau",
  "audience_painpoint": "Da dau mun an, muon hieu thanh phan truoc khi mua serum",
  "audience_context": "Nu 18-28, da dau, solution_aware, da biet Niacinamide va BHA, tim serum phu hop",
  "vibe_promise": "Ban se hieu ro tai sao 3 thanh phan nay o dung nong do giup da dau — va luu lai tham khao"
}
```

### Example: aesthetic mood + Cocoon serum

```yaml
vibe_mode: aesthetic_mood
entertainment_ratio: entertainment_leading
```

Output:
```json
{
  "creative_angle": "Night routine im lang — giot serum chay tren da duoi anh den vang",
  "audience_painpoint": "Met moi ca ngay, muon khoanh khac cham soc ban than buoi toi",
  "audience_context": "Nu 20-30, thich aesthetic content, ASMR, routine toi gian, Instagram Reels",
  "vibe_promise": "Ban se thay binh yen va muon co khoanh khac skincare nhu vay cho minh"
}
```

Same product, three vibes, three completely different creative seeds. The angle, the pain, even the audience context shifts — because the vibe demands a different viewer.

## 5. Vibe Impact Classification

**Classification: vibe-critical — the vibe translator.**

This node does not create content, but it produces the creative seed that all content nodes build from. The `creative_angle` determines what hook-angle-generator hooks around. The `audience_painpoint` determines what is relatable. The `vibe_promise` determines what beat-planner must deliver.

In the Cocoon experiment, this node chose "education_soft_product_support" with creative_angle "Giai ma thanh phan." That single choice made every downstream node produce educational content. The new design prevents this by making the vibe come from the planner — this node translates, not decides.

However, a bad translation can still drift. If the planner says "funny_storytelling" and this node translates it into a boring angle like "Tim hieu serum bi dao cho da dau," the downstream chain will produce flat content despite the funny vibe config. The angle quality matters.

## 6. The 4 Node Analysis Questions

### Q1: How can the node manipulate or normalize its output?

One lever: **creative translation quality.** The node takes an abstract vibe and a product brief and synthesizes a creative seed. The quality depends on how well the LLM connects the vibe's energy to the product's reality. A great translation finds the natural intersection — "confession about hating skincare" is both funny AND about this serum. A weak translation forces the connection — "funny ingredient list" is neither truly funny nor truly educational.

The `entertainment_ratio` constrains the direction. `entertainment_leading` means the angle must lead with the entertainment concept, not the product. `info_leading` means the product insight leads.

### Q2: Should the node manipulate its output, or preserve its direct input?

This node **must translate** — its job is to synthesize two inputs (vibe + product) into something new. It does not preserve the brief, and it does not pass through the vibe config. It creates a bridge.

However, it should not invent product facts. The `audience_painpoint` must come from the brief's `target_user.concerns` and `claims_verbatim`, not from the LLM's imagination. The creative interpretation happens in `creative_angle` and `vibe_promise`, not in the factual fields.

Rule: **create the angle, ground the pain in the brief.**

### Q3: What data should the node extract from raw product information?

This is the one node that reads the brief deeply. It extracts:

- From `brief.target_user`: concerns, skin_type, ideal_for — becomes `audience_painpoint` and `audience_context`
- From `brief.product`: category, name — light product-category awareness for translation
- From `brief.sensory`: texture, scent — potential angle hooks (e.g., "smells like food" for funny vibes)
- From `brief.market_context`: market, tone_target — audience context grounding
- From `brief.ingredients_highlights`: key ingredients — available for education angles
- From `vibe_config`: vibe_mode, entertainment_ratio — the direction to translate toward

### Q4: How does this node's output impact downstream?

| Downstream node | What intent_pack controls |
|---|---|
| truth-constraint-gate | Reads `audience_painpoint` to know which claims need grounding |
| format-library-matcher | Reads `creative_angle` to evaluate format-angle fit |
| hook-angle-generator | Reads all 4 fields — the hook must connect angle + pain + audience + promise |
| beat-planner-shot-compiler | Reads `vibe_promise` to know what payoff the beats must deliver |
| ad-likeness-lint | Reads `creative_angle` to judge whether the output matches the intended concept |

**Drift risk:** Low if the planner's vibe config is clear. The main risk is a weak translation — a boring angle despite a fun vibe. But since format-library-matcher and hook-angle-generator both have their own vibe knobs, a weak angle gets compensated downstream. This node sets the direction but does not have final say on creative execution.

## 7. Anti-patterns Observed

### AP1: The node chose the creative direction instead of translating it

The Cocoon prompt said "for skincare briefs with ingredients, prioritize education_soft_product_support." The node followed that hardcoded rule. There was no external vibe input — the node was both the strategist and the translator. Result: safe, predictable, boring.

**Fix:** Planner provides vibe_mode and entertainment_ratio. This node translates, does not decide.

### AP2: The creative_angle was product-feature-first, not concept-first

The Cocoon output picked "Giai ma thanh phan — Tai sao serum bi dao nay lai hop voi da dau mun hon ban nghi." This is a product-feature angle — it leads with what the product contains. A concept-first angle would lead with a human situation: "Confession: minh ghet skincare..." The product appears inside the concept, not the other way around.

**Fix:** `entertainment_ratio` forces the angle direction. `entertainment_leading` means the concept leads, product follows. `info_leading` means product insight leads. The Cocoon angle would only be correct under `info_leading`.

### AP3: The output was bloated with fields that belong elsewhere

12+ fields including platform_hypothesis, product_visibility, hard_sell_level, success_criteria, assumptions. Most of these are now handled by the planner config or downstream nodes. The bloated output made downstream nodes parse too much, and some fields contradicted or overlapped with each other.

**Fix:** 4 fields. Each one is something only this node can produce.

## 8. Contrasting Behavior Examples

Same product (Cocoon serum), three vibes.

### Vibe A: Funny genz storytelling

```json
{
  "creative_angle": "Confession: minh ghet skincare... cho den khi ban than di lo serum bi dao vao mat luc 11 dem",
  "audience_painpoint": "Mun an li ti, da dau bet, tham mai khong mo — ma luoi skincare",
  "audience_context": "Nu 18-28, da dau, biet Niacinamide co ban, TikTok VN, thich content funny hon giao duc",
  "vibe_promise": "Ban se cuoi va thay 'ua serum bi dao nghe di ma hop da that'"
}
```

### Vibe B: Clean education

```json
{
  "creative_angle": "Giai ma 3 thanh phan nong do cao trong serum bi dao — tai sao combo nay hop da dau",
  "audience_painpoint": "Da dau mun an, muon hieu thanh phan truoc khi mua",
  "audience_context": "Nu 18-28, da dau, solution_aware, da biet Niacinamide va BHA",
  "vibe_promise": "Ban se hieu ro tai sao 3 thanh phan nay o dung nong do giup da dau"
}
```

### Vibe C: Aesthetic mood

```json
{
  "creative_angle": "Night routine im lang — giot serum chay tren da duoi anh den vang",
  "audience_painpoint": "Met moi ca ngay, muon khoanh khac cham soc ban than buoi toi",
  "audience_context": "Nu 20-30, thich aesthetic content, ASMR, routine toi gian, Instagram Reels",
  "vibe_promise": "Ban se thay binh yen va muon co khoanh khac skincare nhu vay cho minh"
}
```

Three completely different creative seeds from the same product. The angle, the pain, even the audience context shifts — because the vibe demands a different viewer.

## 9. Agent-friendly Config Guide

### `vibe_mode` — What kind of video is this?

This is the same vibe_mode the planner sets globally. This node uses it to decide what angle to generate:

- `funny_storytelling` — find the funny, human, absurd connection to this product. Lead with a situation, not a feature.
- `clean_education` — find the insight or knowledge gap this product fills. Lead with the learning.
- `aesthetic_mood` — find the sensory, visual, atmospheric quality of this product. Lead with feeling, not words.
- `raw_authentic` — find the honest, unfiltered take on this product. Lead with personal truth.

### `entertainment_ratio` — Concept-first or product-first?

This knob directly controls creative_angle direction:

- `pure_entertainment` — angle is 100% about the concept/situation. Product may not even appear in the angle description.
- `entertainment_leading` — angle leads with concept, product appears as a natural part of it. "Confession about hating skincare" then serum is the twist.
- `balanced` — angle weaves product and concept equally. "Why these 3 ingredients surprised me."
- `info_leading` — angle leads with product insight. "Decode these ingredients." This is what the Cocoon experiment produced.

Default to `entertainment_leading` for most TikTok content. Only use `info_leading` when the user explicitly wants education-first.

### `angle_generation_count` — How many angles?

Default 1 — the node picks the best translation. Set to 3 if you want to add a human selection step here too (future consideration). For now, keep at 1 since the human gate is after hook-angle-generator, which is the more critical creative decision.

## 10. Vibe State Contract

### Reads

| Vibe dimension | Source | How this node uses it |
|---|---|---|
| `vibe_mode` | planner config | The abstract vibe to translate |
| `entertainment_ratio` | planner config | Whether angle leads with concept or product |

This node reads the least from vibe state — it is early in the chain with minimal upstream creative decisions.

### Writes

| Vibe dimension | What it sets | Who reads it downstream |
|---|---|---|
| `creative_angle` | The concept connecting vibe + product | format-library-matcher (format-angle fit), hook-angle-generator (hook around this concept), beat-planner (beats deliver on this concept) |
| `audience_painpoint` | The specific relatable pain/desire | hook-angle-generator (relatable hooks), truth-constraint-gate (which claims need grounding) |
| `audience_context` | Who is watching | hook-angle-generator (speak to right person), format-library-matcher (platform fit) |
| `vibe_promise` | What viewer should feel after watching | beat-planner (payoff target), ad-likeness-lint (did the video deliver on promise?) |

### Contract rule

Every downstream creative node must serve the `vibe_promise`. If the promise is "you'll laugh," the beats cannot be a dry lecture. If the promise is "you'll understand," the beats cannot be pure comedy with no substance. The vibe_promise is the contract between the video and the viewer — breaking it means the viewer feels baited or confused.

---

**Reference artifacts:**
- Prompt: `test_prompts/intent_outcome_selector_prompt.md`
- Output: `test_prompts/intent_outcome_selector.json`
- Pipeline plan: `.cursor/plans/short-video-pipeline.md`
- Session diagnosis: `test_prompts/session_handoff_2026-04-13.md`
