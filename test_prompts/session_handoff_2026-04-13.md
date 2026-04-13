# Session Handoff — Vibe-First Short Video Workflow

## Context

- Project: AI video workflow planning and prompt-chain execution (manual LLM flow).
- Product used in run: **Cocoon Tinh chất bí đao N7**.
- Primary issue identified: outputs felt too much like TV ads (boring hooks, no twist, product-heavy framing).
- Agreed direction: keep existing workflow runner, add new nodes for vibe control.

## What We Completed

### Prompt-chain steps executed

1. Step 1 — Brief ingest
2. Step 2 — Intent and outcome selector
3. Step 3 — Truth and constraint gate
4. Step 4 — Format library matcher
5. Step 5 — Hook and angle generator
6. Step 7 — Beat planner + shot prompt compiler
7. Step 7.5 — Render-pack split strategy for video API `< 6s`

### Key generated files

- `test_prompts/brief_context_analytics.json`
- `test_prompts/intent_outcome_selector.json`
- `test_prompts/trust_constrant_gate.json`
- `test_prompts/formated_library_matcher.json`
- `test_prompts/objective_fit_native_lint.json` (contains `hook_pack`)
- `test_prompts/beat_planner_shot_prompt_complier.json` (contains `production_pack`)
- `test_prompts/split_video.json` (render split artifacts)

### Prompt templates created

- `test_prompts/beat_planner_shot_prompt_compiler_prompt.md`
- `test_prompts/edit_audio_camption_packing_finalizer_prompt.md`

## Important Clarifications Captured

- Step 8 (`Edit/Audio/Caption Packaging`) does **not** generate video by itself.
- Video generation API is called after Step 7/7.5 using shot/micro-clip render prompts.
- For API limit `< 6s`, clips should be split to 3–5s micro-clips.

## Diagnosed Root Cause (Why video felt ad-like)

- Main drift came from Step 4 + Step 5:
  - format selection favored ingredient-explainer style too strongly,
  - hooks focused on concentration numbers (informative but low drama).
- Step 6 lint focused on compliance but lacked anti-boring/novelty/twist enforcement.
- Step 7 then faithfully executed a linear structure with weak narrative tension.

## Strategic Direction Agreed

- Move to **vibe-first design**:
  - user gives raw expectation,
  - AI configures workflow + node configs,
  - nodes pass and enforce a shared `vibe_state`.

## Planning Artifact Created

- Plan file: `/Users/anh/.cursor/plans/vibe-first_workflow_planning_844a4e15.plan.md`

Core idea in plan:

- each node outputs both `content_output` and `vibe_state`,
- vibe factors are explicitly scored/gated to prevent ad drift.

## Proposed New Nodes (one bead each)

1. `workflow-designer`
2. `vibe-profile-builder`
3. `hook-novelty-scorer`
4. `ad-likeness-lint`
5. `twist-injector`
6. `native-edit-grammar`
7. `creative-memory-updater`
8. shared `vibe_state` schema contract task
9. vibe evaluator metrics task
10. integration tests task

## Next Steps On Resume

1. Re-run Step 4/5 prompts with anti-ad + twist-heavy constraints.
2. Add explicit novelty/tension gates in Step 6.
3. Regenerate Step 7 with forced beat structure:
   - Hook -> Friction -> Reframe/Twist -> Payoff -> Soft CTA.
4. Convert Step 7 to Step 7.5 micro-clips (`< 6s`) for video API.
5. Run Step 8 packaging after render candidates are selected.

## Notes

- Keep compliance guardrails from Step 3 unchanged.
- Keep soft CTA policy.
- Avoid relying on readable label text in generated clips.
- Prioritize creator-native messiness and conflict-driven hooks for TikTok/Reels.
