# AiModel — Workflow Specification

**Purpose.** The generic contract: what a workflow is, what a node is, how data flows, how runs are executed. Stack-agnostic — no Laravel/Python specifics.

Per-node designs live under [`docs/nodes/`](./nodes/README.md). Don't put node details in this file.

---

## 0. Two-app architecture

The system is split into two independent processes that talk over MCP.

```
┌──────────────────────────────────┐         ┌─────────────────────────────┐
│  AI Assistant (App 2)            │         │  Core App (App 1)           │
│  - Planner (composes workflows)  │  MCP    │  - Workflow runner          │
│  - Controller (drives runs)      │ ──────► │  - Persistence              │
│  - Conversation UI (Telegram,    │         │  - Node registry + executor│
│    web chat, etc.)               │         │  - MCP server (contract)    │
└──────────────────────────────────┘         └─────────────────────────────┘
```

- **App 1 — Core.** Stateless executor + stateful persistence. Owns the workflow JSON, the execution run lifecycle, the node registry, and the MCP server that exposes it all. No LLM knowledge beyond what individual nodes call out to. Can run headless indefinitely; a power user can drive it directly via MCP with no AI layer involved.
- **App 2 — Assistant.** The conversational layer. Uses App 1 as a tool. Composes workflows from natural-language briefs, runs them, reports status, surfaces human gates back to the user. Can be rewritten, replaced, or run in multiple flavors (Telegram, web, CLI) without touching App 1.

**Contract between them: MCP.** Every operation App 2 needs on App 1 is an MCP tool call. No shared database, no direct imports, no language coupling. App 1 can be Python, App 2 can be whatever.

This doc specifies App 1's workflow/node contract. The MCP surface and App 2's behavior are documented separately.

---

## 1. The big picture

A **workflow** is a directed acyclic graph (DAG) of **nodes** connected by **edges**. When a workflow **runs**, the runner walks the graph in topological order and executes each node, passing typed payloads along edges.

Typical workflow (school-uniform TikTok):

```
telegramTrigger → productAnalyzer → storyWriter ─► sceneSplitter ─► promptRefiner ─► wanR2V ─► videoComposer ─► telegramDeliver
```

Nodes are stateless. Workflows are JSON. Runs are persisted.

---

## 2. Core types

Every value on the wire is typed. The type system is small and closed.

### 2.1 `DataType` enum

```
text              — plain string
prompt            — a generation prompt (string + optional metadata)
script            — structured script: { title, hook, beats[], narration, cta }
scene             — one visual scene: { index, title, description, visualDescription, durationSeconds, narration }
imageFrame        — one frame for video composition: { id, imageUrl, duration, order }
imageAsset        — generated image: { url, width, height, seed, metadata }
audioPlan         — { segments: [{ sceneId, text, voice, durationSec }] }
audioAsset        — generated audio: { url, duration, format }
subtitleAsset     — subtitles: { segments: [{ id, text, start, end }] }
videoAsset        — generated video: { url, duration, resolution, aspectRatio, seed }
reviewDecision    — human-in-the-loop choice: { approved, reason?, metadata? }
videoUrl          — raw video URL string
wanPrompt         — Wan-formatted multi-shot prompt: { prompt, formula, characterTags[], includeSound }
json              — free-form structured data (escape hatch)
```

**No `*List` types.** Plurality is expressed by the port's `multiple: bool` flag. When `multiple=True`, the payload is `list[T]`; when `False`, it's a single `T`. See §2.3.

### 2.2 `NodeCategory` enum

```
input     — entry points (triggers, static prompts, product analysis)
script    — text / structure generation (writers, splitters, researchers, prompt refiners)
visuals   — image generation / mapping
audio     — audio planning / subtitle formatting
video     — video composition / generation
utility   — gates / checkpoints
output    — terminals (exports, deliveries)
```

UI grouping only. No runtime semantics.

### 2.3 `PortDefinition` / `PortSchema`

```python
@dataclass(frozen=True)
class PortDefinition:
    key: str                                  # stable id, referenced by edges
    label: str                                # UI label
    direction: Literal["input", "output"]
    data_type: DataType
    required: bool = True                     # inputs only
    multiple: bool = False                    # True → payload is list[T]; see §5
    description: str | None = None

@dataclass(frozen=True)
class PortSchema:
    inputs:  list[PortDefinition]
    outputs: list[PortDefinition]
```

**Port plurality rules:**

| Port `multiple` | Meaning at runtime |
|---|---|
| `False` (default) | Port carries exactly one `T`. If multiple producers connect, it's a validation error. |
| `True` | Port carries `list[T]`. Value is a list whether there's 1 upstream producer emitting a list or N producers each emitting one (fan-in is coalesced). |

### 2.4 `VibeImpact` enum

```
Critical  — changes the creative feel (writers, refiners, trend researcher)
Neutral   — mechanical / plumbing (triggers, formatters, composers, gates)
```

Planner-facing signal only. No runtime effect.

---

## 3. Workflow JSON shape

### 3.1 Top-level

```json
{
  "nodes": [ /* Node[] */ ],
  "edges": [ /* Edge[] */ ]
}
```

Persisted on the `workflows` table as a single JSON document, snapshotted into each `ExecutionRun` so a running workflow is immune to the template being edited mid-run.

### 3.2 Node

```json
{
  "id": "scene-splitter",
  "type": "sceneSplitter",
  "config": {
    "maxScenes": 6,
    "edit_pace": "steady"
  },
  "position": { "x": 700, "y": 200 }
}
```

| Field | Type | Meaning |
|---|---|---|
| `id` | string (slug/uuid) | Unique within the workflow. Referenced by edges. |
| `type` | string | Matches a registered node template's `type`. |
| `config` | object | Node-specific configuration; shape defined by the template's `config_rules()`. |
| `position` | `{x, y}` | Canvas coordinates for the editor. Ignored at runtime. |

### 3.3 Edge

```json
{
  "id": "e1",
  "source": "script-writer",
  "sourceHandle": "script",
  "target": "scene-splitter",
  "targetHandle": "script"
}
```

| Field | Type | Meaning |
|---|---|---|
| `id` | string | Unique within the workflow. |
| `source` | node id | Producer. |
| `sourceHandle` | port key | Which output port. |
| `target` | node id | Consumer. |
| `targetHandle` | port key | Which input port. |

### 3.4 Validation rules

1. `source` / `target` must reference existing node `id`s.
2. `sourceHandle` must be a declared output on the source template.
3. `targetHandle` must be a declared input on the target template.
4. `source.dataType` must match `target.dataType` (or one side is `json`).
5. If the target port has `multiple=False`, no more than one edge may connect to it.
6. If the target port has `multiple=True`, any number of edges may connect.
7. Graph must be acyclic.
8. Every required input port must be satisfied — either by an inbound edge or by a static default from config.

### 3.5 Full example

```json
{
  "nodes": [
    { "id": "prompt",  "type": "userPrompt",       "config": {"prompt": "30s TikTok for school uniforms, warm tone"}, "position": {"x": 0, "y": 200} },
    { "id": "script",  "type": "scriptWriter",     "config": {"structure": "three_act", "targetDurationSeconds": 30}, "position": {"x": 300, "y": 200} },
    { "id": "scenes",  "type": "sceneSplitter",    "config": {"maxScenes": 5},                                         "position": {"x": 600, "y": 200} },
    { "id": "refine",  "type": "promptRefiner",    "config": {"imageStyle": "cinematic warm", "aspectRatio": "9:16"},  "position": {"x": 900, "y": 200} },
    { "id": "images",  "type": "imageGenerator",   "config": {"image": {"provider": "fal"}},                           "position": {"x": 1200, "y": 200} },
    { "id": "review",  "type": "reviewCheckpoint", "config": {"approved": false},                                      "position": {"x": 1500, "y": 200} }
  ],
  "edges": [
    { "id": "e1", "source": "prompt", "sourceHandle": "prompt",  "target": "script", "targetHandle": "prompt" },
    { "id": "e2", "source": "script", "sourceHandle": "script",  "target": "scenes", "targetHandle": "script" },
    { "id": "e3", "source": "scenes", "sourceHandle": "scenes",  "target": "refine", "targetHandle": "scene"  },
    { "id": "e4", "source": "refine", "sourceHandle": "prompt",  "target": "images", "targetHandle": "prompt" },
    { "id": "e5", "source": "images", "sourceHandle": "image",   "target": "review", "targetHandle": "data"   }
  ]
}
```

Note `e3`: `scenes.scenes` (output, `multiple=True`) connects to `refine.scene` (input, `multiple=False`). The runtime handles this via auto-iteration — see §5.

---

## 4. Node template contract

Every node type implements this interface. In Python, `abc.ABC` or `Protocol` + `pydantic` models for config.

### 4.1 Metadata (class-level)

```python
type: str              # slug, unique across registry. Used in workflow.json as node.type.
version: str           # semver-ish; runtime may use for migration.
title: str             # human-readable UI label.
category: NodeCategory
description: str       # one-sentence purpose.
```

### 4.2 Schema methods

```python
def ports() -> PortSchema:
    """All input/output ports this node declares."""

def config_rules() -> dict:
    """Validation rules for the 'config' object — pydantic model or JSON Schema."""

def default_config() -> dict:
    """Default config when a new node of this type is created."""

def active_ports(config: dict) -> PortSchema:
    """
    Which ports are active for this config? Default returns ports() unchanged.
    Override only when port shape depends on config (e.g., imageGenerator
    exposing 'image' vs 'images' based on outputMode).
    """
```

### 4.3 Execution

```python
def execute(ctx: NodeExecutionContext) -> dict[str, PortPayload]:
    """
    Run the node. Return a dict keyed by output port key.
    Return empty dict for a no-op. Raise ReviewPendingException to suspend
    the run for a human gate.
    """
```

### 4.4 Planner metadata

```python
def planner_guide() -> NodeGuide:
    """
    The 'guide card' the LLM planner sees. Separate from execution so
    prose can evolve without touching runtime code.
    """
```

### 4.5 `NodeGuide`

```python
@dataclass(frozen=True)
class GuideKnob:
    name: str                  # config key
    type: Literal["string", "int", "bool", "enum", "list"]
    default: Any
    options: list[str] | None  # for enum knobs
    description: str           # what this knob controls
    vibe_linked: bool          # planner may tune this based on vibe mode

@dataclass(frozen=True)
class NodeGuide:
    node_id: str               # == template.type
    purpose: str               # 1-2 sentences
    position: str              # "before X", "after Y", or "unassigned"
    vibe_impact: VibeImpact
    human_gate: bool
    knobs: list[GuideKnob]
    reads_from: list[str]      # typical upstream node ids (hint only)
    writes_to: list[str]       # typical downstream node ids (hint only)
    when_to_include: str       # natural-language rule
    when_to_skip: str          # natural-language rule
```

---

## 5. Fan-out and fan-in — auto-iteration

The single mechanism for plurality at runtime.

**Rule:** when a producer's output port and a consumer's input port disagree on `multiple`, the runtime adapts automatically. No `mapList` helper node required; no graph expansion at plan time.

### 5.1 Matrix

| Producer `multiple` | Consumer `multiple` | Runtime behavior |
|---|---|---|
| False | False | Pass the single `T` through. |
| True | True | Pass the `list[T]` through. |
| False | True | Coalesce multiple producers into `list[T]`. If only one producer, wrap as `[value]`. |
| **True** | **False** | **Auto-iterate:** run the consumer N times, once per item in the producer's list. Collect outputs back into `list[T]` on the consumer's own output port (which becomes implicitly `multiple=True` at runtime for this run). |

### 5.2 Auto-iteration semantics

When row 4 fires (list → scalar), the runtime:

1. Reads the list payload from the producer port.
2. For each item, creates a sub-invocation of the consumer node with `ctx.inputs[port_key] = item` (singular).
3. Executes the sub-invocations — concurrently by default, with concurrency bounded by `max_concurrency` (default 4, configurable per node).
4. Collects each sub-invocation's outputs. The consumer's output ports effectively become `list[T]` for this run.
5. Caching still applies per sub-invocation: retrying one item doesn't re-run the others.
6. Failures are per-item: a single sub-invocation failure by default fails the whole run, but `retry` and `on_error=skip` can be configured at the node level.

### 5.3 Example

`sceneSplitter` outputs `scenes: scene (multiple=True)` — a list of 5 scenes.
`promptRefiner` input `scene: scene (multiple=False)`.

At runtime:
- 5 `promptRefiner` sub-invocations run (concurrently, up to `max_concurrency`).
- Each gets one scene in `ctx.inputs['scene']`.
- Each emits one prompt on its own `prompt: prompt` output.
- Collected output to the next node is `list[prompt]` (implicitly, `multiple=True`).
- Downstream `imageGenerator` with `prompt: prompt (multiple=False)` auto-iterates again — 5 image gens.

The workflow JSON shows one node per role; the DAG expands at runtime.

### 5.4 Why this design

- **Workflow JSON stays readable.** 6 nodes, not 6 × N.
- **Parallelism is free.** Runtime decides, node doesn't know.
- **Caching granularity is per-item.** Iterating on scene 3 doesn't reheat scenes 1, 2, 4, 5.
- **Retry/fail-over granularity is per-item.** One flaky image gen doesn't poison the whole run.
- **Matches every modern DAG framework** (LangGraph, Airflow `.expand()`, Dagster, Temporal dynamic activities).

---

## 6. `NodeExecutionContext`

What every running node can use.

```python
class NodeExecutionContext:
    # identity
    run_id: str
    node_id: str
    workflow_id: str

    # config / inputs
    config: dict                            # this node's config
    inputs: dict[str, PortPayload]          # already resolved; for multiple=True ports, value is a list

    # services
    llm: LlmClient                          # provider-selecting LLM client
    embeddings: EmbeddingClient             # .embed(str) → list[float]
    storage: StorageClient                  # persist artifacts
    memory: MemoryClient                    # per-run scratch
    http: HttpClient                        # for external APIs (uniform retry/failover)

    # human-loop
    human: HumanInteractionClient
        # .propose(proposal) → raises ReviewPendingException
        # .handle_response(response) → called by runner on resume

    # observability
    log: Logger                             # structured, tagged with run_id/node_id
    emit(event: str, payload: dict) -> None # SSE channel: run.{id}

    # cross-run memory
    recall(key: str, ttl_days: int = 7) -> Any | None
    remember(key: str, value: Any, ttl_days: int = 7) -> None
```

**Nodes must not:**
- Hold state across invocations (stateless).
- Reach into other nodes' config or outputs directly — data only flows through ports.
- Commit to the DB directly — use `ctx.storage` / `ctx.remember`.
- Call external HTTP directly — use `ctx.http` for uniform retry, failover, logging.

---

## 7. Execution model

### 7.1 Run states

```
pending → running ─► completed
                   ├► failed         (execute() raised a non-review exception)
                   ├► suspended      (a node raised ReviewPendingException)
                   │    └─► running   (resumed via handle_response)
                   └► cancelled      (user cancelled via UI or tool call)
```

### 7.2 Rules

1. **Topological order.** Nodes run in dependency order. Independent branches run concurrently.
2. **All required inputs present.** A node only runs once every required input port is satisfied.
3. **Stateless nodes, stateful runs.** Each `NodeRunRecord` persists inputs, outputs, config hash, input hash, timings, errors. Reruns are replay.
4. **Caching.** Key: `(node_type, version, config_hash, input_hash)`. Cache hits skip `execute()` entirely. Applied per auto-iterated sub-invocation.
5. **Human gates are resumption points.** `ReviewPendingException` serializes the pending node state to `PendingInteraction` and suspends the run. Runner's `resume(run_id, node_id, response)` re-enters `execute()` with `ctx.inputs['_humanResponse']` populated.
6. **Token-delta streaming.** LLM nodes may call `ctx.emit("node.token.delta", {"text": delta})` to stream partial outputs on the `run.{id}` SSE channel.

### 7.3 Persistence tables (conceptual)

- **`workflows`** — one row per workflow template: id, name, slug, document (JSON), triggerable, catalog_embedding.
- **`execution_runs`** — one row per run: id, workflow_id, trigger, status, document_snapshot, document_hash, node_config_hashes, started_at, completed_at.
- **`node_run_records`** — one row per node-invocation within a run (including each auto-iterated sub-invocation): id, run_id, node_id, iteration_index?, inputs, outputs, status, started_at, ended_at, error.
- **`run_cache_entries`** — keyed by `(node_type, version, config_hash, input_hash)`: outputs, created_at, ttl.
- **`pending_interactions`** — suspended human gates: id, run_id, node_id, channel, proposal_payload, status, response_payload?, responded_at?.
- **`artifacts`** — stored binaries (images, videos, audio, subtitles) referenced by URL in payloads.

### 7.4 Engine responsibilities

The runner:
- Parses and validates workflow JSON per §3.4.
- Builds the DAG, topologically orders it.
- Schedules ready nodes (all required inputs satisfied) on an async executor.
- Handles fan-in/fan-out per §5.
- Manages run state, persists `NodeRunRecord` for each sub-invocation.
- Checks run cache before invoking `execute()`.
- Catches `ReviewPendingException` → writes `PendingInteraction`, transitions run to `suspended`, stops scheduling downstream nodes.
- On `resume(run_id, node_id, response)`, re-enters the suspended node and continues.
- Emits SSE events on `run.{id}` for run-state transitions, node starts/ends, token deltas.
- Commits final status and returns.

---

## 8. Build order for the Python port

Step 1 (the runner — current focus):
1. `DataType`, `NodeCategory`, `VibeImpact` enums.
2. `PortDefinition`, `PortSchema`, `PortPayload` models.
3. `NodeExecutionContext` interface with stub clients for services.
4. `NodeTemplate` ABC + `NodeRegistry`.
5. Workflow JSON parser + validator (§3).
6. DAG runner with auto-iteration (§5) + caching + suspension/resume (§7).
7. Persistence layer (§7.3) — start with Postgres, `pydantic` + SQLAlchemy.
8. SSE channel for `emit()`.
9. MCP server exposing the runner surface (run workflow, get status, cancel, respond to gate, list workflows). Protocol documented separately.

Step 2 (nodes per `docs/nodes/*.md`):
- Start with the smoke path: `userPrompt`, `scriptWriter`, `reviewCheckpoint`.
- Then expand outward following the video-generation flow.

Step 3 (App 2 — AI Assistant, separate process):
- Planner (composes workflows from briefs)
- Controller (drives runs via MCP)
- Conversational surface (Telegram first, others later)
- Not part of this repo's App 1.

---

## 9. Out of scope for this doc

- **Per-node designs** → [`docs/nodes/`](./nodes/README.md)
- **MCP tool surface** — the exact tools App 1 exposes: documented separately.
- **Planner / AI Assistant** (App 2) — separate repo or service.
- **Frontend** — optional; not part of the runner contract.
