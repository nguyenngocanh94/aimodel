# AI Video Builder Spec Refinement

This document captures Revision Round 1 for the AI Video Workflow Builder plan. It fills the requested gaps with implementation-grade detail and records additional issues found during review.

## Required Gap Fixes

### Gap 1: Execution Engine Implementation Detail

#### Analysis And Rationale

The plan identified the right execution-domain types but stopped short of specifying how they work together. The missing details were material:

- how execution scope is computed for each trigger
- how subgraphs are extracted
- how topological order is guaranteed
- how non-executable nodes contribute data
- how cache reuse interacts with partial reruns
- how cancellation propagates
- how downstream nodes respond to upstream failure

The cleanest v1 design is:

- `RunPlanner` computes an explicit `ExecutionPlan`
- execution is sequential in v1
- subgraph selection is trigger-specific
- ordering uses Kahn's algorithm
- non-executable nodes may contribute wrapped preview outputs
- downstream nodes blocked by upstream failures become `skipped`, not `error`

That keeps the first implementation simple while preserving a later path to parallel execution.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ## 11. Mock Execution Engine
@@
 ### 11.3 Run Planning
 
 The `RunPlanner` determines execution scope.
@@
 #### `runNode`
 
-Run only the selected node if all required upstream inputs can be resolved from previews or cache.
+Run only the selected node if all required upstream inputs can be resolved from one of:
+
+- a successful upstream node included in the current execution plan
+- a reusable cache entry for an upstream executable node
+- a wrapped preview payload from an upstream non-executable node
+
+`runNode` does not silently substitute preview output for an upstream executable node that has never completed successfully for the current input/config shape.
@@
 #### `runUpToHere`
 
 Run all upstream executable dependencies needed to produce the selected node’s required inputs, and optionally the selected node.
+
+For v1, `runUpToHere` includes the selected node by default.
+
+### 11.3.1 Execution Planning Types
+
+```ts
+export interface ExecutionPlan {
+  readonly runId: string;
+  readonly workflowId: string;
+  readonly trigger: ExecutionRun['trigger'];
+  readonly targetNodeId?: string;
+  readonly scopeNodeIds: readonly string[];
+  readonly orderedNodeIds: readonly string[];
+  readonly skippedNodeIds: readonly string[];
+}
+```
+
+### 11.3.2 Scope Extraction
+
+`RunPlanner` should derive the execution scope in two phases:
+
+1. compute the candidate node set for the trigger
+2. prune to executable nodes plus any non-executable upstream providers required to hydrate inputs
+
+Use graph traversal helpers:
+
+```ts
+function collectUpstreamNodeIds(
+  targetNodeId: string,
+  incomingByNode: ReadonlyMap<string, readonly WorkflowEdge[]>,
+): Set<string> {
+  const visited = new Set<string>();
+  const stack = [targetNodeId];
+
+  while (stack.length > 0) {
+    const current = stack.pop()!;
+    for (const edge of incomingByNode.get(current) ?? []) {
+      if (!visited.has(edge.sourceNodeId)) {
+        visited.add(edge.sourceNodeId);
+        stack.push(edge.sourceNodeId);
+      }
+    }
+  }
+
+  return visited;
+}
+
+function collectDownstreamNodeIds(
+  sourceNodeId: string,
+  outgoingByNode: ReadonlyMap<string, readonly WorkflowEdge[]>,
+): Set<string> {
+  const visited = new Set<string>();
+  const stack = [sourceNodeId];
+
+  while (stack.length > 0) {
+    const current = stack.pop()!;
+    for (const edge of outgoingByNode.get(current) ?? []) {
+      if (!visited.has(edge.targetNodeId)) {
+        visited.add(edge.targetNodeId);
+        stack.push(edge.targetNodeId);
+      }
+    }
+  }
+
+  return visited;
+}
+```
+
+Trigger rules:
+
+- `runWorkflow`: all reachable workflow nodes
+- `runNode`: selected node only
+- `runFromHere`: selected node plus downstream dependents
+- `runUpToHere`: upstream dependencies plus selected node
+
+### 11.3.3 Topological Ordering
+
+Use Kahn's algorithm over the selected subgraph:
+
+```ts
+export function topologicallySortSubgraph(args: {
+  readonly nodeIds: ReadonlySet<string>;
+  readonly edges: readonly WorkflowEdge[];
+}): string[] {
+  const indegree = new Map<string, number>();
+  const outgoing = new Map<string, string[]>();
+
+  for (const nodeId of args.nodeIds) {
+    indegree.set(nodeId, 0);
+    outgoing.set(nodeId, []);
+  }
+
+  for (const edge of args.edges) {
+    if (!args.nodeIds.has(edge.sourceNodeId) || !args.nodeIds.has(edge.targetNodeId)) {
+      continue;
+    }
+
+    indegree.set(edge.targetNodeId, (indegree.get(edge.targetNodeId) ?? 0) + 1);
+    outgoing.get(edge.sourceNodeId)!.push(edge.targetNodeId);
+  }
+
+  const queue = [...indegree.entries()]
+    .filter(([, count]) => count === 0)
+    .map(([nodeId]) => nodeId);
+
+  const ordered: string[] = [];
+
+  while (queue.length > 0) {
+    const nodeId = queue.shift()!;
+    ordered.push(nodeId);
+
+    for (const nextNodeId of outgoing.get(nodeId) ?? []) {
+      const nextCount = (indegree.get(nextNodeId) ?? 0) - 1;
+      indegree.set(nextNodeId, nextCount);
+      if (nextCount === 0) {
+        queue.push(nextNodeId);
+      }
+    }
+  }
+
+  if (ordered.length !== args.nodeIds.size) {
+    throw new Error('Cannot plan execution for cyclic subgraph');
+  }
+
+  return ordered;
+}
+```
+
+### 11.3.4 `RunPlanner.plan()` Contract
+
+```ts
+export class RunPlanner {
+  plan(args: {
+    readonly workflow: WorkflowDocument;
+    readonly trigger: ExecutionRun['trigger'];
+    readonly targetNodeId?: string;
+    readonly registry: NodeRegistry;
+  }): ExecutionPlan {
+    const incomingByNode = indexIncomingEdges(args.workflow.edges);
+    const outgoingByNode = indexOutgoingEdges(args.workflow.edges);
+
+    const scopeNodeIds = this.resolveScopeNodeIds({
+      trigger: args.trigger,
+      targetNodeId: args.targetNodeId,
+      workflow: args.workflow,
+      incomingByNode,
+      outgoingByNode,
+    });
+
+    const orderedNodeIds = topologicallySortSubgraph({
+      nodeIds: scopeNodeIds,
+      edges: args.workflow.edges,
+    });
+
+    return {
+      runId: crypto.randomUUID(),
+      workflowId: args.workflow.id,
+      trigger: args.trigger,
+      targetNodeId: args.targetNodeId,
+      scopeNodeIds: [...scopeNodeIds],
+      orderedNodeIds,
+      skippedNodeIds: [],
+    };
+  }
+}
+```
@@
 ### 11.4 Execution Ordering
@@
 The run model should not assume parallel execution from day one.
+
+### 11.4.1 Main Execution Loop
+
+```ts
+export class MockExecutor {
+  async execute(args: {
+    readonly workflow: WorkflowDocument;
+    readonly plan: ExecutionPlan;
+    readonly registry: NodeRegistry;
+    readonly runCache: RunCache;
+    readonly signal: AbortSignal;
+  }): Promise<ExecutionRun> {
+    const runAbortController = new AbortController();
+    const forwardAbort = () => runAbortController.abort(args.signal.reason);
+    args.signal.addEventListener('abort', forwardAbort, { once: true });
+
+    this.runStore.startRun({
+      id: args.plan.runId,
+      workflowId: args.workflow.id,
+      trigger: args.plan.trigger,
+      targetNodeId: args.plan.targetNodeId,
+      plannedNodeIds: args.plan.orderedNodeIds,
+    });
+
+    try {
+      for (const nodeId of args.plan.orderedNodeIds) {
+        if (runAbortController.signal.aborted) {
+          this.runStore.markPendingNodesCancelled(args.plan.runId);
+          return this.runStore.completeRun(args.plan.runId, 'cancelled');
+        }
+
+        const node = getNodeOrThrow(args.workflow, nodeId);
+        const template = args.registry.get(node.type);
+
+        if (node.disabled) {
+          this.runStore.writeSkippedNode(args.plan.runId, nodeId, 'disabled');
+          continue;
+        }
+
+        const resolvedInputs = this.resolveNodeInputs({
+          workflow: args.workflow,
+          node,
+          template,
+          runId: args.plan.runId,
+          runCache: args.runCache,
+        });
+
+        if (!resolvedInputs.ok) {
+          this.runStore.writeSkippedNode(
+            args.plan.runId,
+            nodeId,
+            resolvedInputs.reason,
+            resolvedInputs.blockedByNodeIds,
+          );
+          continue;
+        }
+
+        if (!template.executable) {
+          const outputPayloads = this.wrapPreviewAsOutputs({
+            node,
+            template,
+            inputs: resolvedInputs.inputPayloads,
+          });
+
+          this.runStore.writeSucceededNode(args.plan.runId, nodeId, {
+            inputPayloads: resolvedInputs.inputPayloads,
+            outputPayloads,
+            usedCache: false,
+          });
+          continue;
+        }
+
+        const cacheHit = args.runCache.getReusableEntry({
+          workflowId: args.workflow.id,
+          node,
+          inputPayloads: resolvedInputs.inputPayloads,
+        });
+
+        if (cacheHit) {
+          this.runStore.writeSucceededNode(args.plan.runId, nodeId, {
+            inputPayloads: resolvedInputs.inputPayloads,
+            outputPayloads: cacheHit.outputPayloads,
+            usedCache: true,
+          });
+          continue;
+        }
+
+        this.runStore.markNodeRunning(args.plan.runId, nodeId, resolvedInputs.inputPayloads);
+
+        const nodeAbortController = new AbortController();
+        const abortNode = () => nodeAbortController.abort(runAbortController.signal.reason);
+        runAbortController.signal.addEventListener('abort', abortNode, { once: true });
+
+        const startedAt = performance.now();
+
+        try {
+          const outputPayloads = await template.mockExecute!({
+            nodeId,
+            config: node.config,
+            inputs: resolvedInputs.inputPayloads,
+            signal: nodeAbortController.signal,
+            runId: args.plan.runId,
+          });
+
+          args.runCache.put({
+            workflowId: args.workflow.id,
+            node,
+            inputPayloads: resolvedInputs.inputPayloads,
+            outputPayloads,
+          });
+
+          this.runStore.writeSucceededNode(args.plan.runId, nodeId, {
+            inputPayloads: resolvedInputs.inputPayloads,
+            outputPayloads,
+            usedCache: false,
+            durationMs: performance.now() - startedAt,
+          });
+        } catch (error) {
+          if (nodeAbortController.signal.aborted) {
+            this.runStore.writeCancelledNode(args.plan.runId, nodeId);
+            this.runStore.markPendingNodesCancelled(args.plan.runId);
+            return this.runStore.completeRun(args.plan.runId, 'cancelled');
+          }
+
+          this.runStore.writeErroredNode(
+            args.plan.runId,
+            nodeId,
+            error instanceof Error ? error.message : 'Unknown mock execution error',
+          );
+        } finally {
+          runAbortController.signal.removeEventListener('abort', abortNode);
+        }
+      }
+
+      return this.runStore.completeRunFromNodeStates(args.plan.runId);
+    } finally {
+      args.signal.removeEventListener('abort', forwardAbort);
+    }
+  }
+}
+```
+
+### 11.4.2 Input Resolution Rules
+
+Resolve inputs in this order:
+
+1. successful upstream output from the active run
+2. reusable cache entry for an upstream executable node
+3. wrapped preview output from an upstream non-executable node
+4. unresolved
+
+If any required input remains unresolved, mark the node `skipped`.
@@
 ### 11.9 Failure Semantics
@@
 Failures should be deterministic when tied to invalid inputs/config.
+
+### 11.9.1 Downstream Error Propagation
+
+Downstream nodes are not eagerly marked failed. Instead:
+
+- the node that throws becomes `error`
+- any later node whose required inputs depend on that failed output becomes `skipped`
+- the skip reason is `upstreamFailed`
+- optional inputs from a failed node may be omitted without blocking execution if all required inputs are still satisfied
+
+This makes `error` mean "this node itself failed" and `skipped` mean "this node was not runnable".
+
+```ts
+export interface NodeRunRecord {
+  readonly runId: string;
+  readonly nodeId: string;
+  readonly status: 'pending' | 'running' | 'success' | 'error' | 'skipped' | 'cancelled';
+  readonly skipReason?: 'disabled' | 'outsideScope' | 'missingRequiredInputs' | 'upstreamFailed' | 'cacheSatisfied';
+  readonly blockedByNodeIds?: readonly string[];
+  readonly startedAt?: string;
+  readonly completedAt?: string;
+  readonly durationMs?: number;
+  readonly inputPayloads: Readonly<Record<string, PortPayload>>;
+  readonly outputPayloads: Readonly<Record<string, PortPayload>>;
+  readonly errorMessage?: string;
+  readonly usedCache: boolean;
+}
+```
 ```

### Gap 2: Dexie / IndexedDB Schema

#### Analysis And Rationale

The plan chose Dexie correctly but never defined the actual database contract. For a local-first product, that is too much ambiguity. The spec needs to say:

- what each table stores
- which indexes are required
- how database upgrades work
- how document migrations differ from Dexie migrations
- what happens when IndexedDB is unavailable
- what happens when quota is exceeded

Without that, local reliability remains aspirational rather than designed.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ## 12. Persistence, Import/Export, And Recovery
@@
 ### 12.2 Database Tables
@@
 - `appPreferences`
+
+### 12.2.1 Concrete Dexie Schema
+
+```ts
+import Dexie, { type Table } from 'dexie';
+
+export interface StoredWorkflowRow {
+  readonly id: string;
+  readonly name: string;
+  readonly updatedAt: string;
+  readonly basedOnTemplateId?: string;
+  readonly tags: readonly string[];
+  readonly document: WorkflowDocument;
+}
+
+export interface NodeRunRecordRow extends NodeRunRecord {
+  readonly id: string;
+  readonly workflowId: string;
+}
+
+export interface RunCacheEntry {
+  readonly id: string;
+  readonly workflowId: string;
+  readonly nodeId: string;
+  readonly cacheKey: string;
+  readonly nodeType: string;
+  readonly nodeTemplateVersion: string;
+  readonly createdAt: string;
+  readonly lastAccessedAt: string;
+  readonly expiresAt?: string;
+  readonly outputPayloads: Readonly<Record<string, PortPayload>>;
+}
+
+export interface AppPreferenceRow {
+  readonly key: string;
+  readonly value: unknown;
+}
+
+export class WorkflowDexie extends Dexie {
+  workflows!: Table<StoredWorkflowRow, string>;
+  workflowSnapshots!: Table<WorkflowSnapshot, string>;
+  executionRuns!: Table<ExecutionRun, string>;
+  nodeRunRecords!: Table<NodeRunRecordRow, string>;
+  runCacheEntries!: Table<RunCacheEntry, string>;
+  appPreferences!: Table<AppPreferenceRow, string>;
+
+  constructor() {
+    super('ai-video-builder');
+
+    this.version(1).stores({
+      workflows: 'id, updatedAt, name, *tags',
+      workflowSnapshots: 'id, workflowId, kind, savedAt',
+      executionRuns: 'id, workflowId, status, startedAt',
+      nodeRunRecords: 'id, runId, workflowId, nodeId, status',
+      runCacheEntries: 'id, workflowId, nodeId, cacheKey, createdAt, lastAccessedAt',
+      appPreferences: 'key',
+    });
+
+    this.version(2).stores({
+      workflows: 'id, updatedAt, name, basedOnTemplateId, *tags',
+      workflowSnapshots: 'id, workflowId, kind, savedAt, interruptedRunId',
+      executionRuns: 'id, workflowId, status, trigger, startedAt',
+      nodeRunRecords: 'id, runId, workflowId, nodeId, status, completedAt',
+      runCacheEntries: 'id, workflowId, nodeId, cacheKey, nodeType, lastAccessedAt, expiresAt',
+      appPreferences: 'key',
+    }).upgrade(async (tx) => {
+      await tx.table('workflows').toCollection().modify((row: StoredWorkflowRow) => {
+        row.basedOnTemplateId ??= row.document.basedOnTemplateId;
+      });
+
+      await tx.table('runCacheEntries').toCollection().modify((row: RunCacheEntry) => {
+        row.lastAccessedAt ??= row.createdAt;
+      });
+    });
+  }
+}
+```
+
+### 12.2.2 Repository Opening Strategy
+
+```ts
+export type PersistenceMode = 'indexeddb' | 'memory-fallback' | 'unavailable';
+
+export async function openWorkflowRepository(): Promise<{
+  readonly mode: PersistenceMode;
+  readonly db?: WorkflowDexie;
+  readonly reason?: string;
+}> {
+  if (typeof indexedDB === 'undefined') {
+    return { mode: 'memory-fallback', reason: 'IndexedDB API unavailable' };
+  }
+
+  try {
+    const db = new WorkflowDexie();
+    await db.open();
+    return { mode: 'indexeddb', db };
+  } catch (error) {
+    return {
+      mode: 'memory-fallback',
+      reason: error instanceof Error ? error.message : 'Failed to open IndexedDB',
+    };
+  }
+}
+```
@@
 ### 12.6 Migration Strategy
@@
 Migrations should be tested with fixtures.
+
+Migration layers must remain distinct:
+
+- Dexie database version migrations for storage layout
+- workflow JSON migrations for document schema
+- node-template migrations for per-node config shape changes
+
+Do not collapse these into one mechanism.
@@
 ### 12.8 Corruption Handling
@@
 - never silently discard corrupted workflow state
+
+### 12.8.1 Quota And Unavailability Handling
+
+The app must distinguish between:
+
+- IndexedDB unavailable at boot
+- transient transaction failure
+- quota exceeded
+- corrupted row payload
+
+```ts
+async function persistWithQuotaRecovery<T>(write: () => Promise<T>): Promise<T> {
+  try {
+    return await write();
+  } catch (error) {
+    if (error instanceof Dexie.QuotaExceededError) {
+      await pruneRunCacheAndOldSnapshots();
+      return await write();
+    }
+    throw error;
+  }
+}
+```
+
+If the retry also fails:
+
+- preserve in-memory workflow state
+- disable non-essential artifact and cache writes for the session
+- keep lightweight document persistence if still possible
+- show a blocking warning with `Clear Run Cache` and `Export Workflow JSON`
+- never silently drop user edits
 ```

### Gap 3: App Boot Sequence

#### Analysis And Rationale

The original plan listed `app.tsx`, `providers.tsx`, and `routes.tsx` but never assigned responsibilities or startup order. That leaves critical questions unresolved:

- what wraps the app
- when persistence is opened
- when recovery is checked
- when Zustand stores are hydrated
- whether the canvas renders before boot decisions are complete

The refinement should lock this down. The simplest v1 is a single primary route with dialog overlays, plus an explicit boot state machine.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ## 7. System Architecture
@@
 ### 7.1 Architecture Recommendation
@@
 This stack is optimized for:
@@
 - maintainable UI composition
+
+### 7.1.1 App Entry Responsibilities
+
+- `app.tsx`: mounts the app and top-level providers
+- `providers.tsx`: composes theme, persistence, boot, and `ReactFlowProvider`
+- `routes.tsx`: defines the route tree and modal overlays
@@
 ### 7.3 High-Level Component Graph
@@
     PersistenceGateway --> DexieDB
 ```
+
+### 7.3.1 Route Structure
+
+V1 should use a single primary route:
+
+- `/` -> editor shell
+
+Optional dialog/search-param states:
+
+- `/?dialog=template-gallery`
+- `/?dialog=import`
+- `/?dialog=recovery`
+- `/?dialog=settings`
+
+This keeps v1 simple without blocking later expansion.
+
+### 7.3.2 Provider Composition
+
+```tsx
+export function AppProviders({ children }: { children: React.ReactNode }) {
+  return (
+    <React.StrictMode>
+      <ThemeProvider>
+        <PersistenceProvider>
+          <BootProvider>
+            <ReactFlowProvider>{children}</ReactFlowProvider>
+          </BootProvider>
+        </PersistenceProvider>
+      </ThemeProvider>
+    </React.StrictMode>
+  );
+}
+```
+
+Notes:
+
+- Zustand stores remain module-scoped and do not require a custom provider
+- `PersistenceProvider` exposes repository mode
+- `BootProvider` is responsible for store hydration and recovery decisions
+
+### 7.3.3 Boot State Machine
+
+```ts
+type BootState =
+  | { status: 'checkingPersistence' }
+  | { status: 'checkingRecovery'; repository: WorkflowRepository }
+  | { status: 'ready'; repository: WorkflowRepository; initialWorkflowId?: string }
+  | { status: 'degraded'; repository: WorkflowRepository; reason: string }
+  | { status: 'fatal'; message: string };
+```
+
+Boot order:
+
+1. mount providers and render a lightweight splash screen
+2. open the persistence repository
+3. detect degraded mode if IndexedDB is unavailable
+4. check for a recovery snapshot
+5. if recovery exists, show recovery dialog before loading a document
+6. otherwise load `lastOpenedWorkflowId`
+7. if a last-opened workflow exists, hydrate it into `workflowStore`
+8. otherwise show empty state with templates
+9. only then mount the editable `AppShell`
+
+### 7.3.4 Initial Render Contract
+
+`AppShell` should not render the editable canvas until boot has decided one of:
+
+- restored recovery snapshot
+- loaded last workflow
+- shown empty state
+
+This avoids flashing an empty canvas and then replacing it.
+
+Recommended file responsibilities:
+
+```tsx
+// app.tsx
+export function App() {
+  return (
+    <AppProviders>
+      <AppRoutes />
+    </AppProviders>
+  );
+}
+```
+
+```tsx
+// routes.tsx
+const router = createBrowserRouter([
+  {
+    path: '/',
+    element: <BootGate />,
+  },
+]);
+
+function BootGate() {
+  const boot = useBootState();
+
+  if (boot.status === 'checkingPersistence' || boot.status === 'checkingRecovery') {
+    return <AppSplashScreen />;
+  }
+
+  if (boot.status === 'fatal') {
+    return <FatalBootErrorScreen message={boot.message} />;
+  }
+
+  return <AppShell />;
+}
+```
 ```

### Gap 4: FFmpeg.wasm / Video Preview Strategy

#### Analysis And Rationale

The plan said `videoComposer` produces a mock asset descriptor, but it never defined what the user actually sees. That is a product gap, not just a technical one.

The right v1 answer is not true encoding. It is a storyboard-style preview that feels video-like:

- poster frame
- timed scene switching
- subtitle overlay
- transition simulation
- audio timing metadata

That gives users meaningful visual feedback without pulling real video rendering into v1. The spec should also clearly state a staged v2 path and the cost of FFmpeg.wasm.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ### 5.9 `videoComposer`
@@
 Behavior:
-Produces a mock composed asset descriptor, timeline summary, and preview metadata.
+Produces:
+
+- a mock composed asset descriptor
+- a timeline summary
+- a poster frame URL
+- an animated storyboard preview recipe
+- preview metadata for subtitles, transitions, and audio timing alignment
@@
 Mock execution:
 Required.
+
+Preview semantics in v1:
+
+- v1 does not render a true encoded MP4
+- the primary preview is an animated storyboard player
+- the player simulates cuts, fades, title cards, subtitle overlays, and audio timing
@@
 ## 18. Browser And Runtime Constraints
@@
 ### 18.2 Memory Constraints
@@
 - synthetic URLs
+
+### 18.2.1 Mock Video Preview Contract
+
+```ts
+export interface MockVideoAsset {
+  readonly kind: 'mockVideoAsset';
+  readonly posterUrl: string;
+  readonly durationMs: number;
+  readonly width: number;
+  readonly height: number;
+  readonly timeline: readonly {
+    readonly sceneId: string;
+    readonly startMs: number;
+    readonly endMs: number;
+    readonly imageUrl: string;
+    readonly caption?: string;
+    readonly subtitleLines?: readonly string[];
+    readonly transition?: 'cut' | 'fade' | 'slide';
+  }[];
+  readonly previewMode: 'storyboard-player';
+}
+```
+
+The user-visible preview should include:
+
+- poster frame before playback
+- play/pause
+- timed scene switching
+- optional subtitle overlay
+- metadata badges for aspect ratio, duration, fps, and transition style
+
+### 18.2.2 V2 Rendering Strategy
+
+Recommended progression:
+
+1. `Canvas API + MediaRecorder` for lightweight local preview rendering
+2. optional `FFmpeg.wasm` for advanced local export/transcode workflows
+3. server-side rendering only when real execution or durable exports justify it
+
+Stage plan:
+
+- v1: storyboard preview only
+- v2a: Canvas API + MediaRecorder
+- v2b: lazy-loaded FFmpeg.wasm
+- later: server-side rendering
+
+### 18.2.3 FFmpeg.wasm Constraints
+
+If FFmpeg.wasm is introduced later:
+
+- expect roughly 25-31 MB initial download depending on build
+- load it only via dynamic import
+- initialize only on explicit render/export action
+- run it in a worker, never on the main thread
+
+```ts
+const loadFfmpeg = () => import('../rendering/ffmpeg-renderer');
+```
+
+### 18.2.4 Browser Memory Budget
+
+Set explicit budgets for v1:
+
+- max persisted mock artifact payload per workflow: about 40 MB
+- max in-memory active preview media budget per session: about 80 MB
+- poster or thumbnail target size: <= 512 KB each
+- revoke object URLs on unmount or payload replacement
+- never persist raw frame arrays or large base64 blobs
+
+If the budget is exceeded:
+
+- degrade to metadata-only preview for oversized payloads
+- preserve the run record
+- show a warning that rich preview was skipped due to size limits
 ```

### Gap 5: Edge Insertion UX

#### Analysis And Rationale

The current journey says an `Image Asset Mapper` can be inserted between two nodes and edges reconnect automatically, but that hides several implementation decisions:

- how compatible ports are chosen
- what happens if there are multiple valid choices
- whether the command is atomic
- how undo/redo works

This should be a first-class store command rather than incidental UI logic.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ### 4.2 Journey 2: Diagnose A Broken Connection
@@
 The user clicks the quick action to insert `Image Asset Mapper`. The app places it between the two nodes and reconnects the edges automatically if the insertion is unambiguous.
+
+Auto-reconnection rules:
+
+1. do not remove the original edge until a valid replacement path is confirmed
+2. choose the inserted-node input port most compatible with the original source port
+3. choose the inserted-node output port most compatible with the original target port
+4. reconnect source -> inserted node -> target as one atomic command
+
+If there is no unique best port pair, open a small chooser so the user can confirm before the original edge is removed.
@@
 ### 6.3 Center Panel: Canvas
@@
 - quick insertion on edge
+
+Quick insertion should be implemented as a workflow-store command, not ad hoc canvas mutation.
+
+### 6.3.1 Edge Insertion Command
+
+```ts
+export interface InsertNodeOnEdgeCommand {
+  readonly kind: 'insertNodeOnEdge';
+  readonly edgeId: string;
+  readonly newNodeType: string;
+  readonly preferredInputPortKey?: string;
+  readonly preferredOutputPortKey?: string;
+}
+```
+
+Execution semantics:
+
+1. create the new node at the geometric midpoint of the original edge
+2. resolve compatible input/output ports
+3. remove the original edge
+4. add replacement edges
+5. select the inserted node
+6. open the inspector if config is required
+
+Undo semantics:
+
+- one undo removes the inserted node and replacement edges
+- the original edge is restored in the same history step
+
+### 6.3.2 Port Matching Algorithm
+
+Score candidate ports in this order:
+
+- exact semantic type match
+- compatibility via explicit coercion rule
+- compatibility via node-specific adapter rule
+
+Tie-breakers:
+
+- prefer required over optional ports
+- prefer semantically matching labels
+- prefer the only non-generic port if exactly one exists
+
+Example scoring:
+
+- exact match: 100
+- explicit coercion: 60
+- adapter-specific compatibility: 40
+- incompatible: reject
+
+If exactly one input/output pair yields the best score, insert automatically.
+If multiple pairs tie, require user confirmation.
+If no pair is valid, preserve the original edge and optionally open the node disconnected.
 ```

### Gap 6: Clean Up Structure

#### Analysis And Rationale

The opening comparison notes are useful authoring context but weaken the final specification. They delay the core thesis, duplicate reasoning already reflected later, and make the document feel like workshop notes instead of a final implementation spec.

The final spec should start at `Product Thesis`. The comparison material should move to an appendix or a separate synthesis file.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
-# Hybrid AI Video Builder Revision
-
-## Honest Comparison
-
-### Plan B vs Plan A
-
-...
-
-### Plan C vs Plan A
-
-...
-
-### Plan D vs Plan A
-
-...
-
-## Best Hybrid Revisions And Why
-
-...
-
-## Git-Diff Style Changes To Plan A
-
-```diff
-...
-```
-
-## FULL Revised Plan
-
 # AI Video Workflow Builder Design Proposal
 ## Revised Hybrid Specification
@@
 The revised plan should **not** abandon Plan A’s builder-first conviction.
@@
 That is the version most likely to ship cleanly, demo strongly, and evolve into a real execution platform later without regretting its foundations.
+
+---
+
+## Appendix A: Plan Synthesis Notes
+
+Move the former comparison and synthesis material here or into a separate file such as `PLAN_APPENDIX_SYNTHESIS.md`.
+
+The main spec should begin directly with:
+
+- document title
+- revised specification subtitle
+- product thesis
+- scope
 ```

## Additional Issues Found

### Status Model Contradiction

#### Analysis And Rationale

The plan talks about interrupted runs during recovery but never includes `interrupted` in the runtime status union. That creates a direct contradiction between recovery behavior and the type system.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 export interface ExecutionRun {
@@
-  readonly status: 'pending' | 'running' | 'success' | 'error' | 'cancelled';
+  readonly status: 'pending' | 'running' | 'success' | 'error' | 'cancelled' | 'interrupted';
   readonly startedAt: string;
   readonly completedAt?: string;
+  readonly terminationReason?: 'completed' | 'nodeError' | 'userCancelled' | 'tabClosed' | 'recoveredAfterCrash';
 }
@@
 On restart:
@@
- mark interrupted run records as `cancelled` or `interrupted`
+ mark interrupted run records as `interrupted`
+ reserve `cancelled` for explicit user cancellation
 ```

### Config-Dependent Ports

#### Analysis And Rationale

`imageGenerator` changes its contract depending on config. If the implementation mounts and unmounts handles dynamically, React Flow synchronization gets fragile. The safer v1 rule is to keep those ports structurally present and toggle active state instead.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ### 5.5 `imageGenerator`
@@
 Inputs:
- `scenes: sceneList` or `prompts: promptList` depending on mode
+ `scenes: sceneList`
+ `prompts: promptList`
@@
 Outputs:
- `imageFrames: imageFrameList`
- or `imageAssets: imageAssetList` depending on config
+ `imageFrames: imageFrameList`
+ `imageAssets: imageAssetList`
@@
 Behavior:
 In v1, does not call a provider. Produces placeholder image artifacts with metadata.
+
+Port rendering rule:
+
+- keep both input ports and both output ports structurally present
+- config marks ports active vs inactive
+- inactive ports render disabled and reject new connections
+- existing connections to newly inactive ports become validation errors until resolved
 ```

### Import Of Unknown Nodes

#### Analysis And Rationale

Import currently reads as mostly pass/fail. That is too brittle for a spec artifact that may be versioned over time. The app should support degraded import for unknown node types and unsupported template versions.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ### 12.5 Import/Export
@@
 8. surface import report:
    - imported successfully
    - migrated
    - warnings
    - errors
+
+If a workflow references an unknown node type or unsupported node template version:
+
+- import the workflow in degraded mode rather than failing the whole document
+- render the node as an `Unsupported Node` shell with preserved raw config
+- block execution through that node
+- allow export without data loss
+- surface a clear warning in the inspector and workflow summary
 ```

### Keyboard Accessibility Gap

#### Analysis And Rationale

The plan lists keyboard shortcuts but does not provide a keyboard path for connecting ports. That leaves one of the core authoring interactions pointer-only.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ### 6.8 Accessibility
@@
 Canvas-heavy apps are difficult to make perfectly screen-reader friendly, but the inspector and library should remain highly accessible.
+
+Minimum keyboard graph-editing support in v1:
+
+- tab or arrow navigation between nodes
+- enter to inspect selected node
+- keyboard command to start "connect from selected output"
+- searchable dialog listing valid target ports
+- keyboard command to disconnect selected edge
@@
 ### 6.9 Keyboard Shortcuts
@@
 - `Enter`: inspect selected item
+- `C`: connect from selected node or port
 - `R`: run selected node
 ```

### Retention / Garbage Collection

#### Analysis And Rationale

The plan says storage should be bounded but never defines how. That needs to be concrete in a local-first app.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ### 12.3 Autosave
@@
 Autosave should save a document snapshot, not transient UI noise.
+
+### 12.3.1 Retention Policy
+
+Set explicit retention defaults:
+
+- keep the latest 1 committed workflow row per workflow id
+- keep the latest 20 autosave snapshots per workflow
+- keep the latest 10 execution runs per workflow
+- keep the latest 3 reusable cache entries per node/config/input hash family
+
+Garbage collection triggers:
+
+- after successful autosave
+- after run completion
+- after quota-recovery pruning
 ```

### Multi-Tab Safety

#### Analysis And Rationale

The plan says multi-tab sync guarantees are out of scope, but that still leaves a silent data-loss risk. A lightweight warning model is enough for v1.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ### 1.4 V1 Explicitly Out Of Scope
@@
 - Multi-tab sync guarantees
@@
 ### 12.1 Persistence Strategy
@@
 Do not rely on localStorage except possibly for tiny non-critical preferences.
+
+### 12.1.1 Multi-Tab Safety
+
+Even though true multi-tab sync is out of scope, the app should still detect concurrent editing:
+
+- use `BroadcastChannel` when available to announce active workflow sessions
+- if the same workflow opens in a second tab, show a warning banner in both tabs
+- use a soft lock, not a hard block
+- last writer wins for persistence, but the risk is made explicit in the UI
 ```

### `reviewCheckpoint` Scope

#### Analysis And Rationale

The current description is still too loose for v1. A generic typed input expands validation, UI, and execution complexity. The spec should narrow it to a fixed union.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ### 5.10 `reviewCheckpoint`
@@
 Inputs:
- one generic typed input constrained by configuration or a small union of supported types
+ exactly one input from this supported union in v1:
+ - `script`
+ - `sceneList`
+ - `imageAssetList`
+ - `subtitleAsset`
+ - `videoAsset`
@@
 Behavior:
 Does not transform data automatically; it wraps and re-emits after user confirmation in mock mode.
+
+V1 review modes:
+
+- `autoApprove`
+- `manualApprove`
+- `manualReject`
+
+`approvedPayload` preserves the input schema type exactly.
 ```

### Save Vs Export Terminology

#### Analysis And Rationale

The current shortcut copy mixes local save and export. Those should be distinct user actions.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ### 6.9 Keyboard Shortcuts
@@
- `Cmd/Ctrl + S`: export/save snapshot
+ `Cmd/Ctrl + S`: save committed local snapshot
+ `Cmd/Ctrl + Shift + E`: export workflow JSON
 ```

### Preview Vs Last Run Output Ownership

#### Analysis And Rationale

The Data Inspector promises both preview and last-run outputs but never says which one is shown by default. That makes the diagnostic surface inconsistent.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ### 10.3 Preview vs Mock Execution
@@
 The UI should show both when relevant.
+
+Inspector precedence rules:
+
+- default to last successful run output when present
+- fall back to preview output when no successful run exists
+- always label the source as `preview` or `lastRun`
+- allow side-by-side comparison when both exist
 ```

### Preview Performance Rules

#### Analysis And Rationale

The plan says previews should recompute incrementally but does not give any practical performance boundary. Without one, text-field editing risks becoming laggy.

#### Git-Diff Style Change

```diff
diff --git a/PLAN.md b/PLAN.md
--- a/PLAN.md
+++ b/PLAN.md
@@
 ### 10.4 Incremental Recompute
@@
 When a node changes:
@@
 - stop at invalid nodes if required inputs are missing
+
+Performance rule for v1:
+
+- topology-change recompute should be immediate
+- text-entry config recompute should debounce by about 150 ms
+- expensive preview formatting should memoize by node input/config hash
 ```

## Acceptance Criteria

- The document records all six requested gap fixes.
- It also records the additional contradictions and underspecified areas found during review.
- It is implementation-oriented and can be used as a standalone refinement artifact without editing the attached plan file.
