# AI Video Workflow Builder E2E Test Plan

## Goal

Build a fast, deterministic Playwright E2E suite that proves all critical workflow-builder journeys work in a real browser using mock execution only.

## Architecture

Use a layered Playwright design: shared fixtures create deterministic app state, page objects model the shell, canvas, inspector, toolbar, and recovery surfaces, and helpers perform high-friction actions such as drag-drop, port connections, mock runs, and multi-tab orchestration. Keep full-journey tests broad but few, then cover risky behaviors with smaller scenario specs that reuse the same helpers and test data.

## Tech Stack

- Playwright
- Vite dev server on `http://localhost:5173`
- React
- React Flow
- TypeScript
- Mock execution data only

---

## 1. Test Architecture

### Proposed Directory Structure

- `src/tests/e2e/playwright.config.ts`
- `src/tests/e2e/fixtures/test.ts`
- `src/tests/e2e/fixtures/workflows.ts`
- `src/tests/e2e/fixtures/payloads.ts`
- `src/tests/e2e/fixtures/recovery.ts`
- `src/tests/e2e/helpers/canvas.ts`
- `src/tests/e2e/helpers/workflow.ts`
- `src/tests/e2e/helpers/inspector.ts`
- `src/tests/e2e/helpers/keyboard.ts`
- `src/tests/e2e/helpers/assertions.ts`
- `src/tests/e2e/pages/app-shell.page.ts`
- `src/tests/e2e/pages/canvas.page.ts`
- `src/tests/e2e/pages/inspector.page.ts`
- `src/tests/e2e/pages/toolbar.page.ts`
- `src/tests/e2e/pages/recovery-dialog.page.ts`
- `src/tests/e2e/specs/journeys/journey-create-workflow.spec.ts`
- `src/tests/e2e/specs/journeys/journey-diagnose-broken-connection.spec.ts`
- `src/tests/e2e/specs/journeys/journey-rerun-from-failed-step.spec.ts`
- `src/tests/e2e/specs/journeys/journey-inspect-edge-data.spec.ts`
- `src/tests/e2e/specs/journeys/journey-template-fork-export.spec.ts`
- `src/tests/e2e/specs/journeys/journey-refresh-recovery.spec.ts`
- `src/tests/e2e/specs/scenarios/*.spec.ts`
- `src/tests/e2e/specs/shortcuts/keyboard-shortcuts.spec.ts`
- `src/tests/e2e/specs/media/media-preview.spec.ts`
- `src/tests/e2e/specs/edge-cases/workflow-edge-cases.spec.ts`

### Design Principles

- `pages/` owns stable UI surfaces, not business flows.
- `helpers/` owns multi-step interactions reused across specs.
- `fixtures/` owns deterministic graph state, payloads, invalid configs, and recovery seeds.
- `specs/journeys/` proves end-user value.
- `specs/scenarios/` proves narrow risk-heavy regressions.

### Dev Server Lifecycle

- Start Vite using Playwright `webServer`.
- Use `baseURL: 'http://127.0.0.1:5173'`.
- Use `reuseExistingServer: !process.env.CI` locally.
- Let Playwright manage startup and teardown.
- Fail startup quickly if the app is unavailable after ~30 seconds.

### Recommended Playwright Config

```ts
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './specs',
  timeout: 30_000,
  expect: { timeout: 5_000 },
  fullyParallel: true,
  retries: process.env.CI ? 2 : 0,
  use: {
    baseURL: 'http://127.0.0.1:5173',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  webServer: {
    command: 'npm run dev -- --host 127.0.0.1 --port 5173',
    url: 'http://127.0.0.1:5173',
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
```

---

## 2. Test Suite Organization

### Grouping

- `journeys/`: 6 broad user journeys
- `scenarios/`: 12 focused scenario tests
- `shortcuts/`: all keyboard behavior
- `media/`: image/video preview behavior and empty states
- `edge-cases/`: browser navigation, resize, rapid interaction, and undo/delete edge cases

### Naming Conventions

- File names: `journey-create-workflow.spec.ts`, `scenario-reject-incompatible-connection.spec.ts`
- Test names: `Journey 1: create a short-form workflow from scratch`
- Tags in titles: `@journey`, `@scenario`, `@shortcut`, `@media`, `@recovery`, `@multitab`, `@smoke`, `@slow`

### Runtime Budget

- Entire PR suite should complete in under 2 minutes.
- Mock execution only.
- Chromium is the default PR browser.
- Cross-browser should be a nightly or smoke subset only.

---

## 3. Fixtures And Test Data

### Workflow Fixtures

- `emptyWorkflow`
- `validShortFormWorkflow`
- `savedBrokenConnectionWorkflow`
- `failedSubtitleWorkflow`
- `templateNarratedProductTeaser`
- `dirtyDraftState`
- `runningWorkflowState`

### Payload Fixtures

- `scriptWriterToSceneSplitterPayload`
- `sceneSplitterToImageGeneratorPayload`
- `imageGeneratorToVideoComposerPayload`
- `videoComposerToFinalExportPayload`

### Recovery Fixtures

- Autosave timestamp
- Interrupted run metadata
- Soft-lock tab/session owner metadata

### Injection Strategy

- Prefer app-supported local storage or session storage seeding through `page.addInitScript()`.
- If mock execution depends on API calls, intercept them with `page.route()` and fulfill deterministic JSON.
- Store all graph definitions and expected payloads in one place so scenarios and journeys share identical source data.

### Example Fixture Pattern

```ts
import { test as base } from '@playwright/test';
import { AppShellPage } from '../pages/app-shell.page';

export const test = base.extend<{ app: AppShellPage }>({
  app: async ({ page }, use) => {
    const app = new AppShellPage(page);
    await page.goto('/');
    await app.waitUntilReady();
    await use(app);
  },
});
```

---

## 4. Selector Strategy

### Primary Rule

Use `data-testid` first for all stable interactions and assertions.

### Required Selectors

- `node-card-{nodeId}`
- `node-port-in-{nodeId}-{portKey}`
- `node-port-out-{nodeId}-{portKey}`
- `edge-{edgeId}`
- `edge-label-{edgeId}`
- `inspector`
- `inspector-tab-{tabName}`
- `run-toolbar`
- `run-btn-workflow`
- `run-btn-node`
- `run-btn-from-here`
- `run-btn-cancel`
- `workflow-save-btn`
- `workflow-export-btn`
- `node-search-input`
- `quick-add-dialog`
- `connect-dialog`
- `canvas-empty-cta`
- `validation-item-{issueCode}`
- `toast-run-error`
- `workflow-dirty-indicator`
- `run-status-chip`
- `node-menu-btn-{nodeId}`

### Required State Assertions

- `data-selected="true"`
- `data-running="true"`
- `data-invalid="true"`
- `data-stale="true"`

### Fallback Strategy

- Use `getByRole()` for recovery dialog buttons and modal actions.
- Use visible text for specified empty-state copy and template cards.
- Avoid CSS selectors unless the UI has no semantic or test ID hook.

---

## 5. Page Objects

### `AppShellPage`

Responsibilities:

- Wait for app ready state
- Assert empty canvas and empty inspector states
- Open template cards
- Detect global dialogs and warnings

### `CanvasPage`

Responsibilities:

- Drag nodes from library to canvas
- Select nodes and edges
- Connect ports
- Delete selection
- Open quick-add and connect dialogs

### `InspectorPage`

Responsibilities:

- Switch between `config`, `preview`, `data`, `validation`, `meta`
- Edit config fields
- Assert payload/schema/validation content
- Assert empty states

### `ToolbarPage`

Responsibilities:

- Run workflow
- Run selected node
- Run from here
- Cancel run
- Save
- Export
- Read status chip

### `RecoveryDialogPage`

Responsibilities:

- Assert autosave timestamp
- Assert interrupted run badge
- Assert workflow name
- Click `Discard draft`, `Open last saved`, `Restore draft`

---

## 6. Helper Functions

### Required Helpers

- `dragNodeToCanvas(page, nodeLabel, point)`
- `connectPorts(page, { sourceNodeId, sourcePortKey, targetNodeId, targetPortKey })`
- `runWorkflow(page)`
- `runSelectedNode(page)`
- `runFromHere(page)`
- `inspectEdge(page, edgeId)`
- `selectInspectorTab(page, tabName)`
- `verifyNodeState(page, nodeId, state)`
- `verifyMediaPreview(page, nodeId, kind)`
- `seedWorkflow(page, fixtureName)`
- `seedRecoveryState(context, state)`
- `openSecondTab(context, url)`

### Example Helper Code

```ts
import { expect, Page } from '@playwright/test';

export async function connectPorts(
  page: Page,
  input: {
    sourceNodeId: string;
    sourcePortKey: string;
    targetNodeId: string;
    targetPortKey: string;
  },
): Promise<void> {
  const source = page.getByTestId(
    `node-port-out-${input.sourceNodeId}-${input.sourcePortKey}`,
  );
  const target = page.getByTestId(
    `node-port-in-${input.targetNodeId}-${input.targetPortKey}`,
  );

  await source.dragTo(target);
}

export async function verifyNodeState(
  page: Page,
  nodeId: string,
  state: 'selected' | 'running' | 'invalid' | 'stale',
): Promise<void> {
  await expect(page.getByTestId(`node-card-${nodeId}`)).toHaveAttribute(
    `data-${state}`,
    'true',
  );
}
```

---

## 7. Detailed Test Cases

## User Journey 1

**Test name:** `Journey 1: create a short-form workflow from scratch @journey @smoke`

**Preconditions**

- App opens on a fresh empty canvas
- Mock execution is enabled

**Actions**

```ts
await page.goto('/');
await expect(page.getByTestId('canvas-empty-cta')).toBeVisible();
await expect(page.getByTestId('node-search-input')).toBeVisible();

await dragNodeToCanvas(page, 'User Prompt', { x: 420, y: 140 });
await dragNodeToCanvas(page, 'Script Writer', { x: 680, y: 140 });
await dragNodeToCanvas(page, 'Scene Splitter', { x: 940, y: 140 });
await dragNodeToCanvas(page, 'Image Generator', { x: 1200, y: 140 });
await dragNodeToCanvas(page, 'Video Composer', { x: 1460, y: 140 });
await dragNodeToCanvas(page, 'Final Export', { x: 1720, y: 140 });

await connectPorts(page, {
  sourceNodeId: 'user-prompt-1',
  sourcePortKey: 'prompt',
  targetNodeId: 'script-writer-1',
  targetPortKey: 'prompt',
});
await connectPorts(page, {
  sourceNodeId: 'script-writer-1',
  sourcePortKey: 'script',
  targetNodeId: 'scene-splitter-1',
  targetPortKey: 'script',
});
await connectPorts(page, {
  sourceNodeId: 'scene-splitter-1',
  sourcePortKey: 'scenes',
  targetNodeId: 'image-generator-1',
  targetPortKey: 'scenes',
});
await connectPorts(page, {
  sourceNodeId: 'image-generator-1',
  sourcePortKey: 'images',
  targetNodeId: 'video-composer-1',
  targetPortKey: 'assets',
});
await connectPorts(page, {
  sourceNodeId: 'video-composer-1',
  sourcePortKey: 'video',
  targetNodeId: 'final-export-1',
  targetPortKey: 'video',
});

await page.getByTestId('node-card-scene-splitter-1').click();
await expect(page.getByTestId('inspector')).toBeVisible();
await page.getByTestId('inspector-tab-config').click();
await page.getByLabel('sceneCountTarget').fill('6');
await page.getByTestId('inspector-tab-preview').click();
await expect(page.getByText('6 scenes')).toBeVisible();

await page.getByTestId('run-btn-workflow').click();
await expect(page.getByTestId('run-status-chip')).toContainText('Running');
await verifyNodeState(page, 'script-writer-1', 'running');
await expect(page.getByTestId('run-status-chip')).toContainText('Success');

await page.getByTestId('edge-script-writer-1__scene-splitter-1').click();
await page.getByTestId('inspector-tab-data').click();
await expect(page.getByText('payload')).toBeVisible();
await expect(page.getByText('schema')).toBeVisible();
```

**Assertions**

- Canvas empty state disappears after first node
- Inspector opens for selected node
- Preview reflects `sceneCountTarget=6`
- Run state transitions `Idle -> Running -> Success`
- Edge data becomes inspectable after run

**Cleanup**

- None beyond test isolation

## User Journey 2

**Test name:** `Journey 2: diagnose a broken connection @journey @scenario`

**Preconditions**

- Load `savedBrokenConnectionWorkflow`

**Actions**

- Select the invalid edge
- Open validation/data details
- Use quick fix to insert `Image Asset Mapper`

**Assertions**

- Invalid edge has `data-invalid="true"`
- Inspector shows source schema, target schema, compatibility result, explanation, and suggested fixes
- Adapter insertion removes the invalid state and auto-reconnects the graph

**Cleanup**

- None beyond test isolation

## User Journey 3

**Test name:** `Journey 3: rerun from a failed step @journey @scenario`

**Preconditions**

- Load `failedSubtitleWorkflow`

**Actions**

- Run workflow
- Open failed `Subtitle Formatter`
- Fix `maxCharsPerLine` from `60` to `32`
- Click `run-btn-from-here`

**Assertions**

- Failed node shows validation failure
- `toast-run-error` appears after first run
- Upstream nodes stay successful and are not rerun
- Failed node and downstream nodes rerun successfully

**Cleanup**

- None beyond test isolation

## User Journey 4

**Test name:** `Journey 4: inspect data on an edge @journey`

**Preconditions**

- Successful workflow run exists

**Actions**

- Click edge between Script Writer and Scene Splitter
- Open Data tab

**Assertions**

- Edge has `data-selected="true"`
- Inspector shows source payload, source schema, transport metadata, JSON viewer, copy payload action, and target-schema comparison UI

**Cleanup**

- None beyond test isolation

## User Journey 5

**Test name:** `Journey 5: start from template and fork @journey @smoke`

**Preconditions**

- App starts on empty canvas

**Actions**

- Click `Narrated Product Teaser` template
- Remove `TTS Voiceover Planner`
- Rerun workflow
- Export JSON

**Assertions**

- 9 pre-connected nodes load
- Dirty indicator appears after deletion
- Rerun succeeds
- Export downloads updated workflow graph

**Cleanup**

- Clear download artifact if your runner persists files between tests

## User Journey 6

**Test name:** `Journey 6: recover after refresh @journey @recovery`

**Preconditions**

- Seed dirty draft and interrupted run state

**Actions**

- Reload page
- Use `Restore draft`

**Assertions**

- Recovery dialog overlay is visible
- Dialog shows autosave timestamp, interrupted run badge, and workflow name
- Restored workflow returns both graph state and interrupted run metadata

**Cleanup**

- Clear seeded storage state

---

## 8. Detailed Scenario Coverage

### Scenario 1: Create a valid workflow from scratch

- Covered by Journey 1
- Keep an optional lean smoke variant if the full journey grows too broad

### Scenario 2: Reject an incompatible connection

```ts
await seedWorkflow(page, 'emptyWorkflow');
await dragNodeToCanvas(page, 'Script Writer', { x: 600, y: 180 });
await dragNodeToCanvas(page, 'Video Composer', { x: 980, y: 180 });

await connectPorts(page, {
  sourceNodeId: 'script-writer-1',
  sourcePortKey: 'script',
  targetNodeId: 'video-composer-1',
  targetPortKey: 'assets',
});

await expect(
  page.getByTestId('edge-script-writer-1__video-composer-1'),
).toHaveAttribute('data-invalid', 'true');

await page.getByTestId('edge-script-writer-1__video-composer-1').click();
await page.getByTestId('inspector-tab-validation').click();
await expect(page.getByText('Compatibility result')).toBeVisible();
await expect(page.getByText('invalid')).toBeVisible();
```

### Scenario 3: Insert adapter node to fix connection

- Start from Scenario 2 state
- Click quick action `Insert Image Asset Mapper`
- Assert adapter node appears
- Assert invalid edge disappears
- Assert two new valid edges exist
- Assert no `data-invalid="true"` edge remains

### Scenario 4: Run workflow in mock mode

- Load happy-path workflow
- Click `run-btn-workflow`
- Assert `run-status-chip` transitions from idle to running to success
- Assert at least one node has `data-running="true"` during execution
- Assert `toast-run-error` is absent

### Scenario 5: Inspect edge payload

- Select a successful edge
- Assert payload JSON matches expected fixture shape
- Assert `edge-label-{edgeId}` exists
- If copy payload exists, assert clipboard text matches expected payload

### Scenario 6: Fail a node due to invalid config

- Set `maxCharsPerLine` to `60`
- Run workflow
- Assert node error badge
- Assert `toast-run-error`
- Assert `validation-item-config-max-chars`
- Assert failure status in `run-status-chip`

### Scenario 7: Fix config and run from here

- Change `maxCharsPerLine` to `32`
- Select failed node
- Click `run-btn-from-here`
- Assert upstream nodes do not rerun
- Assert failed node and downstream nodes rerun
- Assert final run succeeds

### Scenario 8: Export and reimport workflow

- Export using `workflow-export-btn` or `Cmd/Ctrl+Shift+E`
- Reimport using the app import flow
- Assert node count, edge count, workflow name, and a representative config field round-trip correctly

### Scenario 9: Refresh during dirty edit and recover draft

- Make a dirty change so `workflow-dirty-indicator` appears
- Reload page
- Assert recovery modal appears
- Restore draft
- Assert unsaved graph changes return

### Scenario 10: Refresh during active run and recover interrupted state

- Start workflow
- Wait until one node has `data-running="true"`
- Reload page
- Assert interrupted run badge appears in recovery modal
- Restore draft
- Assert restored state shows interruption, not false success

### Scenario 11: Open same workflow in two tabs and show soft-lock warning

- Open first tab with workflow
- Open second tab in same context
- Assert second tab shows soft-lock warning
- Assert warning includes workflow/session context

### Scenario 12: Close one tab and clear warning after heartbeat expiry

- Close first tab
- Wait for heartbeat expiry or simulate time progression if supported
- Assert warning clears on second tab

---

## 9. Keyboard Shortcut Test Plan

Create `src/tests/e2e/specs/shortcuts/keyboard-shortcuts.spec.ts`.

### Covered Shortcuts

- `Cmd/Ctrl+S`: save snapshot
- `Cmd/Ctrl+Shift+E`: export JSON
- `Cmd/Ctrl+Z`: undo
- `Cmd/Ctrl+Shift+Z`: redo
- `Backspace/Delete`: delete selection
- `Space`: pan mode
- `A`: quick-add node menu
- `Enter`: inspect selected
- `R`: run selected node
- `Shift+R`: run workflow
- `C`: connect dialog
- `Escape`: clear/close

### Required Precedence Rules

- Shortcuts are disabled in text inputs unless explicitly intended
- `Escape` closes the topmost modal first, then dialog, then selection

### Example Shortcut Assertion

```ts
const modifier = process.platform === 'darwin' ? 'Meta' : 'Control';

await page.getByTestId('node-search-input').click();
await page.keyboard.press(`${modifier}+S`);
await expect(page.getByTestId('workflow-dirty-indicator')).toBeVisible();

await page.keyboard.press('Escape');
await expect(page.getByTestId('quick-add-dialog')).not.toBeVisible();
```

### Recommended Test Breakdown

- One focused test for save/export
- One focused test for undo/redo/delete
- One focused test for quick-add/connect/inspect
- One focused test for run shortcuts
- One focused test for `Escape` precedence
- One focused test for suppression inside text input

---

## 10. Media Preview Test Plan

Create `src/tests/e2e/specs/media/media-preview.spec.ts`.

### Image Generator Node

After mock execution:

- Assert thumbnail grid appears inside `node-card-image-generator-1`
- Assert 4 visible thumbnails
- Assert overflow badge `+N` appears when more than 4 images exist

### Video Composer Node

After mock execution:

- Assert poster frame is visible
- Assert play button overlay is visible
- Assert timeline text `0:00 / 0:30`
- Assert metadata footer exists

### Reference Images Node

Before execution:

- Assert imported reference thumbnails already display

### Empty States

- Canvas empty state: `Create your first workflow`, 3 template cards, `Add first node`
- Inspector empty state: `Select a node to inspect`
- Preview empty state: `Run this node in mock mode`
- Validation empty state: `No blocking issues`

---

## 11. Edge Cases

Create `src/tests/e2e/specs/edge-cases/workflow-edge-cases.spec.ts`.

### Cases To Cover

- Browser back/forward should not corrupt graph state or selected node
- Rapid repeated run clicks should not trigger duplicate runs
- Panel resize should not hide key controls or break drag/connect hit targets
- Delete during run should be blocked, confirmed, or deferred safely
- Undo after quick adapter insertion should revert as one coherent action
- Refresh during open dialog should not restore a broken half-open UI

---

## 12. Assertions And Stability Rules

- Prefer `toHaveAttribute()` for state flags
- Prefer `expect.poll()` over arbitrary `waitForTimeout()`
- Use `dragTo()` first for port handles
- Fall back to coordinate-based mouse actions only if React Flow handle behavior requires it
- Prefer semantic DOM assertions over screenshot assertions
- Never assert raw animation timing

---

## 13. CI Integration

### PR Pipeline

- Chromium only
- Full mock-only suite
- Retries: `2`
- Artifacts: trace and screenshot on failure
- HTML report published

### Nightly

- Optional Firefox/WebKit smoke subset
- Recovery and multi-tab tests can run here too if they become the slowest tests

### Parallelization

- Parallelize by file
- Keep storage-isolated specs deterministic
- Use unique workflow IDs for recovery and multi-tab tests

---

## 14. Recommended Build Order

1. Create fixtures, page objects, and helpers.
2. Implement Journey 1 and Scenarios 2 to 4 first.
3. Implement failure and rerun coverage for Scenarios 6 and 7.
4. Add edge inspection and export/import coverage.
5. Add recovery and multi-tab coverage.
6. Add keyboard, media, and edge-case polish last.

---

## 15. Acceptance Criteria

- All 6 user journeys are automated
- All 12 specified scenarios are automated
- All listed keyboard shortcuts are automated
- Shortcut precedence rules are automated
- Media previews and all specified empty states are automated
- Recovery dialog flows are automated
- CI runtime stays under 2 minutes on mock-only PR runs
- Failure artifacts are sufficient for debugging without immediate local repro
