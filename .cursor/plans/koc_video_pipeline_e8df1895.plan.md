---
name: Short video pipeline
overview: Review and redesign the current AI short-form video pipeline so it can generate native-feeling short videos across multiple intents, including entertainment, lifestyle, storytelling, education, and optional product-led content. The plan focuses on architecture, node responsibilities, quality controls, continuity, and prompt design rather than implementation details.
todos:
  - id: define-intent-and-constraint-layer
    content: Define the brief-ingest, intent-selection, and truth/constraint schema that every downstream node must consume.
    status: pending
  - id: define-context-and-format-memory
    content: Design the context builder and curated format library with metadata for tone, objective fit, and production feasibility.
    status: pending
  - id: split-creative-middle-layer
    content: Restructure concept generation into hook/angle, objective-fit lint, beat planner, and shot prompt compiler.
    status: pending
  - id: design-continuity-and-qc
    content: Add continuity binding plus shot-level and whole-video evaluation loops before final export.
    status: pending
  - id: design-native-packaging
    content: Specify the edit, audio, caption, and packaging rules that create native short-form feel.
    status: pending
isProject: false
---

# Short-Form Video Pipeline Architecture Review

## Core Judgment

- Your current 6-node pipeline is still a good base because it separates understanding, concepting, scene design, rendering, and editing.
- The bigger issue is not only that it is linear. It is also biased toward product-led output. A stronger system should support multiple video intents, with product promotion as an optional mode rather than the default frame.
- The main missing layers are: intent selection, truth and constraint grounding, hook design, format fit scoring, shot feasibility checks, continuity control, per-shot QC, and whole-video evaluation.

## What Is Strong In The Current Pipeline

- The pipeline already separates concept development from production, which is the right architectural direction.
- `Trend Matcher` is useful if it retrieves reusable short-form formats rather than chasing shallow trends.
- `Angle Crafter` is the right place to turn a brief into a concrete scenario or point of view.
- Separating `Scene Writer` from `Assembly` is smart because creative direction and editorial packaging are different jobs.
- The current flow can be generalized beyond product content without needing a complete rebuild.

## What Is Weak Or Missing

- No dedicated `Intent Selector`. The system should first decide what kind of short video it is making: relatable, entertaining, educational, aesthetic, story-led, product-supporting, or explicitly promotional.
- No `Truth And Constraint Gate`. If the source includes products, factual topics, canon details, or brand restrictions, downstream nodes need one grounded source of truth.
- No dedicated `Hook` layer. In short-form video, the first 1-3 seconds are still the real entry point.
- No `Objective-Fit Linter`. The system needs an explicit way to reject outputs that feel too ad-like, too vague, too expository, or misaligned with the creative objective.
- No `Feasibility Filter`. LLMs often write scenes that current video models cannot render convincingly.
- No `Continuity Binder`. Multi-shot video generation will drift on face, outfit, product details, lighting, and environment.
- No `QC Loop`. A single bad clip can poison the whole output.
- No `Learning Loop`. The system should remember which hook structures, scene types, and edit patterns actually survive QC and perform well.

## Biggest Failure Points That Make Output Feel AI-Generated Or Ad-Like

- The video has no clear reason to exist beyond “show something.”
- The hook sounds synthetic or generic rather than human, surprising, funny, tense, or specific.
- The tone does not match the chosen objective. For example, a casual story gets written like an ad, or an educational short gets written like a mood reel.
- Scenes are too clean, symmetrical, or cinematic for native short-form viewing behavior.
- Video prompts ask for complex hand-object interactions, readable labels, precise lip sync, or multi-person acting that current models handle poorly.
- Character appearance drifts across shots, which breaks trust immediately.
- Text overlays explain too much and flatten the pacing.
- Music and VO are bolted on late, so the video feels assembled rather than performed.
- The ending feels like a generic wrap-up rather than a payoff, reveal, emotional beat, or loop.

## Recommended Restructure Of Your Existing Nodes

- Replace `Product Analyser` with `Brief And Context Analyser`, with two sub-functions:
  - `Source Context Extractor`
  - `Truth And Constraint Gate`
- Keep `Trend Matcher`, but make it a curated `Format Library Retriever` with metadata like emotional engine, narrative pattern, production feasibility, native feel, audio style, and optional product fit.
- Keep `Angle Crafter`, but make it generate hook-first angles based on the selected intent, not only on product placement logic.
- Split `Scene Writer` into:
  - `Beat Planner`
  - `Shot Prompt Compiler`
- Expand `Video Generation` into a multi-candidate render stage with retries and scoring.
- Upgrade `Assembly` into `Edit Grammar, Audio, Captioning, And Packaging`.
- Add two new gates:
  - `Objective Fit And Native Lint`
  - `Shot QC And Continuity QC`

## Recommended Pipeline If Building From Scratch

```mermaid
flowchart LR
briefIngest[BriefIngest] --> intentSelect[IntentAndOutcomeSelector]
intentSelect --> constraintGate[TruthAndConstraintGate]
constraintGate --> formatMatch[FormatLibraryMatcher]
formatMatch --> hookAngle[HookAndAngleGenerator]
hookAngle --> objectiveLint[ObjectiveFitAndNativeLint]
objectiveLint --> beatPlan[BeatPlanner]
beatPlan --> continuity[ContinuityBinder]
continuity --> promptSpec[ShotPromptCompiler]
promptSpec --> render[MultiCandidateVideoGeneration]
render --> shotQc[ShotAndContinuityQC]
shotQc --> editPack[EditAudioCaptionPackaging]
editPack --> finalEval[WholeVideoEvaluator]
finalEval --> learning[CreativeLearningMemory]
shotQc -->|rewriteOrRerender| hookAngle
```



## Node-By-Node Responsibilities

1. `Brief Ingest`

- Normalize raw input into topic, product if any, core message, platform, audience, constraints, references, tone cues, and desired outcome.

1. `Intent And Outcome Selector`

- Decide what kind of short video is being made.
- Example intents: entertain, relate, explain, aesthetic, story-led, community-building, soft product support, direct product introduction.
- Output the primary viewer outcome: stop, feel, learn, save, share, comment, or click.

1. `Truth And Constraint Gate`

- Produce one grounded context sheet that every later node must use.
- If the source includes products or claims, output allowed facts, risky claims, forbidden phrasing, and visually provable details.
- If the source is non-product content, output canon details, factual anchors, tone guardrails, taboo topics, and non-negotiable constraints.

1. `Format Library Matcher`

- Retrieve 3-5 proven short-form format archetypes from a curated library.
- Rank by native fit, intent fit, emotional relevance, production feasibility, and shelf life.

1. `Hook And Angle Generator`

- Generate multiple angles where the first seconds establish the reason to watch.
- Each angle should specify point of view, tension or curiosity trigger, scenario logic, reveal strategy, and expected viewer payoff.

1. `Objective Fit And Native Lint`

- Reject outputs that mismatch the chosen intent.
- Penalize content that feels too promotional, too vague, too polished, or too exposition-heavy for the intended mode.

1. `Beat Planner`

- Turn one angle into 4-6 beats with a strict time budget.
- Each beat should answer: what the viewer understands now, what new question or emotion is opened, and what payoff or escalation arrives next.

1. `Continuity Binder`

- Create a compact continuity pack for character, outfit, room, lighting, camera style, props, and any source objects that must remain stable.
- Define what must remain fixed and what may vary.

1. `Shot Prompt Compiler`

- Translate each beat into video-model-friendly specs.
- Separate creative intent from rendering constraints: subject, action, environment, camera, lighting, motion intensity, transition, reference images, continuity tokens, and negative constraints.

1. `Multi-Candidate Video Generation`

- Render multiple candidates per shot, not one.
- Prefer image-conditioned or reference-driven generation when character or object consistency matters.

1. `Shot And Continuity QC`

- Evaluate hands, faces, objects, lighting, text legibility, action plausibility, and cross-shot continuity.
- Reject weak shots early and send them back for rewrite or rerender.

1. `Edit Audio Caption Packaging`

- This is where native short-form feel is created.
- Add jump-cut rhythm, handheld-feeling pacing, room tone, music ducking, timing-aware captions, safe-zone text placement, and platform-native overlay behavior.

1. `Whole Video Evaluator`

- Score final output for native feel, coherence, intent fit, continuity, sound fit, and likely retention.
- Export multiple hook variants when possible.

1. `Creative Learning Memory`

- Store which formats, hooks, intent types, beat structures, and visual styles passed QC and performed best.

## Character Consistency Strategy

- Keep one primary subject focus per video unless the concept truly requires more.
- Use a continuity pack with 5-7 invariant traits: age band, hair, outfit, one accessory, environment palette, object rules, and camera style.
- Use anchor stills or a reference face sheet where the model/API supports it.
- Reuse identical identity tokens across all shot specs.
- Favor partial-face, over-shoulder, hands, mirror, and side-angle shots when full-face consistency is not essential.
- Keep location and wardrobe stable across the whole video; vary only the action and framing.
- Prefer short chained shots or reference-conditioned shots instead of totally independent renders.
- If needed, stabilize identity in post, but do not rely on post to rescue a weak concept.

## Closing The Gap Between AI Visuals And Native Short-Form Authenticity

- Do not aim for cinematic polish. Aim for believable phone-camera imperfection.
- Use everyday environments, uneven framing, slight exposure shifts, casual handheld motion, and natural clutter.
- Prefer edit-driven energy over model-driven spectacle.
- Let the text overlay feel like a native part of the video, not an explanatory layer pasted on top.
- If a product or message exists, let it arrive as context, payoff, or prop rather than forced exposition.
- Add audio early in the design process, not only in post.
- Treat “rawness” as a controlled layer added by prompt design and editorial packaging, not as a random downgrade filter.

## Prompt Design Guidance For Key Nodes

- `Brief And Context Analyser`: “Identify the platform, objective, topic, source assets, tone cues, constraints, and whether product or promotion is central, supporting, or absent.”
- `Truth And Constraint Gate`: “If factual claims exist, preserve them conservatively. If not, output story constraints, canon details, and what must remain true.”
- `Format Matcher`: “Rank formats by intent fit, native feel, production feasibility, and how naturally the core idea fits the format.”
- `Hook And Angle Generator`: “Open with tension, curiosity, contrast, humor, confession, or pattern interrupt. Only foreground the product or message if the selected objective requires it.”
- `Objective Lint`: “Reject tone that mismatches the intended mode: ad-like when the goal is story or relatability, vague when the goal is explanation, or flat when the goal is entertainment.”
- `Beat Planner`: “Each beat must introduce one new reason to keep watching. Avoid scenes that only restate the same point.”
- `Shot Prompt Compiler`: “Prefer actions current video models can render reliably. Penalize fine text readability, complex manipulation, dense crowds, or precise lip-sync dependence.”
- `Edit Packaging`: “Captions should add subtext or humor, not narrate every visible action.”

## Practical Principle To Anchor The Whole System

- Optimize for `this feels like a real short video with a clear point of view`, not `this is a generated asset searching for a reason to exist`.
- In system terms, the highest-leverage additions are intent selection, truth and constraint grounding, hook design, continuity control, QC loops, and edit grammar.

## External Design Constraints

- Short-form platforms still reward vertical-native, sound-aware, and not-overly-polished creative.
- Current multi-shot text-to-video systems still face a real trade-off between identity preservation and motion fidelity, so continuity must be designed into the pipeline rather than assumed from prompting alone.

