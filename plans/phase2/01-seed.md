# Project Seed: AI Video Workflow Backend — Runner & API

## What
A backend service for the existing AI Video Workflow Builder frontend. Not just storage and CRUD — the backend is the **runtime** that executes workflows. It receives a workflow document, plans the execution order, runs each node by calling external AI provider APIs, streams progress back to the frontend in real time, and persists results.

The node design methodology must be consistent across frontend and backend: same conceptual model of nodes declaring typed input/output ports, config schemas, and execution contracts. The backend mirrors this contract — a node template on the backend declares the same ports, the same config shape, and implements the real execution logic (calling AI APIs) where the frontend only mocks it.

## Who
Same audience as Phase 1: developers building AI video pipelines visually. The backend enables their workflows to actually produce real outputs.

## Success
A user designs a workflow in the existing frontend canvas, hits "Run", and the backend:
1. Receives the workflow document
2. Plans execution order (topological sort, respecting dependencies)
3. Executes each node in order — calling real AI provider APIs (text generation, image generation, TTS, video composition, etc.)
4. Streams real-time progress to the frontend (node started → running → completed/failed)
5. Stores all inputs, outputs, and artifacts for each run
6. The frontend canvas updates live — status dots, output previews, edge data — as each node completes

## Core Criteria
- **Runner, not just storage**: The backend plans and executes workflows, not just saves them
- **Consistent node design**: Backend node templates follow the same interface methodology as frontend — typed ports (input/output), config schema, execution contract
- **Provider-agnostic execution**: Nodes declare what capability they need (e.g., "text-to-image"), actual AI provider is runtime-configurable. No vendor lock-in
- **Real-time progress**: WebSocket or SSE streaming of execution status and results to the frontend
- **Artifact persistence**: Generated images, audio, video, and intermediate data are stored and retrievable
- **Run history**: Every execution run is recorded — inputs, outputs, durations, errors — queryable and replayable
- **Workflow management**: CRUD for workflow documents, replacing the current IndexedDB/Dexie frontend persistence

## Tech Stack
- Laravel (PHP) — API + queue workers for execution
- PostgreSQL — workflow storage, run history, JSONB for configs and payloads
- Redis — queue broker, real-time pub/sub
- Docker Compose — single stack (app, worker, Redis, PostgreSQL)
- WebSocket or SSE — real-time execution streaming to frontend

## Architecture Sketch
```
┌─────────────┐       REST/WS        ┌──────────────────┐
│  Frontend    │◄────────────────────►│  Laravel API     │
│  (React SPA) │   real-time stream   │                  │
└─────────────┘                       │  - Workflow CRUD │
                                      │  - Run trigger   │
                                      │  - Progress WS   │
                                      └────────┬─────────┘
                                               │ dispatch
                                      ┌────────▼─────────┐
                                      │  Queue Workers    │
                                      │                   │
                                      │  - Run planner    │
                                      │  - Node executor  │
                                      │  - Provider calls │
                                      └────────┬─────────┘
                                               │
                              ┌────────────────┼────────────────┐
                              ▼                ▼                ▼
                        ┌──────────┐    ┌──────────┐    ┌──────────┐
                        │PostgreSQL│    │  Redis   │    │ Artifact │
                        │          │    │          │    │ Storage  │
                        │workflows │    │ queues   │    │(abstract)│
                        │runs      │    │ pub/sub  │    │          │
                        │payloads  │    │ cache    │    │          │
                        └──────────┘    └──────────┘    └──────────┘
```

## Key Design Principles
- **Mirror the frontend contract**: A backend `NodeTemplate` declares `inputs[]`, `outputs[]`, `configSchema`, and `execute()` — same shape as the frontend's `PortDefinition`, `DataType`, and config schemas, translated to PHP equivalents
- **Execution is a first-class concern**: Run planning (topological sort), node-by-node execution, caching, retry, and error handling are core — not bolted on
- **Provider abstraction**: Each node type maps to a "capability" (e.g., `TextToImage`, `TextGeneration`, `TextToSpeech`). Providers implement capabilities. Swapping a provider doesn't change the node
- **Artifact storage is abstract**: Interface-based. Start with local filesystem, swap to S3/R2 later without changing node code
- **The frontend is untouched initially**: Phase 2 starts by building the backend independently. Frontend integration (replacing mock executor with real API calls, replacing Dexie with API persistence) comes after the backend is solid

## Non-goals
- NOT running AI models locally (all inference via external API calls)
- NOT multi-tenant/multi-user (single-user for now, auth can come later)
- NOT a generic workflow engine (purpose-built for the existing 11 node types)
- NOT replacing the frontend — extending it with a real backend
- NOT mobile API — desktop browser frontend only

---

# Frontend Reference: Existing Design Contracts

Everything below is the source of truth for how the frontend defines nodes, types, execution, and persistence. The backend must replicate this **design methodology** (not the code) in PHP/Laravel idioms.

---

## A. Data Model (Plan Section 8)

### A.1 Semantic Data Types (17 types)

```typescript
type DataType =
  | 'text' | 'textList'
  | 'prompt' | 'promptList'
  | 'script'
  | 'scene' | 'sceneList'
  | 'imageFrame' | 'imageFrameList'
  | 'imageAsset' | 'imageAssetList'
  | 'audioPlan' | 'audioAsset'
  | 'subtitleAsset'
  | 'videoAsset'
  | 'reviewDecision'
  | 'json';
```

### A.2 Port Definition

Every node declares its inputs and outputs as typed ports:

```typescript
interface PortDefinition {
  readonly key: string;           // e.g., 'prompt', 'script', 'sceneList'
  readonly label: string;         // Human-readable label
  readonly direction: 'input' | 'output';
  readonly dataType: DataType;    // Semantic type from the 17-type system
  readonly required: boolean;
  readonly multiple: boolean;
  readonly description?: string;
}
```

### A.3 Workflow Document

The top-level document that represents a workflow:

```typescript
interface WorkflowNode<TConfig = unknown> {
  readonly id: string;
  readonly type: string;          // Maps to a registered node template
  readonly label: string;
  readonly position: { readonly x: number; readonly y: number };
  readonly config: Readonly<TConfig>;
  readonly disabled?: boolean;
  readonly notes?: string;
}

interface WorkflowEdge {
  readonly id: string;
  readonly sourceNodeId: string;
  readonly sourcePortKey: string;
  readonly targetNodeId: string;
  readonly targetPortKey: string;
  readonly targetOrder?: number;
}

interface WorkflowDocument {
  readonly id: string;
  readonly schemaVersion: number;
  readonly name: string;
  readonly description: string;
  readonly tags: readonly string[];
  readonly nodes: readonly WorkflowNode[];
  readonly edges: readonly WorkflowEdge[];
  readonly viewport: { readonly x: number; readonly y: number; readonly zoom: number };
  readonly createdAt: string;
  readonly updatedAt: string;
  readonly basedOnTemplateId?: string;
  readonly basedOnTemplateVersion?: string;
}
```

### A.4 Port Payload Wrapper

Every piece of data flowing between nodes is wrapped in a payload:

```typescript
interface PortPayload<TValue = unknown> {
  readonly value: TValue | null;
  readonly status: 'idle' | 'ready' | 'running' | 'success' | 'error' | 'skipped' | 'cancelled';
  readonly schemaType: DataType;
  readonly producedAt?: string;
  readonly sourceNodeId?: string;
  readonly sourcePortKey?: string;
  readonly previewText?: string;
  readonly previewUrl?: string;
  readonly sizeBytesEstimate?: number;
  readonly errorMessage?: string;
}
```

- `ready` = preview-derived, available before execution
- `success` = produced by execution or reused from cache

### A.5 Run Model

```typescript
interface ExecutionRun {
  readonly id: string;
  readonly workflowId: string;
  readonly mode: 'mock';  // Phase 2 adds 'live' mode
  readonly trigger: 'runWorkflow' | 'runNode' | 'runFromHere' | 'runUpToHere';
  readonly targetNodeId?: string;
  readonly plannedNodeIds: readonly string[];
  readonly status: 'pending' | 'running' | 'awaitingReview' | 'success' | 'error' | 'cancelled' | 'interrupted';
  readonly startedAt: string;
  readonly completedAt?: string;
  readonly terminationReason?: 'completed' | 'nodeError' | 'userCancelled' | 'tabClosed' | 'recoveredAfterCrash';
  readonly documentHash: string;
  readonly nodeConfigHashes: Readonly<Record<string, string>>;
}

interface NodeRunRecord {
  readonly runId: string;
  readonly nodeId: string;
  readonly status: 'pending' | 'running' | 'awaitingReview' | 'success' | 'error' | 'skipped' | 'cancelled';
  readonly skipReason?: 'disabled' | 'missingRequiredInputs' | 'upstreamFailed';
  readonly blockedByNodeIds?: readonly string[];
  readonly startedAt?: string;
  readonly completedAt?: string;
  readonly durationMs?: number;
  readonly inputPayloads: Readonly<Record<string, PortPayload>>;
  readonly outputPayloads: Readonly<Record<string, PortPayload>>;
  readonly errorMessage?: string;
  readonly usedCache: boolean;
}

interface EdgePayloadSnapshot {
  readonly edgeId: string;
  readonly sourcePayload: PortPayload;
  readonly transportedPayload: PortPayload;
  readonly coercionApplied?: string;
}
```

### A.6 Execution Plan

```typescript
interface ExecutionPlan {
  readonly runId: string;
  readonly workflowId: string;
  readonly trigger: ExecutionRun['trigger'];
  readonly targetNodeId?: string;
  readonly scopeNodeIds: readonly string[];    // All nodes in execution scope
  readonly orderedNodeIds: readonly string[];   // Topologically sorted for execution
  readonly skippedNodeIds: readonly string[];   // Disabled or blocked
}
```

### A.7 Validation Model

```typescript
interface ValidationIssue {
  readonly id: string;
  readonly severity: 'error' | 'warning' | 'info';
  readonly scope: 'workflow' | 'node' | 'edge' | 'port' | 'config';
  readonly message: string;
  readonly nodeId?: string;
  readonly edgeId?: string;
  readonly portKey?: string;
  readonly code:
    | 'cycleDetected' | 'missingRequiredInput' | 'incompatiblePortTypes'
    | 'configInvalid' | 'orphanNode' | 'disabledNode'
    | 'coercionApplied' | 'downstreamInvalidated';
  readonly suggestion?: string;
}
```

### A.8 Compatibility Model

```typescript
interface CompatibilityResult {
  readonly compatible: boolean;
  readonly coercionApplied: boolean;
  readonly severity: 'none' | 'warning' | 'error';
  readonly reason?: string;
  readonly suggestedAdapterNodeType?: string;
}
```

Type compatibility rules:
- Exact match (e.g., `script → script`): always compatible
- Safe scalar-to-list wrapping (e.g., `text → textList`, `prompt → promptList`): compatible with warning
- Incompatible without adapter (e.g., `imageFrameList → imageAssetList`): error, suggests adapter node
- List-to-scalar: always incompatible

---

## B. Node Template Contract

### B.1 Template Interface

Every node template declares the same shape:

```typescript
interface NodeTemplateBase<TConfig> {
  readonly type: string;                    // Unique identifier, e.g., 'scriptWriter'
  readonly templateVersion: string;         // Semver, e.g., '1.0.0'
  readonly title: string;                   // Human-readable, e.g., 'Script Writer'
  readonly category: 'input' | 'script' | 'visuals' | 'audio' | 'video' | 'utility' | 'output';
  readonly description: string;
  readonly inputs: readonly PortDefinition[];
  readonly outputs: readonly PortDefinition[];
  readonly defaultConfig: Readonly<TConfig>;
  readonly configSchema: ZodType<TConfig>;  // Runtime config validation
  readonly fixtures: readonly NodeFixture<TConfig>[];
  readonly buildPreview: (args: {
    config: Readonly<TConfig>;
    inputs: Readonly<Record<string, PortPayload>>;
  }) => Readonly<Record<string, PortPayload>>;
}

// Discriminated union: executable vs non-executable
type NodeTemplate<TConfig> =
  | (NodeTemplateBase<TConfig> & { executable: false })
  | (NodeTemplateBase<TConfig> & {
      executable: true;
      mockExecute: (args: MockNodeExecutionArgs<TConfig>) => Promise<Record<string, PortPayload>>;
    });

interface MockNodeExecutionArgs<TConfig> {
  readonly nodeId: string;
  readonly config: Readonly<TConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
  readonly signal: AbortSignal;
  readonly runId: string;
}

interface NodeFixture<TConfig> {
  readonly id: string;
  readonly label: string;
  readonly config?: Partial<TConfig>;
  readonly previewInputs?: Readonly<Record<string, PortPayload>>;
  readonly executionInputs?: Readonly<Record<string, PortPayload>>;
}
```

### B.2 The 11 Node Templates

| Type                 | Category | Inputs                    | Outputs                  | Executable |
|----------------------|----------|---------------------------|--------------------------|------------|
| `userPrompt`         | input    | _(none)_                  | prompt                   | false      |
| `scriptWriter`       | script   | prompt                    | script                   | true       |
| `sceneSplitter`      | script   | script                    | sceneList                | true       |
| `promptRefiner`      | script   | sceneList                 | promptList               | true       |
| `imageGenerator`     | visuals  | sceneList OR promptList   | imageFrameList OR imageAssetList | true |
| `imageAssetMapper`   | visuals  | imageFrameList            | imageAssetList           | true       |
| `ttsVoiceoverPlanner`| audio    | script                    | audioPlan                | true       |
| `subtitleFormatter`  | audio    | script, audioPlan         | subtitleAsset            | true       |
| `videoComposer`      | video    | imageAssetList, audioAsset, subtitleAsset | videoAsset | true |
| `reviewCheckpoint`   | utility  | _(any)_                   | reviewDecision           | true       |
| `finalExport`        | output   | videoAsset                | _(file output)_          | true       |

### B.3 Example: scriptWriter Template

Shows the full pattern — config schema, ports, preview, mock execution:

```typescript
// Config schema (validated at runtime via Zod)
const ScriptWriterConfigSchema = z.object({
  style: z.string().min(1).max(200),
  structure: z.enum(['three_act', 'problem_solution', 'story_arc', 'listicle']),
  includeHook: z.boolean(),
  includeCTA: z.boolean(),
  targetDurationSeconds: z.number().int().min(5).max(600),
});

type ScriptWriterConfig = z.infer<typeof ScriptWriterConfigSchema>;

// Ports
const inputs: PortDefinition[] = [
  { key: 'prompt', label: 'Prompt', direction: 'input', dataType: 'prompt', required: true, multiple: false }
];
const outputs: PortDefinition[] = [
  { key: 'script', label: 'Script', direction: 'output', dataType: 'script', required: true, multiple: false }
];

// Default config
const defaultConfig: ScriptWriterConfig = {
  style: 'Clear, conversational narration with concrete examples',
  structure: 'three_act',
  includeHook: true,
  includeCTA: true,
  targetDurationSeconds: 90,
};

// The template object
const scriptWriterTemplate: NodeTemplate<ScriptWriterConfig> = {
  type: 'scriptWriter',
  templateVersion: '1.0.0',
  title: 'Script Writer',
  category: 'script',
  description: 'Turns a structured prompt into a script object...',
  inputs, outputs, defaultConfig,
  configSchema: ScriptWriterConfigSchema,
  fixtures: [...],
  executable: true,
  buildPreview,     // Synchronous preview from config + inputs
  mockExecute,      // Async mock — Phase 2 backend replaces this with real AI API call
};
```

### B.4 Example: imageGenerator Template (config-dependent ports)

Some nodes change active ports based on config:

```typescript
const ImageGeneratorConfigSchema = z.object({
  inputMode: z.enum(['scenes', 'prompts']),   // Activates sceneList OR promptList input
  outputMode: z.enum(['frames', 'assets']),   // Activates imageFrameList OR imageAssetList output
  stylePreset: z.enum(['default', 'cinematic', 'vivid', 'subdued']),
  resolution: z.enum(['512x512', '1024x1024', '1024x1536', '1536x1024']),
  seedStrategy: z.enum(['deterministic', 'random']),
});

// Both input ports declared, but only one is active based on config.inputMode:
const inputPorts: PortDefinition[] = [
  { key: 'sceneList', dataType: 'sceneList', required: false, ... },  // Active when inputMode='scenes'
  { key: 'promptList', dataType: 'promptList', required: false, ... }, // Active when inputMode='prompts'
];

// Both output ports declared, but only one produces data based on config.outputMode:
const outputPorts: PortDefinition[] = [
  { key: 'imageFrameList', dataType: 'imageFrameList', required: false, ... },
  { key: 'imageAssetList', dataType: 'imageAssetList', required: false, ... },
];
```

### B.5 Registry

All 11 templates are registered in a singleton registry:

```typescript
class NodeTemplateRegistry {
  private templates = new Map<string, NodeTemplate<unknown>>();
  register<TConfig>(template: NodeTemplate<TConfig>): void;
  get<TConfig>(type: string): NodeTemplate<TConfig> | undefined;
  getAll(): NodeTemplate<unknown>[];
  getByCategory(category: string): NodeTemplate<unknown>[];
  getMetadata(): TemplateMetadata[];
}
```

---

## C. Execution Engine

### C.1 Run Planner

Plans which nodes to execute and in what order:

1. **Scope extraction** by trigger type:
   - `runWorkflow`: all non-disabled nodes
   - `runNode`: target node + upstream providers
   - `runFromHere`: target node + all downstream
   - `runUpToHere`: all upstream + target node

2. **Prune disabled nodes** from scope

3. **Topological sort** (Kahn's algorithm) over the scoped subgraph

Graph traversal helpers:
- `collectUpstreamNodeIds(targetNodeId, incomingEdgeIndex)` — BFS/DFS backward through edges
- `collectDownstreamNodeIds(sourceNodeId, outgoingEdgeIndex)` — BFS/DFS forward through edges

### C.2 Executor Loop

The main execution loop (currently mock, backend replaces with real):

```
for each nodeId in orderedNodeIds:
  1. Check abort signal → cancel remaining if aborted
  2. Skip disabled nodes → record as skipped('disabled')
  3. Resolve inputs:
     - Priority 1: successful upstream output from active run
     - Priority 2: reusable cache entry
     - Priority 3: preview output from non-executable node
     - If required input unresolved → skip('missingRequiredInputs' or 'upstreamFailed')
  4. Non-executable node → use buildPreview() output, record as success
  5. Check cache (keyed by nodeType + templateVersion + schemaVersion + configHash + inputHash)
     - Cache hit → record as success(usedCache=true)
  6. Execute node:
     - Mark 'running'
     - Call mockExecute() (→ Phase 2: call real provider API)
     - On success → cache result, record as success
     - On error → record as error, downstream will be skipped
     - On abort → cancel node and all remaining
  7. After all nodes: derive final run status from node states
```

### C.3 Cache

Execution results are cached by composite key:
- `nodeType:templateVersion:schemaVersion:configHash:inputHash`

Cache reuse requires ALL parts to match. LRU eviction with configurable max entries.

Input normalization for hashing strips transient fields (producedAt, sourceNodeId, sourcePortKey).

### C.4 Review Handler

`reviewCheckpoint` nodes pause execution and wait for human decision:
- Node enters `awaitingReview` status
- Run pauses until user approves or rejects
- Decision stored as `reviewDecision` payload
- On cancel: review is auto-rejected

### C.5 Node Status Lifecycle

```
pending → running → success
                  → error
                  → cancelled
       → skipped (disabled | missingRequiredInputs | upstreamFailed)
       → awaitingReview → success (approved) | error (rejected)
```

### C.6 Failure Semantics

- The node that fails becomes `error`
- Downstream nodes whose required inputs depend on the failed output become `skipped(upstreamFailed)`
- Optional inputs from failed nodes are simply omitted
- `error` = "this node itself failed"; `skipped` = "this node was not runnable"

### C.7 Cancellation

Every active node receives an `AbortSignal`. On cancel:
- Active signal aborts
- Current running node → `cancelled`
- Downstream pending nodes → `cancelled`
- Run status → `cancelled`

---

## D. Persistence (Current Frontend — to be replaced by backend)

Currently uses Dexie/IndexedDB with these tables:

| Table              | Purpose                                      |
|--------------------|----------------------------------------------|
| workflows          | Workflow documents (CRUD)                    |
| workflowSnapshots  | Autosave + crash recovery snapshots          |
| executionRuns      | Run history (status, trigger, timing)        |
| nodeRunRecords     | Per-node execution results (inputs, outputs) |
| runCacheEntries    | Execution cache for reuse                    |
| appPreferences     | User settings                                |

The backend should provide equivalent storage via PostgreSQL, replacing the frontend's IndexedDB persistence entirely.
