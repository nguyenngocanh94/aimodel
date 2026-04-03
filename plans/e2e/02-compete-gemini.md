This comprehensive Playwright E2E test plan is designed for high-velocity validation of the **AI Video Workflow Builder**. Given the complexity of a React Flow-based canvas, the strategy prioritizes **Page Object Models (POMs)** to abstract canvas interactions and a **custom fixture** to handle the mock execution lifecycle.

---

## 1. Test Architecture

The suite follows a modular design to ensure the tests remain maintainable as the node library expands.

### Directory Structure
```text
tests/e2e/
├── fixtures/
│   ├── base.fixture.ts      # Custom playwright fixture for 'editorPage'
│   └── mock-workflows.ts    # JSON payloads for re-import/template tests
├── pages/
│   ├── Canvas.ts            # React Flow interaction logic (drag, connect, zoom)
│   ├── Library.ts           # Node library search and drag source
│   ├── Inspector.ts         # Sidebar tabs, forms, and data viewing
│   └── Toolbar.ts           # Global actions (Run, Save, Export)
├── suites/
│   ├── journeys.spec.ts     # The 6 core user journeys
│   ├── scenarios.spec.ts    # The 12 specific functional scenarios
│   ├── keyboard.spec.ts     # Shortcut and focus management
│   └── recovery.spec.ts     # Refresh, autosave, and multi-tab logic
└── playwright.config.ts     # Parallelization, retries, and webServer config
```

### Starting the Dev Server
The `playwright.config.ts` will manage the Vite lifecycle:
```typescript
import { defineConfig } from '@playwright/test';
export default defineConfig({
  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:5173',
    reuseExistingServer: !process.env.CI,
  },
  use: {
    baseURL: 'http://localhost:5173',
    viewport: { width: 1440, height: 900 },
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
});
```

---

## 2. Page Object Models & Helper Functions

Canvas interactions are brittle if performed via raw coordinates. Our POMs use `data-testid` and bounding box calculations.

### Canvas POM (`pages/Canvas.ts`)
```typescript
import { Page, Locator, expect } from '@playwright/test';

export class Canvas {
  constructor(private page: Page) {}

  getNode(nodeId: string): Locator {
    return this.page.getByTestId(`node-card-${nodeId}`);
  }

  async connectNodes(sourceId: string, sourcePort: string, targetId: string, targetPort: string) {
    const from = this.page.getByTestId(`node-port-out-${sourceId}-${sourcePort}`);
    const to = this.page.getByTestId(`node-port-in-${targetId}-${targetPort}`);
    
    await from.hover();
    await this.page.mouse.down();
    await to.hover();
    await this.page.mouse.up();
  }

  async dragNodeToCanvas(category: string, nodeName: string, x: number, y: number) {
    const libraryItem = this.page.getByTestId(`library-item-${nodeName}`);
    await libraryItem.dragTo(this.page.locator('.react-flow__pane'), {
      targetPosition: { x, y }
    });
  }

  async verifyNodeState(nodeId: string, state: 'running' | 'done' | 'error' | 'pending') {
    const node = this.getNode(nodeId);
    await expect(node).toHaveAttribute(`data-${state}`, 'true');
  }
}
```

---

## 3. Detailed Test Cases: The 6 User Journeys

### Journey 1: Create A Short-Form Video Workflow From Scratch
**Preconditions:** Fresh application state, canvas empty.
```typescript
test('Journey 1: Create scratch workflow and run mock', async ({ page, editor }) => {
  // 1. Add Nodes
  await editor.library.dragNode('Input', 'User Prompt', 100, 100);
  await editor.library.dragNode('Script', 'Script Writer', 400, 100);
  await editor.library.dragNode('Video', 'Video Composer', 700, 100);

  // 2. Connect
  await editor.canvas.connectNodes('node-1', 'output', 'node-2', 'input');
  await editor.canvas.connectNodes('node-2', 'output', 'node-3', 'input');

  // 3. Configure Node
  await editor.canvas.getNode('node-2').click();
  await editor.inspector.selectTab('config');
  await page.getByLabel('sceneCountTarget').fill('5');

  // 4. Run Workflow
  await page.getByTestId('run-btn-workflow').click();

  // 5. Assertions
  await expect(page.getByTestId('run-status-chip')).toContainText('Running');
  await editor.canvas.verifyNodeState('node-3', 'done'); // Final node success
  
  // 6. Inspect Edge Data
  await page.getByTestId('edge-node-2-node-3').click();
  await editor.inspector.selectTab('data');
  await expect(page.getByTestId('inspector')).toContainText('"sceneCount": 5');
});
```

### Journey 3: Rerun From A Failed Step
**Scenario:** `Subtitle Formatter` fails due to config; fix and resume.
```typescript
test('Journey 3: Rerun from failed step', async ({ page, editor }) => {
  // Precondition: Workflow loaded, node-4 (Subtitle Formatter) has error
  await editor.canvas.verifyNodeState('node-4', 'error');
  await editor.canvas.getNode('node-4').click();
  
  await editor.inspector.selectTab('validation');
  await expect(page.getByTestId('validation-item-MAX_CHARS')).toBeVisible();

  // Fix: change 60 to 32
  await editor.inspector.selectTab('config');
  await page.getByLabel('maxCharsPerLine').fill('32');

  // Execution: Run From Here
  await page.getByTestId('run-btn-from-here').click();

  // Assertions: Node 1-3 should remain 'done' (cached), 4-5 should flip to 'running' then 'done'
  await expect(page.getByTestId('node-card-node-1')).toHaveAttribute('data-stale', 'false');
  await editor.canvas.verifyNodeState('node-4', 'done');
});
```

---

## 4. Specified Functional Scenarios (Selected Highlights)

### Scenario 2: Reject Incompatible Connection
**Intent:** Ensure Video Output cannot connect to Script Input.
```typescript
test('Scenario 2: Reject incompatible connection', async ({ page, editor }) => {
  await editor.canvas.connectNodes('video-node', 'video-out', 'script-node', 'text-in');
  
  const edge = page.getByTestId('edge-video-node-script-node');
  await expect(edge).toHaveAttribute('data-invalid', 'true');
  await expect(page.getByTestId('edge-label-video-node-script-node')).toContainText('Incompatible');
});
```

### Scenario 11 & 12: Multi-tab Locking
```typescript
test('Scenario 11/12: Multi-tab soft-lock logic', async ({ context, page }) => {
  const page2 = await context.newPage();
  await page.goto('/');
  await page2.goto('/');

  // Second tab shows warning
  await expect(page2.locator('.soft-lock-warning')).toBeVisible();

  // Close first tab
  await page.close();

  // Second tab clears warning (allow for heartbeat interval)
  await expect(page2.locator('.soft-lock-warning')).not.toBeVisible({ timeout: 10000 });
});
```

---

## 5. Keyboard Shortcut Tests

We must verify that global shortcuts do not trigger while focused on input fields.

| Shortcut | Action | Expected Selector State |
| :--- | :--- | :--- |
| `Backspace` | Delete Node | `data-testid="node-card-id"` is detached |
| `Shift+R` | Run Workflow | `data-testid="run-status-chip"` -> 'Running' |
| `A` | Quick Add | `data-testid="quick-add-dialog"` is visible |

```typescript
test('Keyboard Shortcuts: Deletion and Input Protection', async ({ page, editor }) => {
  await editor.canvas.getNode('node-1').click();
  
  // Case: Focused in config input
  await editor.inspector.selectTab('config');
  const input = page.getByLabel('Node Name');
  await input.focus();
  await page.keyboard.press('Backspace');
  await expect(editor.canvas.getNode('node-1')).toBeVisible(); // Should NOT delete

  // Case: Global context
  await page.mouse.click(0, 0); // Click canvas to blur
  await editor.canvas.getNode('node-1').click();
  await page.keyboard.press('Backspace');
  await expect(editor.canvas.getNode('node-1')).not.toBeAttached();
});
```

---

## 6. Media Preview Tests

After mock execution, nodes must render the correct content types.



```typescript
test('Media Preview: Image Generator Grid', async ({ page, editor }) => {
  await editor.canvas.dragNodeToCanvas('Visuals', 'Image Generator', 100, 100);
  await page.getByTestId('run-btn-workflow').click();

  const node = page.getByTestId('node-card-image-gen');
  const grid = node.locator('.thumbnail-grid');
  
  await expect(grid.locator('img')).toHaveCount(4);
  await expect(grid.locator('.overflow-badge')).toHaveText('+N'); // Verify overflow logic
});

test('Media Preview: Video Composer 16:9', async ({ page }) => {
  const videoNode = page.getByTestId('node-card-video-composer');
  await expect(videoNode.locator('.video-preview-frame')).toHaveCSS('aspect-ratio', '16 / 9');
  await expect(videoNode.getByTestId('play-button')).toBeVisible();
  await expect(videoNode).toContainText('0:00 / 0:30');
});
```

---

## 7. Recovery & Persistence

### Scenario 9: Refresh during dirty edit
1. User adds a node (Workflow becomes "dirty").
2. Page reload.
3. Assert recovery modal visibility.
4. Click "Restore draft".
5. Assert node exists on canvas.

```typescript
test('Scenario 9: Recovery after refresh', async ({ page }) => {
  await page.goto('/');
  await page.getByTestId('library-item-User Prompt').dragTo(page.locator('.react-flow__pane'));
  await expect(page.getByTestId('workflow-dirty-indicator')).toBeVisible();

  await page.reload();

  const modal = page.getByTestId('recovery-dialog');
  await expect(modal).toBeVisible();
  await modal.getByText('Restore draft').click();

  await expect(page.getByTestId('node-card-node-1')).toBeVisible();
});
```

---

## 8. CI Integration & Reliability

### Parallelization Strategy
Since these are browser-heavy tests, we use **sharding** in CI (GitHub Actions).
* **Retries:** 2 on CI to account for React Flow rendering jitter.
* **Workers:** 4 (standard) or `1` if testing multi-tab heartbeat logic.

### CI Configuration (`.github/workflows/e2e.yml`)
```yaml
- name: Run Playwright tests
  run: npx playwright test --shard=${{ matrix.shardIndex }}/${{ matrix.shardTotal }}
- uses: actions/upload-artifact@v4
  if: always()
  with:
    name: playwright-report
    path: playwright-report/
```

---

## 9. Edge Cases

* **Rapid Connection:** Dragging an edge and releasing it before the UI updates. Test uses `page.mouse` for micro-second control.
* **Delete during Run:** If a user deletes a node while `data-running="true"`, the system must terminate the mock process and clean up the toolbar state.
* **Undo after Edge Insertion:** Test `Cmd+Z` immediately after `Canvas.connectNodes`. Verify the edge is removed and `workflow-dirty-indicator` status.
* **Viewport Resize:** Ensure the three-panel layout (280px / Auto / 400px) holds and the canvas remains interactable.

---

## 10. Selector Strategy Summary

| Priority | Selector Type | Example |
| :--- | :--- | :--- |
| **1** | `data-testid` | `page.getByTestId('run-btn-workflow')` |
| **2** | State Attributes | `locator('[data-running="true"]')` |
| **3** | Accessible Role | `page.getByRole('button', { name: 'Save' })` |
| **4** | Canvas Handles | `.react-flow__handle-right` |

**Opinionated Recommendation:** Avoid testing CSS animations (like "nodes animate through pending"). Instead, test the **resulting state** (e.g., node status badge changes from yellow to green). Testing CSS transitions in Playwright leads to flakiness. Use `waitForAttribute` or `expect().toHaveAttribute()` to handle the async nature of the mock runner.
