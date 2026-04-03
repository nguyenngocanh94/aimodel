# E2E Test Plan Seed

## What
A comprehensive Playwright E2E test suite for the AI Video Workflow Builder. Tests verify that every user journey from the product plan works end-to-end in a real browser — from opening the app to exporting a workflow.

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
- Crash recovery
- Multi-tab safety
- Accessibility smoke tests

## Tech Stack
- Playwright (already in project)
- Testing against http://localhost:5173 (Vite dev server)
- No external services needed (all mock execution)

## Non-goals
- NOT visual regression testing (no pixel comparison)
- NOT performance benchmarking (separate concern)
- NOT testing real AI provider integrations (v2)

## Reference
- Plan section 4: User Journeys (6 journeys)
- Plan section 19.5: E2E Test Scenarios (12 scenarios)
- Plan section 19.7: Coverage guidance
