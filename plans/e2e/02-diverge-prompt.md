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

### App Architecture And UI Design

**Layout:** Dark-mode three-panel app (1440x900 default)
- Left panel (280px): Node library with search, category filters (Input/Script/Visuals/Audio/Video/Utility/Output), draggable items
- Center: React Flow canvas with custom node cards and typed edges
- Right panel (400px): Inspector with 5 tabs (Config, Preview, Data, Validation, Meta)
- Top toolbar (48px): Workflow name, unsaved badge, Run Workflow/Run Node/From Here buttons, status chip

**Node Cards show:**
- Category accent line (2px, color-coded: blue=script, violet=visuals, amber=video, teal=audio)
- Title, subtitle (type + provider), status dot
- Typed port handles with labels (input left, output right)
- Inline media previews: image thumbnail grid (4 images) for Image Generator, 16:9 video preview with play/timeline for Video Composer
- Footer with duration, badge (done/running/error/pending)

**Edge Design:**
- Neutral default stroke with type pill labels (SCRIPT, VIDEO, AUDIO, IMAGE, DATA)
- States: default, hovered, selected (cyan), valid preview, invalid (red dotted), execution trace (amber animated)

**Keyboard Shortcuts (must test):**
- `Cmd/Ctrl+S`: save snapshot
- `Cmd/Ctrl+Shift+E`: export JSON
- `Cmd/Ctrl+Z` / `Cmd/Ctrl+Shift+Z`: undo/redo
- `Backspace/Delete`: delete selection
- `Space`: pan mode
- `A`: quick-add node menu
- `Enter`: inspect selected
- `R`: run selected node
- `Shift+R`: run workflow
- `C`: connect dialog
- `Escape`: clear/close

### data-testid Selectors (USE THESE in all tests)

The design system defines these exact test IDs:
- `data-testid="node-card-{nodeId}"`
- `data-testid="node-port-in-{nodeId}-{portKey}"`
- `data-testid="node-port-out-{nodeId}-{portKey}"`
- `data-testid="edge-{edgeId}"`
- `data-testid="edge-label-{edgeId}"`
- `data-testid="inspector"`
- `data-testid="inspector-tab-{tabName}"` (config, preview, data, validation, meta)
- `data-testid="run-toolbar"`
- `data-testid="run-btn-workflow"`
- `data-testid="run-btn-node"`
- `data-testid="run-btn-from-here"`
- `data-testid="run-btn-cancel"`
- `data-testid="workflow-save-btn"`
- `data-testid="workflow-export-btn"`
- `data-testid="node-search-input"`
- `data-testid="quick-add-dialog"`
- `data-testid="connect-dialog"`
- `data-testid="canvas-empty-cta"`
- `data-testid="validation-item-{issueCode}"`
- `data-testid="toast-run-error"`
- `data-testid="workflow-dirty-indicator"`
- `data-testid="run-status-chip"`
- `data-testid="node-menu-btn-{nodeId}"`

State attributes on elements:
- `data-selected="true"`
- `data-running="true"`
- `data-invalid="true"`
- `data-stale="true"`

### Media Preview Nodes (must test)

**Image Generator node:** After mock execution, shows a 4-image thumbnail grid inside the node card. Each thumbnail is 56x56px, rounded corners. Overflow shows "+N" badge.

**Video Composer node:** After mock execution, shows a 16:9 video preview frame with poster image, play button overlay, timeline text "0:00 / 0:30", and metadata footer.

**Reference Images node:** Shows imported reference images as thumbnail grid before execution.

### Empty States (must test)
- Canvas empty: "Create your first workflow" + 3 template cards + "Add first node" button
- Inspector empty: "Select a node to inspect" with pointer icon
- No preview: "Run this node in mock mode"
- No validation issues: "No blocking issues"

### Recovery Dialog (must test)
- Modal overlay with scrim
- Shows: last autosave timestamp, interrupted run badge, workflow name
- Three buttons: "Discard draft", "Open last saved", "Restore draft" (primary)

## YOUR TASK

Create a comprehensive E2E test plan covering:

1. **Test Architecture** — File structure, page objects, fixtures, helpers, how to start/stop the dev server
2. **Test Suite Organization** — How tests are grouped, naming conventions, tags
3. **Detailed Test Cases** — For each of the 12 scenarios + 6 journeys, write:
   - Test name
   - Preconditions
   - Step-by-step actions (with actual Playwright API calls using data-testid selectors)
   - Assertions (specific, referencing exact data-testid and data-attribute values)
   - Cleanup/teardown
4. **Helper Functions** — Reusable functions: dragNodeToCanvas, connectPorts, runWorkflow, inspectEdge, selectInspectorTab, verifyNodeState, verifyMediaPreview, etc.
5. **Selector Strategy** — Use data-testid as primary, role/text as fallback. Reference the exact IDs from the design system.
6. **Keyboard Shortcut Tests** — Test all 12 shortcuts, including precedence rules (disabled in text inputs, escape closes topmost UI first)
7. **Media Preview Tests** — Image thumbnail grid appears after mock execution, video preview shows poster/controls, empty states when no media
8. **Test Data** — Fixtures, mock workflows, expected payloads
9. **CI Integration** — How to run in CI, parallelization, retries, screenshots on failure
10. **Edge Cases** — Browser back/forward, rapid clicking, resize panels, delete during run, undo after edge insertion

Include actual Playwright code examples using the data-testid selectors — not pseudocode. Be opinionated. Aim for 3000-5000 words.
