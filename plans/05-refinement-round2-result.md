# AI Video Workflow Builder Review Findings

This file captures the remaining issues found after applying the Round 1 refinement mentally to the base plan.

## Summary

- Total findings: 37
- Assessment: not ready for implementation yet

## Findings

### 1. `WorkflowDocument` version field contradicts migration language

Problem:
`WorkflowDocument` still hard-codes `version: 2`, while the rest of the plan talks about `schemaVersion` and future migrations.

Why it matters:
The type cannot represent future imported documents cleanly and conflicts with the metadata language elsewhere.

```diff
@@ 8.4 Workflow Document Entities
 export interface WorkflowDocument {
   readonly id: string;
-  readonly version: 2;
+  readonly schemaVersion: number;
   readonly name: string;
@@
 ### 1.3 V1 In Scope
 - Workflow metadata:
   - name
   - description
   - tags
   - createdAt
   - updatedAt
   - schemaVersion
```

### 2. Cache invalidation references a missing node template version

Problem:
The cache schema refers to `nodeTemplateVersion`, but `NodeTemplate` has no version field.

Why it matters:
Deterministic cache invalidation cannot be implemented.

```diff
@@ 8.9 Node Template Contract
 export interface NodeTemplate<TConfig> {
   readonly type: string;
+  readonly templateVersion: string;
   readonly title: string;
```

### 3. `scriptWriter` example contradicts the catalog definition

Problem:
The example config/schema for `scriptWriter` does not match the earlier node catalog.

Why it matters:
The example cannot serve as a trustworthy implementation reference.

```diff
@@ 15. Example Node Template
 const scriptWriterConfigSchema = z.object({
-  topic: z.string().min(3),
-  tone: z.union([
-    z.literal('educational'),
-    z.literal('cinematic'),
-    z.literal('playful'),
-    z.literal('dramatic'),
-  ]),
-  durationSeconds: z.number().int().min(15).max(180),
+  style: z.string().min(1),
+  structure: z.string().min(1),
+  includeHook: z.boolean(),
   includeCTA: z.boolean(),
+  targetDurationSeconds: z.number().int().min(15).max(180),
 });
```

### 4. Journey 1 still references a `Preview` tab that no longer exists

Problem:
Journey 1 says the user opens a Preview tab, but the inspector tab list omits it.

Why it matters:
A core UX walkthrough references nonexistent UI.

```diff
@@ 6.4 Right Panel: Inspector
 Tabs for node mode:
   - `Config`
+  - `Preview`
   - `Data`
   - `Validation`
   - `Metadata`
```

### 5. `PortPayload.status` includes `ready` but never defines it

Problem:
The status union contains `ready`, but the plan never says when it should be used.

Why it matters:
Preview/run UI and state logic will diverge inconsistently.

```diff
@@ 8.5 Port Payload Wrapper
 export interface PortPayload<TValue = unknown> {
   readonly value: TValue | null;
   readonly status: 'idle' | 'ready' | 'running' | 'success' | 'error' | 'skipped' | 'cancelled';
@@
 }
+
+`ready` means "preview-derived and available before mock execution".
+`success` means "produced by mock execution or reused from run cache".
```

### 6. Fixtures are still raw inputs instead of wrapped payloads

Problem:
`NodeFixture.sampleInputs` is typed as raw `unknown` values.

Why it matters:
Fixture behavior is underspecified for both preview and mock execution.

```diff
@@ 8.9 Node Template Contract
 export interface NodeFixture<TConfig> {
   readonly id: string;
   readonly label: string;
   readonly config?: Partial<TConfig>;
-  readonly sampleInputs?: Record<string, unknown>;
+  readonly previewInputs?: Readonly<Record<string, PortPayload>>;
+  readonly executionInputs?: Readonly<Record<string, PortPayload>>;
 }
```

### 7. `buildPreview()` still returns raw values

Problem:
Preview builders return raw objects instead of `PortPayload` wrappers.

Why it matters:
The Data Inspector cannot compare preview and last-run outputs consistently.

```diff
@@ 8.9 Node Template Contract
   readonly buildPreview: (args: {
     readonly config: Readonly<TConfig>;
-    readonly inputs: Readonly<Record<string, unknown>>;
-  }) => Readonly<Record<string, unknown>>;
+    readonly inputs: Readonly<Record<string, PortPayload>>;
+  }) => Readonly<Record<string, PortPayload>>;
```

### 8. `reviewCheckpoint` still lacks a paused state

Problem:
Manual review mode has no `awaitingReview` run or node status.

Why it matters:
The run model cannot honestly represent a paused review step.

```diff
@@ 8.7 Run Model
 export interface ExecutionRun {
@@
-  readonly status: 'pending' | 'running' | 'success' | 'error' | 'cancelled' | 'interrupted';
+  readonly status: 'pending' | 'running' | 'awaitingReview' | 'success' | 'error' | 'cancelled' | 'interrupted';
@@
 export interface NodeRunRecord {
@@
-  readonly status: 'pending' | 'running' | 'success' | 'error' | 'skipped' | 'cancelled';
+  readonly status: 'pending' | 'running' | 'awaitingReview' | 'success' | 'error' | 'skipped' | 'cancelled';
@@ 11.10 Manual Review Nodes
+When `manualApprove` is active, the node enters `awaitingReview` and the run pauses until the user approves or rejects.
```

### 9. `reviewCheckpoint` output typing still does not fit static port definitions

Problem:
The plan says `approvedPayload` preserves the input type exactly, but port definitions are statically typed.

Why it matters:
The current model cannot express "same type as selected input" safely.

```diff
@@ 5.10 `reviewCheckpoint`
-Outputs:
-- `approvedPayload`
+Outputs:
+- `approvedScript: script`
+- `approvedSceneList: sceneList`
+- `approvedImageAssetList: imageAssetList`
+- `approvedSubtitleAsset: subtitleAsset`
+- `approvedVideoAsset: videoAsset`
 - `reviewDecision`
@@
+Exactly one input/output pair is active per checkpoint config; inactive pairs remain disconnected and disabled.
```

### 10. Runs still lack an immutable workflow snapshot contract

Problem:
The plan does not explicitly require a run to execute against a frozen workflow snapshot.

Why it matters:
Mid-run edits can change the meaning of planned execution.

```diff
@@ 11.2 Execution Modes
+All runs execute against an immutable workflow snapshot captured at run start.
@@ 8.7 Run Model
 export interface ExecutionRun {
@@
+  readonly documentHash: string;
+  readonly nodeConfigHashes: Readonly<Record<string, string>>;
```

### 11. Deleting a running node or edge is still undefined

Problem:
The spec does not state what happens if the user deletes graph elements during an active run.

Why it matters:
The executor can reference missing topology or silently keep running stale data.

```diff
@@ 12.4 Undo/Redo
+If a node or edge participating in an active run is deleted, the active run continues against its captured snapshot and the edited workflow is marked `runStale` until the next run.
```

### 12. Toggling `disabled` during a run is still undefined

Problem:
The spec does not state whether `disabled` changes affect the current run.

Why it matters:
Skip semantics become nondeterministic.

```diff
@@ 11.5 Node Status Lifecycle
+The `disabled` flag is read from the run-start snapshot.
+Changing `disabled` during an active run affects only future runs.
```

### 13. Autosave during an active run is underspecified

Problem:
The plan does not define how autosave and active-run recovery snapshots stay aligned.

Why it matters:
Recovery can restore a document that does not match the interrupted run it claims to represent.

```diff
@@ 12.3 Autosave
 Autosave should save a document snapshot, not transient UI noise.
+
+During an active run, autosave must write:
+- the current document snapshot
+- a separate recovery snapshot keyed by `documentHash` and `activeRunId`
+
+These writes must be committed atomically.
```

### 14. `WorkflowSnapshot` lacks enough interrupted-run context

Problem:
It stores only `interruptedRunId`.

Why it matters:
The app cannot reconstruct a trustworthy recovery UI.

```diff
@@ 8.10 Crash Recovery Snapshot
 export interface WorkflowSnapshot {
@@
   readonly interruptedRunId?: string;
+  readonly activeRunSummary?: {
+    readonly runId: string;
+    readonly trigger: ExecutionRun['trigger'];
+    readonly status: ExecutionRun['status'];
+    readonly currentNodeId?: string;
+    readonly plannedNodeIds: readonly string[];
+  };
 }
```

### 15. `runWorkflow` still uses undefined "reachable" semantics

Problem:
The plan says `runWorkflow` runs all reachable nodes, but never defines roots.

Why it matters:
Disconnected components are ambiguous.

```diff
@@ 11.3 Run Planning
 #### `runWorkflow`
 
-Plan all executable nodes reachable in a valid topological order.
+Plan all executable nodes in the current workflow document that are not excluded by validation or explicit disablement.
```

### 16. `outsideScope` is still modeled as a skip reason

Problem:
Out-of-scope nodes normally are not planned at all, but `outsideScope` still appears in skip semantics.

Why it matters:
The spec mixes "not part of the run" with "planned but skipped".

```diff
@@ 11.5 Node Status Lifecycle
 A node is `skipped` if:
   - it is disabled
   - an upstream required dependency failed
-  - it lies outside selected run scope
@@ 11.9.1 Downstream Error Propagation
-  readonly skipReason?: 'disabled' | 'outsideScope' | 'missingRequiredInputs' | 'upstreamFailed';
+  readonly skipReason?: 'disabled' | 'missingRequiredInputs' | 'upstreamFailed';
```

### 17. `cacheSatisfied` still conflicts with cache-hit success semantics

Problem:
The type still treats cache satisfaction as a skip reason, but the executor writes cache hits as success.

Why it matters:
Status reporting would disagree across the system.

```diff
@@ 11.9.1 Downstream Error Propagation
-  readonly skipReason?: 'disabled' | 'missingRequiredInputs' | 'upstreamFailed' | 'cacheSatisfied';
+  readonly skipReason?: 'disabled' | 'missingRequiredInputs' | 'upstreamFailed';
@@
 Cache hits remain `success` with `usedCache: true`.
```

### 18. Planning still mixes executable and non-executable scope unclearly

Problem:
The plan still says execution scope is executable-only, while the loop also consumes non-executable upstream providers.

Why it matters:
Plan counts and run summaries become inconsistent.

```diff
@@ 11.3 Run Planning
-The `RunPlanner` determines execution scope.
+The `RunPlanner` determines:
+- `resolvedNodeIds`: nodes needed to hydrate inputs, including non-executable providers
+- `executedNodeIds`: executable nodes that may actually run
```

### 19. `NodeTemplate` still allows `executable: true` without `mockExecute`

Problem:
The type does not enforce a mock executor for executable nodes.

Why it matters:
The runtime relies on a non-null assertion and can crash.

```diff
@@ 8.9 Node Template Contract
-export interface NodeTemplate<TConfig> {
-  readonly executable: boolean;
-  readonly mockExecute?: (
-    args: MockNodeExecutionArgs<TConfig>,
-  ) => Promise<Readonly<Record<string, PortPayload>>>;
-}
+export type NodeTemplate<TConfig> =
+  | {
+      readonly executable: false;
+      readonly mockExecute?: never;
+      /* other shared fields */
+    }
+  | {
+      readonly executable: true;
+      readonly mockExecute: (
+        args: MockNodeExecutionArgs<TConfig>,
+      ) => Promise<Readonly<Record<string, PortPayload>>>;
+      /* other shared fields */
+    };
```

### 20. Multi-input ports still have no ordering contract

Problem:
The data model does not define edge order for `multiple: true` inputs.

Why it matters:
Preview, execution, hashing, and export can all become nondeterministic.

```diff
@@ 8.4 Workflow Document Entities
 export interface WorkflowEdge {
   readonly id: string;
   readonly sourceNodeId: string;
   readonly sourcePortKey: string;
   readonly targetNodeId: string;
   readonly targetPortKey: string;
+  readonly targetOrder?: number;
 }
@@ 16.3 Edge-Level Rules
+For `multiple: true` inputs, resolved input arrays are ordered by `targetOrder`, then edge creation time.
```

### 21. Edge payloads are still not first-class data

Problem:
The UI promises selected edge payload inspection, but the model only defines port payloads.

Why it matters:
Per-edge coercion and transport metadata have nowhere to live.

```diff
@@ 8.7 Run Model
+export interface EdgePayloadSnapshot {
+  readonly edgeId: string;
+  readonly sourcePayload: PortPayload;
+  readonly transportedPayload: PortPayload;
+  readonly coercionApplied?: string;
+}
@@ 6.5 Data Inspector As First-Class Feature
-- selected edge payload
+- selected edge payload via `EdgePayloadSnapshot`
```

### 22. Import still does not validate semantically broken edges

Problem:
Import validates references and config, but not semantic compatibility.

Why it matters:
A workflow can import structurally but remain unusable.

```diff
@@ 12.5 Import/Export
 5. validate every node against registry
 6. validate edge references
-7. validate configs
-8. surface import report:
+7. validate semantic compatibility for every edge against the compatibility registry
+8. validate configs
+9. surface import report:
    - imported successfully
    - migrated
    - warnings
    - errors
+   - semantically broken edges
```

### 23. Templates still are not version-checked against the registry

Problem:
The template system does not specify registry-version validation.

Why it matters:
Built-in templates can silently drift out of compatibility.

```diff
@@ 17.3 Template Requirements
+Each built-in template must declare:
+- `templateVersion`
+- `registryVersion`
+- `minimumWorkflowSchemaVersion`
+
+Template instantiation must validate against the current node registry before creation.
```

### 24. Template provenance still lacks a version

Problem:
`basedOnTemplateId` exists without a matching template version.

Why it matters:
Forked workflows lose useful provenance and upgrade warning ability.

```diff
@@ 8.4 Workflow Document Entities
   readonly basedOnTemplateId?: string;
+  readonly basedOnTemplateVersion?: string;
```

### 25. BroadcastChannel multi-tab safety still lacks session semantics

Problem:
The soft-lock model still lacks `sessionId`, heartbeat, and expiry rules.

Why it matters:
Ghost tabs and self-conflicts are likely.

```diff
@@ 12.1.1 Multi-Tab Safety
 Even though true multi-tab sync is out of scope, the app should still detect concurrent editing:
 
 - use `BroadcastChannel` when available to announce active workflow sessions
+- each tab gets a `sessionId`
+- broadcast a heartbeat every 5 seconds
+- expire sessions after 15 seconds without heartbeat
+- ignore announcements from the current `sessionId`
 - if the same workflow opens in a second tab, show a warning banner in both tabs
```

### 26. GC rules can still prune data needed for recovery

Problem:
The retention policy does not protect recovery-linked rows.

Why it matters:
Recovery can break due to dangling references after pruning.

```diff
@@ 12.3.1 Retention Policy
 Garbage collection triggers:
 
 - after successful autosave
 - after run completion
 - after quota-recovery pruning
+
+Never prune:
+- the latest recovery snapshot per workflow
+- any run referenced by a recovery snapshot
+- cache rows referenced by the latest successful run for the currently open workflow
```

### 27. Degraded persistence UI is still not explicit enough

Problem:
The plan defines fallback modes but not a clear always-visible degraded-mode warning.

Why it matters:
Users may think session-only edits are durable.

```diff
@@ 6.1 Primary Layout
+If persistence mode is `memory-fallback` or `unavailable`, show a persistent warning banner with:
+- current mode
+- durability warning
+- `Export Workflow JSON`
+- `Retry Persistence`
@@ 7.3.4 Initial Render Contract
+`AppShell` must render the degraded-mode banner before the user edits the document.
```

### 28. Storyboard preview still does not define zero-image behavior

Problem:
The video preview contract still does not say what happens when image generation produces no images.

Why it matters:
The player can appear broken instead of intentionally empty.

```diff
@@ 18.2.1 Mock Video Preview Contract
+If `timeline.length === 0` or upstream image generation yields no images:
+- render an empty-state poster area
+- show subtitle/audio metadata if present
+- label the preview `metadataOnly`
+- never present playback as successful video output
```

### 29. `ttsVoiceoverPlanner` conflates plan data with an audio asset

Problem:
It outputs `audioPlan: audioAsset`.

Why it matters:
Planning metadata and rendered media are different contracts.

```diff
@@ 8.2 Semantic Data Types
   | 'imageAssetList'
+  | 'audioPlan'
   | 'audioAsset'
@@ 5.7 `ttsVoiceoverPlanner`
-Outputs:
-- `audioPlan: audioAsset`
+Outputs:
+- `audioPlan: audioPlan`
@@ 5.8 `subtitleFormatter`
-- optional `audioPlan: audioAsset`
+- optional `audioPlan: audioPlan`
@@ 5.9 `videoComposer`
-- optional `audioPlan: audioAsset`
+- optional `audioPlan: audioPlan`
```

### 30. Command/file structure still misses several declared actions

Problem:
The file structure does not include homes for many promised commands and shortcuts.

Why it matters:
The command-oriented architecture is not fully specified.

```diff
@@ 13. File Structure
 │   ├── workflow/
 │   │   ├── commands/
 │   │   │   ├── add-node.ts
 │   │   │   ├── connect-ports.ts
+│   │   │   ├── disconnect-edge.ts
+│   │   │   ├── insert-node-on-edge.ts
+│   │   │   ├── delete-selection.ts
+│   │   │   ├── duplicate-node.ts
 │   │   │   ├── update-node-config.ts
 │   │   │   └── history.ts
+│   ├── execution/
+│   │   ├── commands/
+│   │   │   ├── run-workflow.ts
+│   │   │   ├── run-node.ts
+│   │   │   ├── run-from-here.ts
+│   │   │   ├── run-up-to-here.ts
+│   │   │   └── cancel-run.ts
```

### 31. Boot state machine still lacks explicit tests

Problem:
Round 1 adds boot sequencing, but testing still does not mention it.

Why it matters:
Boot/recovery logic is high-risk and regression-prone.

```diff
@@ 19.4 Integration Tests
 Test:
+ - boot with IndexedDB available
+ - boot in memory-fallback mode
+ - boot with recovery snapshot present
+ - boot with fatal repository failure
   - editing config recomputes preview
   - running a node writes run records
```

### 32. Retention and quota-recovery logic still lack tests

Problem:
The plan defines GC and quota recovery but assigns no tests.

Why it matters:
These failures often appear only after prolonged use and can cause data loss.

```diff
@@ 19.2 Unit Tests
 Test:
   - topological sort
   - cycle detection
+  - retention pruning preserves protected recovery rows
+  - quota recovery retries once after cache pruning
   - compatibility matrix
```

### 33. BroadcastChannel warning behavior still lacks tests

Problem:
The new multi-tab warning model is specified but untested.

Why it matters:
Timing-sensitive browser coordination is easy to break.

```diff
@@ 19.5 E2E Tests
 10. Refresh during active run and recover interrupted state.
+11. Open the same workflow in two tabs and show the soft-lock warning in both.
+12. Close one tab and clear the warning after heartbeat expiry.
```

### 34. Edge insertion still lacks explicit undo/redo tests

Problem:
Round 1 adds atomic insertion semantics, but tests still do not cover history behavior.

Why it matters:
This is a high-risk command/history interaction.

```diff
@@ 19.4 Integration Tests
 Test:
   - editing config recomputes preview
   - running a node writes run records
+  - insert adapter node on an edge as one atomic command
+  - undo restores the original edge in one history step
+  - redo re-inserts the node with the same resolved ports
```

### 35. React Flow performance envelope is still not explicit

Problem:
The plan still does not define a supported graph-size target.

Why it matters:
Performance work cannot be scoped or verified.

```diff
@@ 18.2 Memory Constraints
+Graph-size target for v1:
+- support smooth authoring up to 15 nodes / 25 edges on a mid-range laptop
+- warn when the workflow exceeds that envelope
```

### 36. Persistence write budget is still undefined

Problem:
The plan does not define a serialized workflow size budget.

Why it matters:
Autosave can become sluggish before storage fully fails.

```diff
@@ 18.2 Memory Constraints
+Persistence budget for v1:
+- target serialized `WorkflowDocument` size <= 1 MB
+- warn at 750 KB
+- degrade rich artifact persistence to metadata-only above 1 MB
```

### 37. Data Inspector payload-size behavior is still too vague

Problem:
The plan says to truncate intelligently but gives no rendering thresholds.

Why it matters:
The inspector can become sluggish or freeze on large payloads.

```diff
@@ 6.5 Data Inspector As First-Class Feature
 If a payload is too large, truncate intelligently and provide:
 
 - expand
 - copy
 - download JSON
+
+Large-payload policy:
+- inline render up to 256 KB
+- collapse and virtualize between 256 KB and 2 MB
+- require download above 2 MB
@@ 19.3 Component Tests
 Test:
   - node library filtering
   - inspector form rendering
+  - payload truncation, virtualization, and copy/download behavior at each threshold
```

## Overall Assessment

The plan is not ready for implementation yet. It is close, but it still needs another refinement round.

The main blockers are:

1. Run snapshot semantics versus live editing.
2. `reviewCheckpoint` state and type modeling.
3. Template and registry version compatibility.
4. Recovery and degraded-mode UX honesty.
5. Explicit performance envelopes and tests for the newly added safety mechanisms.
