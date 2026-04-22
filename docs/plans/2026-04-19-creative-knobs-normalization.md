# Creative Config Knobs — Normalization Across Core Nodes

> Part of AiModel-645.2 — Normalize creative config knobs across core nodes
> Parent epic: AiModel-645 — AI planner composes workflows from node guides and configs

## 1. Context & Goals

The AI workflow planner (645.4) composes a `WorkflowDocument` by reading each node's `plannerGuide()` (a `NodeGuide` with a list of `GuideKnob`s) and setting `config` values per node. For this to work, every creative node must expose its **creative levers as explicit, well-named knobs** with:

- a `vibe_mapping` that maps the four vibe modes → a recommended value, and
- (when the workflow must carry the choice into execution) a matching `configRules()` entry and `defaultConfig()` value.

Today `StoryWriterTemplate` is the only creative template that conforms. The other five creative templates (`ScriptWriterTemplate`, `SceneSplitterTemplate`, `PromptRefinerTemplate`, `TrendResearcherTemplate`, `ProductAnalyzerTemplate`) inherit the empty skeleton `plannerGuide()` from `NodeTemplate` — the planner cannot tune them.

Beyond adding knobs, we also need to **harmonize** overlapping levers. `humor_density`, `hook_tension`, `narrative_tension`, `product_emphasis`, `edit_pace`, `cta_softness`, `trend_usage`, `native_tone` have natural homes on multiple nodes; if every node redefines its own scale, the planner will drift.

## 2. The Four Vibe Modes (reference)

Every `vibe_mapping` uses these keys (from `docs/plans/2026-04-15-intent-outcome-selector-framework.md`):

| vibe_mode              | character                                                         |
|------------------------|-------------------------------------------------------------------|
| `funny_storytelling`   | Skit / confession / POV. Entertainment-leading. Humor throughout. |
| `clean_education`      | Ingredient breakdown / how-to. Info-leading. Sincere tone.        |
| `aesthetic_mood`       | Texture / routine / mood reel. Sensory, slow, polished.           |
| `raw_authentic`        | Unpolished confession / user testimony. Emotional, low humor.     |

## 3. Shared-vs-Local Knob Decisions

### 3.1 Canonical ("shared") knobs

These knobs have **identical names, types, and option sets** everywhere they appear. One node owns the creative decision, others **read it as a planner hint** and shape their own output accordingly. This lets the planner set the vibe in one place and propagate it.

| knob                  | canonical owner      | also exposed on                                  | semantic                                                          |
|-----------------------|----------------------|--------------------------------------------------|-------------------------------------------------------------------|
| `humor_density`       | `storyWriter`        | `scriptWriter`, `sceneSplitter`, `promptRefiner` | How much humor lives in the creative output. `none \| punchline_only \| throughout`. |
| `narrative_tension`   | `scriptWriter`       | `storyWriter` (via `story_tension_curve`)        | How tense/dramatic the narrative is. `low \| medium \| high`.      |
| `product_emphasis`    | `scriptWriter`       | `storyWriter` (via `product_appearance_moment`), `sceneSplitter`, `promptRefiner`, `productAnalyzer` | How prominent the product is in the output. `subtle \| balanced \| hero`. |
| `native_tone`         | `scriptWriter`       | `storyWriter` (via `genZAuthenticity`), `trendResearcher` | How native/casual the voice feels. `polished \| conversational \| genz_native \| ultra_slang`. |
| `edit_pace`           | `sceneSplitter`      | `scriptWriter`, `promptRefiner`                  | Scene pacing / cut rhythm. `slow_meditative \| steady \| fast_cut \| rapid_fire`. |
| `cta_softness`        | `scriptWriter`       | `storyWriter` (via `ending_type_preference`)     | How hard the call-to-action pushes. `none \| soft \| medium \| hard`. |
| `trend_usage`         | `trendResearcher`    | `storyWriter`, `scriptWriter`                    | How aggressively to lean on current trends. `ignore \| informed \| leaned_in \| fully_on_trend`. |
| `hook_intensity`      | `scriptWriter`       | `storyWriter` (via `story_tension_curve`)        | How hard the first 3 seconds grab. `low \| medium \| high \| extreme`. |

**Rationale for ownership choices:**
- **`storyWriter` owns `humor_density`** — it is the node that actually writes comedic beats. `sceneSplitter`, `promptRefiner`, `scriptWriter` expose the knob so they inherit the same vibe mapping and can shape their split-points / visual prompts / script tone accordingly, but the *canonical* comedy decision is made at story time. (We considered `sceneSplitter` as owner because scenes could be inserted purely for comedic pacing, but the comedy is a narrative-level decision; scene splits are executional.)
- **`scriptWriter` owns `narrative_tension`, `hook_intensity`, `cta_softness`, `product_emphasis`** — for linear, non-story pipelines (education, mood), `scriptWriter` is the single creative node. `storyWriter` *already* encodes these through `story_tension_curve`, `product_appearance_moment`, `ending_type_preference` — those are story-specific richer forms; the shared knob on `storyWriter` is NOT added to avoid redundancy (see §7 decline list).
- **`sceneSplitter` owns `edit_pace`** — it literally decides how many scenes fit in the run and therefore the cut rhythm.
- **`trendResearcher` owns `trend_usage`** — it researches trends, so it knows the intensity; downstream nodes read it as a hint.

### 3.2 Node-local knobs

These remain private to one node.

| node             | local knob                      | reason                                                          |
|------------------|---------------------------------|-----------------------------------------------------------------|
| `storyWriter`    | `story_tension_curve`           | Story-specific shape (`slow_build \| fast_hit \| rollercoaster`). Not meaningful outside storyWriter. |
| `storyWriter`    | `product_appearance_moment`     | Story-specific (`early \| middle \| twist \| end`).            |
| `storyWriter`    | `ending_type_preference`        | Story-specific resolution shape.                                |
| `storyWriter`    | `story_versions_for_human`      | Operational count for human gate.                               |
| `storyWriter`    | `max_moments`, `target_duration_sec` | Operational duration/count.                                |
| `scriptWriter`   | `structure`                     | Rhetorical framing (`three_act \| problem_solution \| story_arc \| listicle`). Not meaningful elsewhere. |
| `sceneSplitter`  | `max_scenes`                    | Operational count.                                              |
| `sceneSplitter`  | `include_visual_descriptions`   | Operational output-shape toggle.                                |
| `promptRefiner`  | `image_style`, `detail_level`   | Generator-side aesthetic dial.                                  |
| `promptRefiner`  | `aspect_ratio`, `wan_*`         | Downstream API parameters.                                      |
| `trendResearcher`| `market`, `platform`, `language`| Research scope; creator-set per run, not vibe-derived.          |
| `productAnalyzer`| `analysis_depth`                | Analysis verbosity dial.                                        |

## 4. Knob Inventory — Per Node

Legend: `C` = has `configRules()` + `defaultConfig()` entry (planner must set this in workflow JSON for the node to execute correctly), `G` = guide-only (planner-facing hint, read by downstream nodes or consumed as context). The goal is to keep `configRules()` surface small; guide-only knobs are advisory.

### 4.1 `storyWriter` (Script) — CRITICAL vibe impact

Existing knobs keep their current shape. **New guide-only hints** are added so the planner can propagate the canonical vibe knobs into storyWriter as context.

| knob                        | scope | type | options / range | default | effect | funny | clean_edu | aesthetic | raw |
|-----------------------------|-------|------|-----------------|---------|--------|-------|-----------|-----------|-----|
| `story_tension_curve`       | G (existing) | enum | slow_build, fast_hit, rollercoaster | fast_hit | (existing) | fast_hit | slow_build | slow_build | slow_build |
| `product_appearance_moment` | G (existing) | enum | early, middle, twist, end | twist | (existing) | twist | early | middle | middle |
| `humor_density`             | G (existing, canonical) | enum | none, punchline_only, throughout | throughout | How much humor is woven into the story. | throughout | none | none | none |
| `story_versions_for_human`  | G (existing) | int | — | 2 | Human-gate output count. | — | — | — | — |
| `max_moments`               | G (existing) | int | — | 6 | Upper bound on story moments. | — | — | — | — |
| `target_duration_sec`       | G (existing) | int | — | 35 | Target run length. | — | — | — | — |
| `ending_type_preference`    | G (existing) | enum | twist_reveal, emotional_beat, soft_loop, call_to_action | twist_reveal | (existing) | twist_reveal | call_to_action | soft_loop | emotional_beat |
| **`native_tone`** *(new)*   | G | enum | polished, conversational, genz_native, ultra_slang | genz_native | How native/casual the voice feels. Shared with scriptWriter. | genz_native | conversational | polished | ultra_slang |
| **`trend_usage`** *(new)*   | G | enum | ignore, informed, leaned_in, fully_on_trend | leaned_in | How much the story leans on current trends. Shared with trendResearcher. | leaned_in | informed | informed | informed |

**+2 new knobs on storyWriter.** No new `configRules()` entries. StoryWriter already has `emotionalTone`, `genZAuthenticity`, `vietnameseDialect` which are the runtime-material knobs; the new `native_tone` / `trend_usage` are planner-facing hints that will later map into those existing config keys in 645.4.

### 4.2 `scriptWriter` (Script) — CRITICAL vibe impact

`scriptWriter` becomes the **canonical home** for the flat (non-story) creative knobs. It was effectively dumb before — style, structure, hook/cta booleans. The new knobs expose real creative levers the planner must set.

| knob                        | scope | type | options / range | default | effect | funny | clean_edu | aesthetic | raw |
|-----------------------------|-------|------|-----------------|---------|--------|-------|-----------|-----------|-----|
| `structure` *(existing)*    | C | enum | three_act, problem_solution, story_arc, listicle | three_act | Rhetorical framing. | story_arc | problem_solution | story_arc | story_arc |
| `target_duration_sec`       | C *(rename-neutral: existing `targetDurationSeconds` stays; new knob `target_duration_sec` is a guide alias)* | int | 5–600 | 90 | Target video length. | — | — | — | — |
| **`hook_intensity`** *(new)* | C | enum | low, medium, high, extreme | high | How hard the first 3 seconds grab. | high | medium | low | medium |
| **`narrative_tension`** *(new)* | C | enum | low, medium, high | medium | How tense/dramatic the narrative gets. | high | medium | low | medium |
| **`humor_density`** *(new)*  | G | enum | none, punchline_only, throughout | punchline_only | Shared with storyWriter. Shapes script tone. | throughout | none | none | none |
| **`product_emphasis`** *(new)* | C | enum | subtle, balanced, hero | balanced | How prominent the product is. | subtle | hero | subtle | balanced |
| **`cta_softness`** *(new)*   | C | enum | none, soft, medium, hard | medium | CTA aggressiveness. | soft | hard | none | soft |
| **`native_tone`** *(new)*    | C | enum | polished, conversational, genz_native, ultra_slang | conversational | How native/casual the voice feels. Canonical across nodes. | genz_native | conversational | polished | ultra_slang |
| **`edit_pace`** *(new)*      | G | enum | slow_meditative, steady, fast_cut, rapid_fire | steady | Shared with sceneSplitter. Shapes sentence density. | fast_cut | steady | slow_meditative | steady |
| **`trend_usage`** *(new)*    | G | enum | ignore, informed, leaned_in, fully_on_trend | informed | Shared with trendResearcher. | leaned_in | informed | informed | informed |

**+7 new knobs on scriptWriter (5 in `configRules`, 2 guide-only).** `includeHook`/`includeCTA` kept for back-compat — the planner sets `hook_intensity`/`cta_softness` instead; execution code remains unchanged in this task (additive only).

### 4.3 `sceneSplitter` (Script) — CRITICAL vibe impact

Scene-splitting is where narrative pacing becomes cut rhythm. It should expose `edit_pace` as the canonical pacing knob and read `humor_density` and `product_emphasis` as hints.

| knob                        | scope | type | options / range | default | effect | funny | clean_edu | aesthetic | raw |
|-----------------------------|-------|------|-----------------|---------|--------|-------|-----------|-----------|-----|
| `max_scenes` *(existing)*   | C | int | 1–50 | 10 | Upper bound on scene count. | — | — | — | — |
| `include_visual_descriptions` *(existing)* | C | bool | — | true | Whether to emit visualDescription. | — | — | — | — |
| **`edit_pace`** *(new, canonical)* | C | enum | slow_meditative, steady, fast_cut, rapid_fire | steady | Drives scene count + cut rhythm. | fast_cut | steady | slow_meditative | steady |
| **`humor_density`** *(new)* | G | enum | none, punchline_only, throughout | punchline_only | Shared with storyWriter. Permits comedic beat-splits. | throughout | none | none | none |
| **`product_emphasis`** *(new)* | G | enum | subtle, balanced, hero | balanced | Shared with scriptWriter. Shapes whether product gets its own scene. | subtle | hero | subtle | balanced |
| **`scene_granularity`** *(new)* | C | enum | broad, normal, fine | normal | Inverse of min-scene-duration; lets planner request more/fewer cuts. | fine | broad | broad | normal |

**+4 new knobs on sceneSplitter (2 in `configRules`, 2 guide-only).**

### 4.4 `promptRefiner` (Script) — CRITICAL vibe impact

PromptRefiner already carries many Wan-specific knobs. The new additions are vibe-facing:

| knob                        | scope | type | options / range | default | effect | funny | clean_edu | aesthetic | raw |
|-----------------------------|-------|------|-----------------|---------|--------|-------|-----------|-----------|-----|
| `image_style` *(existing)*  | C | string | free-text | "cinematic, high quality, photorealistic" | Aesthetic seed phrase. | — | — | — | — |
| `aspect_ratio` *(existing)* | C | enum | 1:1, 16:9, 9:16, 4:3 | 16:9 | Frame aspect. | — | — | — | — |
| `detail_level` *(existing)* | C | enum | minimal, standard, detailed | standard | Prompt verbosity. | — | — | — | — |
| **`visual_polish`** *(new)* | C | enum | raw_authentic, natural_clean, polished_minimal, hyper_polished | natural_clean | Finish level in the generated prompts. Canonical for visual polish. | natural_clean | natural_clean | polished_minimal | raw_authentic |
| **`mood_palette`** *(new)** | C | enum | warm, cool, neutral, high_contrast, pastel, moody | neutral | Color/lighting family baked into prompts. | warm | neutral | pastel | moody |
| **`humor_density`** *(new)* | G | enum | none, punchline_only, throughout | punchline_only | Shared with storyWriter. Permits comedic visual language. | throughout | none | none | none |
| **`product_emphasis`** *(new)* | G | enum | subtle, balanced, hero | balanced | Shared. Shapes framing of product in prompts. | subtle | hero | subtle | balanced |
| **`edit_pace`** *(new)*     | G | enum | slow_meditative, steady, fast_cut, rapid_fire | steady | Shared with sceneSplitter. | fast_cut | steady | slow_meditative | steady |

**+5 new knobs on promptRefiner (2 in `configRules`, 3 guide-only).** Wan-specific knobs remain as-is.

### 4.5 `trendResearcher` (Script) — CRITICAL vibe impact

Historically positioned as a "research" node with no creative knobs. We add `trend_usage` (the canonical trend-leaning knob) plus `native_tone` as a hint so the brief matches downstream tone.

| knob                        | scope | type | options / range | default | effect | funny | clean_edu | aesthetic | raw |
|-----------------------------|-------|------|-----------------|---------|--------|-------|-----------|-----------|-----|
| `market` *(existing)*       | C | enum | vietnam, global, sea | vietnam | Research scope. | — | — | — | — |
| `platform` *(existing)*     | C | enum | tiktok, youtube, instagram, all | tiktok | Research scope. | — | — | — | — |
| `language` *(existing)*     | C | string | — | vi | Output language. | — | — | — | — |
| **`trend_usage`** *(new, canonical)* | C | enum | ignore, informed, leaned_in, fully_on_trend | informed | How aggressively to mine and surface current trends. | leaned_in | informed | informed | informed |
| **`content_angle_focus`** *(new)* | C | enum | broad, vibe_matched, entertainment_first, info_first | vibe_matched | Constrains the angles the researcher returns. | entertainment_first | info_first | vibe_matched | vibe_matched |
| **`native_tone`** *(new)*   | G | enum | polished, conversational, genz_native, ultra_slang | conversational | Shared. Shapes trend brief phrasing. | genz_native | conversational | polished | ultra_slang |

**+3 new knobs on trendResearcher (2 in `configRules`, 1 guide-only).**

### 4.6 `productAnalyzer` (Input) — NEUTRAL vibe impact

Product analysis is mostly vibe-neutral; it extracts features. But the framing of `suggestedMood` and `selling points` can tilt toward the vibe. We add `product_emphasis` as a guide knob to propagate the planner's intent and `analysis_angle` so the report is written for the downstream vibe.

| knob                        | scope | type | options / range | default | effect | funny | clean_edu | aesthetic | raw |
|-----------------------------|-------|------|-----------------|---------|--------|-------|-----------|-----------|-----|
| `analysis_depth` *(existing)* | C | enum | basic, detailed | detailed | Verbosity of analysis. | — | — | — | — |
| **`analysis_angle`** *(new)* | C | enum | neutral, entertainment_ready, education_ready, aesthetic_ready | neutral | Tilts selling-point wording and suggestedMood toward the vibe. | entertainment_ready | education_ready | aesthetic_ready | neutral |
| **`product_emphasis`** *(new)* | G | enum | subtle, balanced, hero | balanced | Shared. Helps analyzer know which traits to foreground. | subtle | hero | subtle | balanced |

**+2 new knobs on productAnalyzer (1 in `configRules`, 1 guide-only).**

## 5. Planner Contract

- **Planner-tunable** (per run, vibe-derived): `humor_density`, `narrative_tension`, `hook_intensity`, `cta_softness`, `product_emphasis`, `native_tone`, `edit_pace`, `trend_usage`, `visual_polish`, `mood_palette`, `content_angle_focus`, `analysis_angle`, and all the existing vibe-mapped knobs on storyWriter.
- **Creator-set** (per project, not planner-derived): `market`, `platform`, `language`, `aspect_ratio`, `target_duration_sec`, `max_scenes`, `max_moments`, `wanFormula`, `wanAspectRatio`, `characterTags`, `provider`, `apiKey`, `model`.
- **Hand-off boundary (645.4):** The planner reads `plannerGuide()` for each node, walks the knob list, consults each knob's `vibeMapping` with the active `vibe_mode`, and writes the resolved value into `workflow.nodes[*].config`. Knobs with `scope=G` are emitted into a sibling `_plannerHints` block on the node config (to be wired in 645.4; out of scope here).

## 6. Migration / Back-compat

- **Nothing removed, nothing renamed.** All additions are purely additive to `plannerGuide()`, `configRules()`, and `defaultConfig()`.
- Existing config keys like `storyFormula`, `emotionalTone`, `productIntegrationStyle`, `genZAuthenticity`, `vietnameseDialect`, `style`, `structure`, `includeHook`, `includeCTA`, `imageStyle`, `aspectRatio`, `detailLevel`, `analysisDepth`, `maxScenes`, `includeVisualDescriptions`, `market`, `platform`, `language` are **preserved**. Existing tests continue to pass.
- Execution code is NOT modified. Runtime behavior is unchanged unless a downstream author wires the new config keys into prompt construction (out of scope for 645.2).
- **Shared-knob naming convention:** snake_case. The existing storyWriter knobs already use snake_case. We align new knobs to the same convention even when they coexist with camelCase legacy keys (`targetDurationSeconds` vs `target_duration_sec`). This is intentional: snake_case is the planner/LLM-facing name; the legacy camelCase config stays for the existing runtime.
- **LG2 boundary:** `provider`, `apiKey`, `model` are untouched. All six templates retain those keys as-is; LG2 will migrate them behind a trait in a follow-up bead.

## 7. Declined / Deferred

The following were considered but **not added**, to avoid knob sprawl:

1. **`humor_density` on scriptWriter as `configRules()` entry.** The knob is present in `plannerGuide()` but NOT added to `configRules()`/`defaultConfig()`. The scriptWriter execution path already encodes humor implicitly via `style`. Adding a runtime key would duplicate that surface. Flagged for 645.3 if planner output shows humor drift in scripts.
2. **`narrative_tension` on storyWriter.** StoryWriter already has `story_tension_curve` (a richer enum). Adding the shared knob would be redundant; storyWriter's guide describes `story_tension_curve` as the story-specific form of tension. The planner maps `narrative_tension=high` → `story_tension_curve=fast_hit` in 645.4.
3. **`product_emphasis` on storyWriter.** Redundant with existing `product_appearance_moment` + `productIntegrationStyle`. Planner-side translation handles the mapping.
4. **`cta_softness` on storyWriter.** Redundant with `ending_type_preference`. Same translation rationale.
5. **`hook_intensity` on storyWriter.** StoryWriter consumes the hook from upstream `humanGate`; it does not generate the hook itself. The hook tension is set by the (future) `hookAngleGenerator` node. Exposing `hook_intensity` on storyWriter would imply it can still shape the hook, which is misleading.
6. **`edit_pace` as `configRules()` on scriptWriter.** scriptWriter does not split scenes; it produces narration. Keeping it guide-only on scriptWriter prevents it from being interpreted as a runtime parameter.
7. **`trend_usage` as `configRules()` on storyWriter/scriptWriter.** Trend-leaning is operationalized inside `trendResearcher` (which owns the actual research call). Adding it as a runtime key on downstream nodes would imply they consult an external trend API mid-execution; they don't.
8. **Visual knobs on sceneSplitter** (`mood_palette`, `visual_polish`). These belong on `promptRefiner` where the visual prompt is actually written; sceneSplitter only describes scene content, not style.
9. **Language knob as vibe-mapped.** Language is creator-set (Vietnamese-first in this product). No vibe mapping applied.

### Dead-knob flags (follow-up for the 645.4 / cleanup bead)

- `style` on scriptWriter is free-text; with the new structured knobs it may become effectively dead. Candidate for removal once the planner populates `narrative_tension` + `native_tone` + `humor_density`.
- `includeHook` / `includeCTA` are booleans that `hook_intensity` / `cta_softness` subsume. Once the planner reliably sets the new enums, the booleans can be derived.
- `imageStyle` on promptRefiner overlaps with the new `visual_polish` + `mood_palette`. Keep all three for now; revisit after 645.4.
- `emotionalTone`, `productIntegrationStyle`, `genZAuthenticity` on storyWriter partially overlap the new shared hints. NOT removed in this task.

## 8. Follow-up for 645.4 (planner implementation)

Knobs whose `vibe_mapping` is our best current guess but needs verification via actual planner runs:

- **`mood_palette`** on promptRefiner — mapping per vibe is educated guess; `aesthetic_mood=pastel` may need to be `cool` or `warm` depending on the brief's product. Flag for A/B once the planner is running.
- **`content_angle_focus`** on trendResearcher — the mapping of `aesthetic_mood → vibe_matched` is defensive; the right value may be `entertainment_first` for high-aesthetic categories like beauty/fashion.
- **`edit_pace`** default across vibes — `funny_storytelling=fast_cut` is solid; for `clean_education` we picked `steady` but some educational formats (ingredient breakdown) want `fast_cut` to keep attention. Flag for 645.4 to consider format-archetype-conditional mapping.
- **`analysis_angle`** on productAnalyzer — whether `raw_authentic → neutral` is correct or should lean `entertainment_ready` (raw-authentic creators often still want a punchy hook in the selling points). Flag.
- **`scene_granularity`** on sceneSplitter — we did not give it a vibe mapping because granularity is arguably a function of `edit_pace`; the planner may derive it instead of setting it independently. Leave as configurable, no vibe_mapping, observe planner behavior in 645.4.

No knob was added with full confidence in its vibe mapping across *all four* modes — the mappings are defensible defaults, not production-tested values. 645.4 should treat these as tunables.

---

*End of design note.*
