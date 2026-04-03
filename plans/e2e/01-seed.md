# E2E Test Plan Seed

## What
A comprehensive Playwright E2E test suite for the AI Video Workflow Builder. Tests verify that every user journey works end-to-end in a real browser — from opening the app to exporting a workflow.

## Who
Developers and CI pipelines. Tests run automatically on every PR and before release.

## Success
All 6 user journeys from the plan are automated. A new developer can run the full suite in under 2 minutes and know with confidence that the product works.

## Scope
- 12 Playwright scenarios (from plan section 19.5)
- 6 user journeys (from plan section 4.1-4.6)
- Happy paths + error paths + edge cases
- Mock execution flows (no real AI providers)
- Persistence round-trips (save, reload, import/export)
- Crash recovery and recovery dialog
- Multi-tab safety (BroadcastChannel soft-lock)
- Accessibility smoke tests
- Media preview verification (image thumbnails in nodes, video preview player)
- Keyboard shortcuts (12 shortcuts from design system section 16)
- All interactions via data-testid selectors (from design system section 18)

## Tech Stack
- Playwright (already in project)
- Testing against http://localhost:5173 (Vite dev server)
- No external services needed (all mock execution)

## Non-goals
- NOT visual regression testing (no pixel comparison)
- NOT performance benchmarking (separate concern)
- NOT testing real AI provider integrations (v2)

## Reference
- `plans/06-final-plan.md` — Section 4: User Journeys, Section 19.5: E2E Scenarios
- `plans/ui-ux/04-final-design-system.md` — Section 16: Keyboard Shortcuts, Section 17: Accessibility, Section 18: data-testid Strategy
- `designs/workflow-builder-ui.pen` — 5 screen designs (Main Editor, Empty State, Error State, Data Inspector, Recovery Dialog)

## Design Context
The app is a dark-mode three-panel layout:
- Left: Node library (searchable, categorized by Input/Script/Visuals/Audio/Video/Utility/Output)
- Center: React Flow canvas with custom node cards showing inline media previews
- Right: Inspector with 5 tabs (Config, Preview, Data, Validation, Meta)
- Top: Run toolbar (Run Workflow, Run Node, From Here, Cancel, status chip)

Node cards show:
- Category accent line (blue=script, violet=visuals, amber=video)
- Status badges (success/running/error/pending)
- Inline image thumbnail grids (for image gen nodes)
- 16:9 video preview with play controls (for video composer)
- Typed port handles with labels

All interactive elements use data-testid attributes per `plans/ui-ux/04-final-design-system.md` section 18.
