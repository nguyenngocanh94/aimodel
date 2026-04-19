# Planner Benchmark Fixtures

> aimodel-645.6 — Build benchmark fixtures for contrasting video vibes
> Part of epic aimodel-645 (AI planner composes workflows from node guides and configs)

## Purpose

Encode ground-truth creative intents so planner quality can be judged
against concrete targets. Each fixture is a tuple
`(brief, expectedVibeMode, expectedCharacteristics)` that a planner output
can be scored against.

This exists because the 645.9 diagnosis of the Cocoon pipeline run showed
that **vibe drift was invisible**: each node independently did "the right
thing" for a product-centered ad, and nothing in the system encoded what
a soft-sell creator-native output should look like. Fixtures make the
failure mode detectable.

## Location

- Value object + loader: `backend/app/Domain/Planner/BenchmarkFixture.php`, `BenchmarkFixtureLoader.php`
- Fixture files: `backend/tests/Fixtures/PlannerBenchmarks/*.php`
- Tests: `backend/tests/Unit/Domain/Planner/BenchmarkFixtureLoaderTest.php`

## Storage format — PHP, not JSON

Chose PHP arrays per-file because:

1. **Autoloadable + type-checked.** The loader `require`s each file; a typo in
   a key name surfaces via `BenchmarkFixture::fromArray()` instead of silent
   JSON truthiness.
2. **Inline comments.** Fixture files carry 30–60 lines of rationale
   explaining why each knob value is expected. JSON cannot hold those without
   sidecar files.
3. **Constant references later.** When 645.4 lands node-type constants (e.g.
   `StoryWriterTemplate::TYPE`), fixture files can switch from string literals
   to constants without a schema migration.
4. **No cross-language consumer yet.** 645.5 (drift-eval) and 645.8
   (integration tests) are both PHP. If a Python/JS consumer appears later,
   `BenchmarkFixture::toArray()` already exists for JSON export.

## The four fixtures

| ID | Vibe mode | One-line intent |
| -- | --------- | --------------- |
| `cocoon-soft-sell` | `funny_storytelling` | Story-driven, product as twist reveal (the Cocoon anti-pattern) |
| `cocoon-direct-intro` | `clean_education` | Ingredient breakdown, product hero early, CTA end (control fixture) |
| `milktea-aesthetic-mood` | `aesthetic_mood` | Visual flow, no dialogue, slow-paced, no CTA |
| `chocopie-raw-authentic` | `raw_authentic` | Talking-head UGC, no hook, minimal polish, nostalgic |

### Fixture A — cocoon-soft-sell

Rationale: direct encoding of the 645.9 Cocoon drift diagnosis. The real
pipeline run for this brief drifted into an ingredient-breakdown TVC because
no node carried vibe signal. Fixture A's `antiPatterns` explicitly list
"ingredient breakdown monologue" and "direct product hero shot in first 3s"
as failure modes — drift-eval can pattern-match the planner's output against
these strings.

### Fixture B — cocoon-direct-intro

Rationale: control case. Same product as A, but brief structurally demands
product-centered content. Without this fixture, a naive planner could pass
645.5 by hardcoding "never show product early" — B ensures the planner must
actually read vibe intent from the brief, not apply a global rule.

### Fixture C — milktea-aesthetic-mood

Rationale: tests drift in the opposite direction from A. If the planner
defaults to `storyWriter` + humor because those nodes are most developed,
this fixture catches it — the brief explicitly rejects storyline and
dialogue.

### Fixture D — chocopie-raw-authentic

Rationale: the hardest vibe. Raw-authentic content is defined as much by
what it *lacks* (polish, hooks, production value) as by what it contains.
Tests whether the planner can actively suppress defaults from nodes designed
for polished output.

## How aimodel-645.5 (drift-eval) consumes these

The drift evaluator iterates every fixture and runs the planner on
`$fixture->brief`. It reads:

- `$fixture->expectedVibeMode` — the axis each heuristic scores against
  (funny vs. educational vs. mood vs. raw).
- `$fixture->expectedCharacteristics` — pass/fail signals extracted from the
  planner's chosen graph + node configs. Example fields:
  - `productMentionsFirstThreeSeconds` (bool) — scan hook / first-shot config.
  - `humorPresent` (bool) — check storyWriter.humor_density != 'none' etc.
  - `narrativeStructure` (string) — map from selected node + formula knob.
  - `adLikenessMaxScore` / `adLikenessMinScore` (float 0–1) — the scoring
    scale 645.5 defines; a heuristic combining ingredient-listing,
    hero-shot-timing, CTA-presence, voiceover-register.
  - Vibe-specific: `aestheticCoherenceMinScore`, `feelsUgcMinScore`,
    `productionPolishMaxScore`.
- `$fixture->antiPatterns` — free-text failure modes. 645.5 uses these as
  LLM-judge prompts or regex anchors on the rendered plan JSON.
- `$fixture->forbiddenNodes` — quick-fail: if the planner's selected node
  types intersect the leading tokens of this list, drift-eval returns a hard
  fail before even scoring characteristics.

Scoring scales are 645.5's responsibility — fixtures provide the *targets*,
not the formulas. `adLikenessMaxScore: 0.35` in fixture A and
`adLikenessMinScore: 0.4` in fixture B define the goal posts; 645.5 decides
how to compute the actual ad-likeness number.

## How aimodel-645.8 (integration tests) consumes these

For each fixture the integration test:

1. Calls the planner with `$fixture->brief`.
2. Asserts every `$fixture->expectedNodes` type is present in the composed
   graph.
3. Asserts no node type whose leading token appears in
   `$fixture->forbiddenNodes` is present.
4. For each `$fixture->expectedKnobValues` entry `"node.knob" => value`,
   locates the node in the graph and asserts the resolved config value
   matches.
5. Asserts the `vibe_mode` metadata attached to the plan equals
   `$fixture->expectedVibeMode`.

Assertion pattern:

```php
$plan = $planner->plan($fixture->brief);
foreach ($fixture->expectedNodes as $type) {
    $this->assertTrue($plan->hasNodeOfType($type), "{$fixture->id}: missing {$type}");
}
foreach ($fixture->expectedKnobValues as $path => $expected) {
    [$type, $knob] = explode('.', $path, 2);
    $this->assertSame($expected, $plan->nodeOfType($type)->config[$knob] ?? null);
}
```

## Known limits and follow-ups

1. **`moodSequencer` node does not exist yet.** Fixture C depends on it.
   Until 645.4 registers the node (with the knobs from
   `docs/plans/2026-04-15-mood-sequencer-framework.md`), any integration test
   against fixture C will fail at the node-presence assertion. 645.5 can
   still consume fixture C's `expectedCharacteristics` because those describe
   the *output*, not the node structure. **Flag for 645.4.**

2. **`raw_authentic` knobs on image/video nodes are aspirational.** Fixture D
   references `imageGenerator.stylePreset = raw_phone_camera` and
   `videoComposer.polishLevel = minimal`, which are planned knobs per the
   645.9 raw-authentic framework discussion but not yet in
   `ImageGeneratorTemplate` / `VideoComposerTemplate`. **Flag for 645.4.**

3. **`scriptWriter` is not yet vibe-aware.** Fixture B assumes
   scriptWriter exposes `tone = educational` and `includeCallToAction` knobs.
   It currently does not expose them with vibe mapping. **Flag for 645.4.**

4. **Scoring scales are placeholders.** `adLikenessMaxScore: 0.35` etc. are
   calibration targets, not derived numbers. 645.5 will tune them after the
   first drift-eval pass produces real scores.

5. **Only one brief per vibe.** Four fixtures is the minimum to catch
   cross-vibe drift. Longer term, two fixtures per vibe (different product
   categories) would guard against the planner overfitting to product-specific
   wording (serum vs. milk tea vs. snack).

6. **Vietnamese-only briefs.** Deliberate — matches the current target
   market. English fixtures would be a separate follow-up once the planner
   ships a localized variant.

## Acceptance checklist

- [x] 4 fixture files committed in `backend/tests/Fixtures/PlannerBenchmarks/`.
- [x] Loader at `backend/app/Domain/Planner/BenchmarkFixtureLoader.php`
      with `all()`, `byId()`, `byVibeMode()`.
- [x] Value object `BenchmarkFixture` is `final readonly`.
- [x] Tests green: `docker exec backend-app-1 php artisan test --filter=BenchmarkFixtureLoaderTest`.
- [x] `grep -rn "funny_storytelling\|clean_education\|aesthetic_mood\|raw_authentic" backend/tests/Fixtures/PlannerBenchmarks/` hits all four vibes.
- [x] Design doc committed (this file).
