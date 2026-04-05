# Phase 2 Synthesis — Backend Runner & API

## What This Document Does

Two competing plans exist for the AI Video Workflow Backend:

- **GPT plan** (`02-compete-gpt.md`): Top-down, enterprise-structured. Domain-driven directory layout, two-job architecture (RunWorkflowJob → ExecuteNodeJob), SSE-first streaming, staged 6-phase delivery, extensive interface hierarchy.
- **Opus plan** (`02-compete-opus.md`): Bottom-up, pragmatic. Flat template hierarchy, single-job sequential loop, Laravel Broadcasting, get-one-workflow-running-first delivery, deliberate omissions.

This synthesis takes the strongest decisions from each and resolves the conflicts.

---

## Resolved Decisions

### 1. Job Architecture → Single Job (Opus wins)

**GPT**: Two jobs — `RunWorkflowJob` dispatches `ExecuteNodeJob` per node.
**Opus**: Single `RunWorkflowJob` with sequential loop inside.

**Decision: Single job.**

Rationale: Two-job architecture creates queue coordination overhead (ordering, partial failure across jobs, inter-job state passing) with zero benefit in v1. Sequential execution inside one process is what the frontend's mock executor already does. It makes debugging trivial — one stack trace, one process, one log stream. Parallel execution at dependency barriers is a clean v2 upgrade path when needed.

### 2. Streaming Transport → Laravel Broadcasting with SSE fallback (Both contribute)

**GPT**: Raw SSE controllers, Redis pub/sub manually wired.
**Opus**: Laravel Broadcasting (Reverb or Redis driver), standard event system.

**Decision: Laravel Broadcasting events internally, SSE endpoint as the consumer.**

Use `broadcast(new NodeStatusChanged(...))` everywhere — this is the event production side. On the consumption side, provide an SSE endpoint (`GET /runs/{id}/stream`) that subscribes to the Redis channel and flushes events as SSE frames. This avoids requiring Reverb/WebSocket infrastructure in v1 while keeping the code ready for a WebSocket upgrade later.

The key insight: **separate event production (Broadcasting) from event consumption (SSE/WS)**. Both plans agree events should go through Redis pub/sub — the only question is the last mile to the browser.

### 3. Directory Layout → Domain-organized, but flatter (Hybrid)

**GPT**: Deep domain directories (`app/Domain/Nodes/Data/`, `app/Domain/Nodes/Contracts/`, `app/Domain/Execution/Graph/`, etc.).
**Opus**: Flatter (`app/Domain/Nodes/`, `app/Domain/Execution/`, `app/Domain/Providers/`).

**Decision: GPT's domain organization, Opus's flatness within each domain.**

```
app/
├── Domain/
│   ├── DataType.php                  # Enum (flat, not in a Data/ subdirectory)
│   ├── NodeCategory.php              # Enum
│   ├── RunStatus.php                 # Enum
│   ├── NodeRunStatus.php             # Enum
│   ├── RunTrigger.php                # Enum
│   ├── Capability.php                # Enum
│   ├── PortDefinition.php            # Readonly class
│   ├── PortPayload.php               # Readonly class
│   ├── PortSchema.php                # Value object
│   ├── Nodes/
│   │   ├── NodeTemplate.php          # Abstract base class (one, not an interface hierarchy)
│   │   ├── NodeTemplateRegistry.php
│   │   ├── NodeExecutionContext.php
│   │   ├── ScriptWriterTemplate.php
│   │   ├── ...all 11 templates
│   ├── Execution/
│   │   ├── WorkflowValidator.php
│   │   ├── ExecutionPlanner.php
│   │   ├── InputResolver.php
│   │   ├── RunExecutor.php           # The main loop
│   │   ├── RunCache.php
│   │   ├── PayloadHasher.php
│   │   └── TypeCompatibility.php
│   └── Providers/
│       ├── ProviderContract.php      # Interface
│       ├── ProviderRouter.php
│       └── Adapters/
│           ├── OpenAiAdapter.php
│           ├── ReplicateAdapter.php
│           └── StubAdapter.php
├── Models/                           # Eloquent models (Laravel convention)
├── Jobs/
│   └── RunWorkflowJob.php
├── Events/
│   ├── RunStarted.php
│   ├── NodeStatusChanged.php
│   └── RunCompleted.php
├── Http/
│   ├── Controllers/
│   └── Resources/
└── Services/
    └── ArtifactStore.php             # Interface + LocalArtifactStore
```

Rationale: Domain boundaries matter (GPT is right), but nesting `Data/`, `Contracts/`, `Graph/` subdirectories inside each domain creates navigation friction for a project this size. Keep each domain directory flat — when a directory has 15+ files, split then, not before.

### 4. Template Design → Single Abstract Class (Opus wins, GPT's metadata added)

**GPT**: `NodeTemplate` interface + `ExecutableNodeTemplate` interface + separate contracts.
**Opus**: One abstract `NodeTemplate` class. `execute()` always exists.

**Decision: Single abstract class. Every template has `execute()`.**

Non-executable nodes (like `userPrompt`) just return preview output from `execute()`. No discriminated union needed on the backend — every node can be called the same way. This eliminates branching in the executor loop.

**BUT** — adopt GPT's idea of explicit metadata exposure. The registry should expose `TemplateMetadata` (type, title, category, ports, version) for a future template-sync API endpoint.

### 5. Config Validation → Laravel Validator Rules (Both agree)

Both plans agree: use Laravel's built-in validation, not a Zod clone. Each template returns `configRules(): array` using standard Laravel rule syntax.

### 6. Artifact Storage → Minimal Interface (Opus wins, GPT's metadata model)

**Opus**: 5-method interface, `LocalArtifactStore`, done.
**GPT**: More elaborate with signed URLs, checksums, role metadata.

**Decision: Opus's minimal interface now. GPT's `artifacts` table schema** (which includes mime_type, size_bytes, disk, path) — this metadata is cheap to store and invaluable later.

### 7. Database Schema → Merged (Both contribute)

Both plans have nearly identical schemas. Merged decisions:

- **`workflows`**: GPT's `*tags` GIN index. Opus's simpler column set.
- **`execution_runs`**: Opus's `document_snapshot` JSONB (immutable copy). Both agree on `document_hash` and `node_config_hashes`.
- **`node_run_records`**: Both identical. JSONB for `input_payloads` and `output_payloads`.
- **`run_cache_entries`**: Opus's flat key approach. Unique index on `cache_key`.
- **`artifacts`**: GPT's richer schema (disk, path, mime_type, size_bytes).
- **No `edge_payload_snapshots` table**: GPT includes it, Opus doesn't. Decision: skip it. Edge snapshots are derivable from node run records + the workflow edges. Don't persist what you can compute.
- **No `workflow_snapshots` table**: Both plans downplay it. The `document_snapshot` on `execution_runs` serves crash recovery for runs. Frontend keeps doing client-side autosave.

### 8. Review Checkpoint → DB Polling (Opus wins)

**GPT**: Event-driven resume (vague on implementation).
**Opus**: Job polls DB every 2 seconds until decision appears.

**Decision: DB polling.** Simple, debuggable, works within Laravel's queue model. The job sleeps 2s between checks. Configurable timeout (default 1 hour). If the job times out or run is cancelled, auto-reject.

### 9. Cancellation → Cooperative DB Check (Both agree)

Both plans agree: `$run->refresh()` at the start of each node iteration. If status is `cancelled` (set by `POST /runs/{id}/cancel`), stop the loop and mark remaining nodes cancelled.

### 10. Delivery Phases → Opus's bottom-up order, GPT's task granularity

**Opus**: 7 steps, get-one-thing-working-first philosophy.
**GPT**: 8 tasks with more granular file-level detail.

**Decision: Opus's ordering (skeleton → domain → persistence → execution → streaming → providers → polish) with GPT's file-level specificity in each step.**

### 11. Staged Node Scope → Both agree

First live nodes: `scriptWriter`, `sceneSplitter`, `promptRefiner`, `imageGenerator`.

This forms a complete vertical slice: prompt → script → scenes → refined prompts → generated images. Proves the entire pipeline (planning, execution, provider calls, artifact storage, streaming).

Remaining nodes get `StubAdapter` execution (returns realistic mock data) until their providers are wired.

### 12. What To Leave Out → Opus's list, confirmed

Both plans agree these are not v1:
- Authentication / multi-tenancy
- Parallel node execution
- Workflow document migrations on the backend
- Multi-tab safety (frontend concern)
- Template metadata sync API (nice-to-have, not blocking)

---

## Resolved API Surface

```
# Workflows
GET    /api/workflows                    List (paginated, filterable by tags/name)
POST   /api/workflows                    Create
GET    /api/workflows/{id}               Show (includes full document)
PUT    /api/workflows/{id}               Update document
DELETE /api/workflows/{id}               Delete (cascades runs)

# Runs
POST   /api/workflows/{id}/runs          Trigger { trigger, targetNodeId? }
GET    /api/runs/{id}                    Show run + node records
GET    /api/runs/{id}/stream             SSE event stream
POST   /api/runs/{id}/cancel             Cancel active run

# Review
POST   /api/runs/{id}/review             Submit { nodeId, decision, notes }

# Artifacts
GET    /api/artifacts/{id}               Download file
```

Minimal. Every endpoint has a clear reason to exist.

---

## Resolved Event Stream Format

```
event: run.started
data: {"runId":"...","status":"running","plannedNodeIds":[...]}

event: node.status
data: {"runId":"...","nodeId":"...","status":"running|success|error|skipped|awaitingReview","outputPayloads":{},"durationMs":null,"errorMessage":null,"skipReason":null,"usedCache":false}

event: run.completed
data: {"runId":"...","status":"success|error|cancelled","terminationReason":"...","completedAt":"..."}
```

Three event types, not seven. The `node.status` event carries all node state — the frontend reads `status` to decide what to render. Fewer event types = simpler client code.

---

## Resolved Open Questions

1. **Monorepo with `backend/` + `frontend/` subdirectories.** The existing frontend code moves under `frontend/`. Backend lives under `backend/` with its own `docker/`, `app/`, and `docker-compose.yml`. Shared project files (`.beads/`, `.claude/`, `plans/`, etc.) stay at project root.

```
AiModel/
├── backend/
│   ├── app/
│   ├── docker/
│   ├── docker-compose.yml
│   ├── composer.json
│   └── ...
├── frontend/
│   ├── src/
│   ├── package.json
│   ├── vite.config.ts
│   └── ...
├── plans/
├── .beads/
├── .claude/
└── AGENTS.md
```

2. **API keys live on the node config, not in `.env`.** Each node instance carries its own provider settings (API key, model, endpoint) in its config. This means users can configure different providers per node — e.g., one `scriptWriter` using OpenAI, another using Anthropic. The backend reads provider credentials from the node's config at execution time. No global provider registry in `.env`. This is more flexible and matches the "provider-agnostic, no lock-in" principle.

3. **Run timeout: 15-minute queue timeout + per-node application timeout.** Queue worker runs with `--timeout=900` (15 minutes). Individual provider HTTP calls get a 120-second timeout via Guzzle. If a single node exceeds 120s, it errors. If the whole run exceeds 15 minutes, the job fails and the run is marked `interrupted`. This covers realistic workflows (10 nodes × 30s = 5 min) with headroom.

4. **No payload size limits.** Let `output_payloads` JSONB grow freely. Binary data (images, audio, video) goes to artifact storage — payloads only hold structured data, preview text, and artifact references. Structured JSON rarely gets large enough to matter.

---

## Definition of Done (Milestone 1)

- `docker compose up` starts app, worker, postgres, redis
- Workflow CRUD works via API
- `POST /workflows/{id}/runs` triggers real execution on queue worker
- `scriptWriter → sceneSplitter → promptRefiner → imageGenerator` executes with real AI API calls
- Every node state change broadcasts via SSE
- Generated images stored as artifacts, downloadable via API
- Cache reuse works for repeated runs with same config
- Cancellation stops execution mid-run
- Review checkpoint pauses and resumes execution
- Planner, resolver, cache, and compatibility have unit tests
- One seed workflow fixture demonstrates the full live path
