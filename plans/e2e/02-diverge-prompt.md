# E2E Test Plan — Diverge Prompt

> Send this to GPT Pro AND one other model (Gemini or Claude). Save results as `02-compete-gpt.md` and `02-compete-other.md`.

---

I'm building a comprehensive Playwright E2E test suite for an AI Video Workflow Builder — a browser-based visual pipeline editor where users drag-drop nodes, connect them, and run mock AI video generation workflows.

Below is the seed for the test plan, followed by the relevant sections from the product plan (user journeys and specified test scenarios).

## SEED

A comprehensive Playwright E2E test suite. Tests verify every user journey works end-to-end in a real browser. 12 planned scenarios + 6 user journeys. All mock execution (no real AI). Runs in under 2 minutes.

Tech: Playwright, Vite dev server at localhost:5173, React + React Flow canvas.

## PRODUCT CONTEXT

### The 6 User Journeys

**Journey 1: Create A Short-Form Video Workflow From Scratch**
User opens app → sees node library with categories (Input, Script, Visuals, Audio, Video, Utility, Output) → drags User Prompt, Script Writer, Scene Splitter, Image Generator, Video Composer, Final Export → connects them in sequence → selects Scene Splitter, sees inspector with config form → changes sceneCountTarget → preview updates → clicks "Run Workflow (Mock)" → nodes animate through pending/running/success → inspects edge data between nodes → sees payload details.

**Journey 2: Diagnose A Broken Connection**
User loads saved workflow → one edge highlighted red → selects edge → Data Inspector shows source schema, target schema, compatibility result, why invalid, suggested fixes → clicks quick action to insert Image Asset Mapper → edges auto-reconnect → validation error clears.

**Journey 3: Rerun From A Failed Step**
User mock-runs workflow → Subtitle Formatter fails (config validation: maxCharsPerLine=60, max is 42) → error badge on node → Data Inspector shows config validation failure → user fixes config to 32 → clicks "Run From Here" → only downstream nodes rerun → upstream outputs reused from cache.

**Journey 4: Inspect Data On An Edge**
User clicks edge between Script Writer and Scene Splitter → right panel shows edge inspection: source payload, source schema, transport metadata, JSON viewer, copy payload, compare against target schema.

**Journey 5: Start From Template And Fork**
User clicks "Narrated Product Teaser" template → 9 nodes load pre-connected → removes TTS Voiceover Planner → reruns mock → exports workflow JSON.

**Journey 6: Recover After Refresh**
User editing + running mock → tab refreshes → on reopen: recovered draft available, last autosave timestamp, interrupted run detected → restore options shown → user restores → workflow and run state recovered.

### The 12 Specified E2E Scenarios (from plan section 19.5)

1. Create a valid workflow from scratch
2. Reject an incompatible connection
3. Insert adapter node to fix connection
4. Run workflow in mock mode
5. Inspect edge payload
6. Fail a node due to invalid config
7. Fix config and run from here
8. Export and reimport workflow
9. Refresh during dirty edit and recover draft
10. Refresh during active run and recover interrupted state
11. Open same workflow in two tabs — show soft-lock warning
12. Close one tab — clear warning after heartbeat expiry

### App Architecture (for writing selectors)

- Three-panel layout: left (node library), center (React Flow canvas), right (inspector)
- Node library: searchable sidebar with category filters
- Canvas: React Flow with custom node cards, custom edges
- Inspector: tabs for Config, Preview, Data, Validation, Metadata
- Run toolbar: Run Workflow, Run Selected Node, Run From Here, Run Up To Here, Cancel
- Nodes have: title, icon, status badge (idle/pending/running/success/error), port handles
- Edges have: default/selected/invalid/warning states, validation badges
- Persistence: IndexedDB via Dexie, autosave, import/export JSON

## YOUR TASK

Create a comprehensive E2E test plan covering:

1. **Test Architecture** — File structure, page objects, fixtures, helpers, how to start/stop the dev server
2. **Test Suite Organization** — How tests are grouped, naming conventions, tags
3. **Detailed Test Cases** — For each of the 12 scenarios + 6 journeys, write:
   - Test name
   - Preconditions
   - Step-by-step actions (with Playwright API calls)
   - Assertions (specific, not vague)
   - Cleanup/teardown
4. **Helper Functions** — Reusable functions for common operations (drag node, connect ports, run workflow, inspect edge, etc.)
5. **Selector Strategy** — How to find elements reliably (data-testid? role? text?)
6. **Test Data** — Fixtures, mock workflows, expected payloads
7. **CI Integration** — How to run in CI, parallelization, retries, artifacts
8. **Edge Cases** — Tests for things NOT in the 12 scenarios (browser back/forward, rapid clicking, network interruption simulation, etc.)

Include actual Playwright code examples — not pseudocode. Be opinionated. Aim for 2000-4000 words.
