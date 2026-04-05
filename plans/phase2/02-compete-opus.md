# AI Video Workflow Backend — Opus Competing Plan

## Thesis

The backend's job is simple to state and hard to get right: **take a workflow graph, execute it node by node against real AI APIs, and stream every state change back to the canvas in real time.** Everything else — CRUD, caching, artifact storage — is plumbing in service of that core loop.

The biggest risk is not missing features. It is **premature abstraction**. A capability router, a provider registry, an artifact adapter layer, a cache normalizer — these are all things the codebase will eventually need, but building them all before a single real API call works is how Laravel projects end up with 40 files and zero running workflows. This plan is deliberately bottom-up: get one workflow executing end-to-end first, then extract the abstractions that the code actually asks for.

## Where I Diverge From The Obvious Approach

### 1. One Job, Not Two

The GPT-style plan proposes `RunWorkflowJob` dispatching individual `ExecuteNodeJob` per node. That creates queue coordination overhead: you need to chain jobs, track inter-job state, handle partial failures across queue boundaries, and ensure event ordering when jobs run on different workers.

For v1, a **single `RunWorkflowJob`** that loops through nodes sequentially inside one process is simpler and sufficient. The frontend's mock executor already works this way. Each node execution is a method call within the loop — not a separate queued job. This keeps state local, ordering guaranteed, and debugging trivial.

When we need parallel execution later, we split the loop at dependency barriers — but that's a v2 concern. Premature parallelism is worse than sequential.

### 2. Laravel Broadcasting Over Raw SSE

Raw SSE controllers in Laravel are awkward — you need to hold an HTTP connection open, manually flush, and manage connection lifecycle. Laravel already has a mature broadcasting system (`broadcast(new RunNodeCompleted(...))`  → Redis → client). Using **Laravel Reverb** (the first-party WebSocket server) or even Pusher-compatible broadcasting gives us:

- Standard Laravel event broadcasting with zero custom streaming code
- Automatic channel auth (when we add it later)
- Private channels per run (`run.{runId}`)
- Client-side libraries that handle reconnection

The frontend subscribes to `private-run.{runId}` and receives typed events. This is 10 lines of Laravel config vs. a custom SSE controller.

If Reverb feels heavy, fall back to **Laravel SSE via Symfony's StreamedResponse** — but use `event()` + `broadcast()` internally either way, so the transport is swappable.

### 3. Node Templates As Simple Classes, Not An Interface Hierarchy

PHP doesn't need Java-style interface pyramids. A node template is a class with known methods:

```php
class ScriptWriterTemplate extends NodeTemplate
{
    public string $type = 'scriptWriter';
    public string $version = '1.0.0';
    public string $title = 'Script Writer';
    public NodeCategory $category = NodeCategory::Script;

    public function ports(): PortSchema { ... }
    public function configRules(): array { ... }
    public function defaultConfig(): array { ... }
    public function execute(NodeExecutionContext $ctx): array { ... }
}
```

One abstract class. Concrete templates extend it. The `execute()` method is where the real work happens — it receives a context object with resolved inputs, config, and an artifact store handle, and returns output payloads. No separate `ExecutableNodeTemplate` vs `NonExecutableNodeTemplate` discrimination — if a node has nothing to execute (like `userPrompt`), its `execute()` just returns the preview output. Keep it flat.

### 4. Config Validation Via Laravel's Validator, Not a Zod Clone

Don't build a Zod-equivalent in PHP. Use what Laravel already has:

```php
// In ScriptWriterTemplate
public function configRules(): array
{
    return [
        'style' => ['required', 'string', 'min:1', 'max:200'],
        'structure' => ['required', Rule::in(['three_act', 'problem_solution', 'story_arc', 'listicle'])],
        'includeHook' => ['required', 'boolean'],
        'includeCTA' => ['required', 'boolean'],
        'targetDurationSeconds' => ['required', 'integer', 'min:5', 'max:600'],
    ];
}
```

Same shape guarantees. Zero custom validation framework. The frontend's Zod schema and the backend's Laravel rules validate the same shapes — that's the consistency we need. Not code sharing, methodology sharing.

### 5. Artifact Storage Can Start Dumb

The seed says abstract it. I agree — but the initial abstraction should be **one interface, one implementation, five methods**. Not a storage adapter layer with mime-type resolvers, checksums, and signed URL generators on day one.

```php
interface ArtifactStore
{
    public function put(string $runId, string $nodeId, string $name, string $contents, string $mimeType): Artifact;
    public function url(Artifact $artifact): string;
    public function get(Artifact $artifact): string;
    public function delete(Artifact $artifact): void;
    public function deleteForRun(string $runId): void;
}
```

`LocalArtifactStore` writes to `storage/app/artifacts/{runId}/{nodeId}/{name}`. Done. S3 adapter comes when we need it.

## Architecture

### Request Flow

```
Frontend                    Laravel API                Queue Worker
   │                           │                          │
   ├─── POST /workflows ──────►│                          │
   │◄── 201 workflow ──────────┤                          │
   │                           │                          │
   ├─── POST /workflows/{id}/runs ►│                      │
   │◄── 202 { runId } ────────┤                          │
   │                           ├── dispatch RunWorkflowJob ►│
   │                           │                          │
   ├─── GET /runs/{id}/stream ►│                          │
   │    (SSE or WS channel)    │                          │
   │                           │                          │
   │                           │    ┌─────────────────────┤
   │                           │    │ for each node:      │
   │                           │    │  resolve inputs     │
   │                           │    │  check cache        │
   │                           │    │  call AI provider   │
   │                           │    │  store artifacts    │
   │                           │    │  broadcast event ───┼──► Redis pub/sub
   │◄── SSE: node.running ─────┤◄───┼────────────────────┘
   │◄── SSE: node.success ─────┤    │
   │◄── SSE: run.completed ────┤    │
   │                           │                          │
   ├─── GET /runs/{id} ───────►│                          │
   │◄── run details + records ─┤                          │
```

### Project Layout

```
backend/
├── docker-compose.yml
├── Dockerfile
├── app/
│   ├── Models/
│   │   ├── Workflow.php
│   │   ├── ExecutionRun.php
│   │   ├── NodeRunRecord.php
│   │   ├── RunCacheEntry.php
│   │   └── Artifact.php
│   ├── Domain/
│   │   ├── DataType.php                    # PHP enum matching the 17 frontend types
│   │   ├── NodeCategory.php                # PHP enum: input, script, visuals, audio, video, utility, output
│   │   ├── RunStatus.php                   # PHP enum
│   │   ├── NodeRunStatus.php               # PHP enum
│   │   ├── RunTrigger.php                  # PHP enum
│   │   ├── PortDefinition.php              # Readonly class
│   │   ├── PortPayload.php                 # Readonly class
│   │   ├── PortSchema.php                  # Value object: inputs[] + outputs[]
│   │   ├── NodeExecutionContext.php         # What execute() receives
│   │   ├── Nodes/
│   │   │   ├── NodeTemplate.php            # Abstract base class
│   │   │   ├── NodeTemplateRegistry.php    # Singleton map of type → template
│   │   │   ├── UserPromptTemplate.php
│   │   │   ├── ScriptWriterTemplate.php
│   │   │   ├── SceneSplitterTemplate.php
│   │   │   ├── PromptRefinerTemplate.php
│   │   │   ├── ImageGeneratorTemplate.php
│   │   │   ├── ImageAssetMapperTemplate.php
│   │   │   ├── TtsVoiceoverPlannerTemplate.php
│   │   │   ├── SubtitleFormatterTemplate.php
│   │   │   ├── VideoComposerTemplate.php
│   │   │   ├── ReviewCheckpointTemplate.php
│   │   │   └── FinalExportTemplate.php
│   │   ├── Execution/
│   │   │   ├── WorkflowValidator.php       # Cycle detection, port compat, config validation
│   │   │   ├── ExecutionPlanner.php        # Scope + topological sort
│   │   │   ├── InputResolver.php           # Upstream output → cache → preview fallback
│   │   │   ├── RunExecutor.php             # The main loop (called by job)
│   │   │   ├── RunCache.php                # Cache lookup/store with composite key
│   │   │   └── PayloadHasher.php           # Stable hashing, input normalization
│   │   ├── Compatibility/
│   │   │   └── TypeCompatibility.php       # 17×17 matrix, same rules as frontend
│   │   └── Providers/
│   │       ├── Capability.php              # PHP enum: TextGeneration, TextToImage, TTS, etc.
│   │       ├── ProviderContract.php         # interface: execute(Capability, input, config): output
│   │       ├── ProviderRouter.php           # Routes capability → configured provider
│   │       └── Adapters/
│   │           ├── OpenAiAdapter.php
│   │           ├── ReplicateAdapter.php
│   │           └── StubAdapter.php          # Returns mock data, for testing
│   ├── Jobs/
│   │   └── RunWorkflowJob.php              # Single job, sequential loop
│   ├── Events/
│   │   ├── RunStarted.php
│   │   ├── NodeStatusChanged.php
│   │   └── RunCompleted.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── WorkflowController.php
│   │   │   ├── RunController.php
│   │   │   ├── RunStreamController.php
│   │   │   ├── ReviewController.php
│   │   │   └── ArtifactController.php
│   │   └── Resources/
│   │       ├── WorkflowResource.php
│   │       ├── RunResource.php
│   │       └── NodeRunRecordResource.php
│   └── Services/
│       └── ArtifactStore.php               # Interface + LocalArtifactStore
├── database/
│   └── migrations/
│       ├── create_workflows_table.php
│       ├── create_execution_runs_table.php
│       ├── create_node_run_records_table.php
│       ├── create_run_cache_entries_table.php
│       └── create_artifacts_table.php
├── routes/
│   ├── api.php
│   └── channels.php
├── config/
│   ├── providers.php                       # Capability → provider mapping
│   └── nodes.php                           # Node registration config
└── tests/
    ├── Unit/
    │   ├── ExecutionPlannerTest.php
    │   ├── TypeCompatibilityTest.php
    │   ├── InputResolverTest.php
    │   ├── RunCacheTest.php
    │   └── PayloadHasherTest.php
    └── Feature/
        ├── WorkflowCrudTest.php
        ├── RunExecutionTest.php
        └── RunStreamTest.php
```

## Database Schema

### workflows

| Column | Type | Notes |
|--------|------|-------|
| id | uuid PK | |
| name | varchar(255) | indexed |
| description | text | |
| schema_version | integer | |
| tags | jsonb | GIN indexed |
| document | jsonb | Full WorkflowDocument |
| created_at | timestamp | |
| updated_at | timestamp | indexed |

### execution_runs

| Column | Type | Notes |
|--------|------|-------|
| id | uuid PK | |
| workflow_id | uuid FK | indexed |
| mode | varchar(20) | 'mock' or 'live' |
| trigger | varchar(20) | runWorkflow, runNode, etc. |
| target_node_id | varchar(255) nullable | |
| planned_node_ids | jsonb | Ordered array |
| status | varchar(20) | indexed |
| document_snapshot | jsonb | Immutable copy at run start |
| document_hash | varchar(64) | |
| node_config_hashes | jsonb | |
| started_at | timestamp | indexed |
| completed_at | timestamp nullable | |
| termination_reason | varchar(30) nullable | |

### node_run_records

| Column | Type | Notes |
|--------|------|-------|
| id | uuid PK | |
| run_id | uuid FK | indexed |
| node_id | varchar(255) | composite index with run_id |
| status | varchar(20) | |
| skip_reason | varchar(30) nullable | |
| blocked_by_node_ids | jsonb nullable | |
| input_payloads | jsonb | |
| output_payloads | jsonb | |
| error_message | text nullable | |
| used_cache | boolean default false | |
| duration_ms | integer nullable | |
| started_at | timestamp nullable | |
| completed_at | timestamp nullable | |

### run_cache_entries

| Column | Type | Notes |
|--------|------|-------|
| id | uuid PK | |
| cache_key | varchar(255) | unique index |
| node_type | varchar(50) | indexed |
| template_version | varchar(20) | |
| output_payloads | jsonb | |
| created_at | timestamp | |
| last_accessed_at | timestamp | indexed, for LRU |

### artifacts

| Column | Type | Notes |
|--------|------|-------|
| id | uuid PK | |
| run_id | uuid FK | indexed |
| node_id | varchar(255) | |
| name | varchar(255) | |
| mime_type | varchar(100) | |
| size_bytes | bigint | |
| disk | varchar(20) | 'local', 's3', etc. |
| path | varchar(500) | Disk-relative path |
| created_at | timestamp | |

## The Execution Loop In Detail

This is the heart of the system. Everything else feeds into or reads from this loop.

```php
class RunExecutor
{
    public function execute(ExecutionRun $run): void
    {
        $workflow = $run->documentSnapshot(); // Immutable
        $plan = $this->planner->plan($workflow, $run->trigger, $run->target_node_id);
        $run->markRunning($plan);

        broadcast(new RunStarted($run));

        foreach ($plan->orderedNodeIds as $nodeId) {
            // 1. Cooperative cancellation check
            $run->refresh();
            if ($run->status === RunStatus::Cancelled) {
                $this->cancelRemaining($run, $plan, $nodeId);
                return;
            }

            $node = $workflow->findNode($nodeId);
            $template = $this->registry->get($node->type);

            // 2. Skip disabled
            if ($node->disabled) {
                $this->recordSkipped($run, $nodeId, 'disabled');
                continue;
            }

            // 3. Resolve inputs
            $resolution = $this->inputResolver->resolve($node, $template, $run);
            if (!$resolution->ok) {
                $this->recordSkipped($run, $nodeId, $resolution->reason, $resolution->blockedBy);
                continue;
            }

            // 4. Check cache
            $cacheKey = $this->cache->buildKey($node, $template, $workflow->schemaVersion, $resolution->inputs);
            $cached = $this->cache->get($cacheKey);
            if ($cached) {
                $this->recordSuccess($run, $nodeId, $cached->outputPayloads, usedCache: true);
                continue;
            }

            // 5. Execute
            $this->recordRunning($run, $nodeId);
            $startTime = microtime(true);

            try {
                $context = new NodeExecutionContext(
                    nodeId: $nodeId,
                    config: $node->config,
                    inputs: $resolution->inputs,
                    runId: $run->id,
                    artifactStore: $this->artifactStore,
                );

                $outputs = $template->execute($context);
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);

                $this->cache->put($cacheKey, $outputs);
                $this->recordSuccess($run, $nodeId, $outputs, durationMs: $durationMs);

            } catch (ReviewPendingException $e) {
                $this->recordAwaitingReview($run, $nodeId);
                $this->waitForReview($run, $nodeId); // Polls DB until decision
                // After decision, continue or error based on result

            } catch (\Throwable $e) {
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                $this->recordError($run, $nodeId, $e->getMessage(), $durationMs);
            }
        }

        $this->deriveTerminalStatus($run);
        broadcast(new RunCompleted($run));
    }
}
```

Every `recordRunning`, `recordSuccess`, `recordSkipped`, and `recordError` call both persists the state AND broadcasts an event. One place, one responsibility.

## Provider System

Keep it minimal. A provider is something that can fulfill a capability:

```php
enum Capability: string
{
    case TextGeneration = 'text_generation';
    case TextToImage = 'text_to_image';
    case TextToSpeech = 'text_to_speech';
    case StructuredTransform = 'structured_transform';  // JSON-in, JSON-out via LLM
    case MediaComposition = 'media_composition';
}

interface ProviderContract
{
    public function capabilities(): array;  // [Capability::TextGeneration, ...]
    public function execute(Capability $capability, array $input, array $config): array;
}
```

`ProviderRouter` reads `config/providers.php` to know which adapter handles which capability:

```php
// config/providers.php
return [
    'text_generation' => ['driver' => 'openai', 'model' => 'gpt-4o'],
    'text_to_image' => ['driver' => 'replicate', 'model' => 'flux-1.1-pro'],
    'text_to_speech' => ['driver' => 'openai', 'model' => 'tts-1'],
];
```

Swapping a provider = changing one config line. No node code changes.

## Node Template → Provider Mapping

The node template knows which **capability** it needs but not which **provider** fulfills it:

```php
class ScriptWriterTemplate extends NodeTemplate
{
    public function execute(NodeExecutionContext $ctx): array
    {
        $prompt = $ctx->input('prompt');

        $result = $ctx->provider(Capability::TextGeneration)->execute(
            Capability::TextGeneration,
            input: [
                'system' => 'You are a video script writer...',
                'user' => $this->buildPrompt($prompt, $ctx->config),
                'response_format' => 'json',
            ],
            config: ['temperature' => 0.7],
        );

        $script = $this->parseScriptFromResponse($result);

        return [
            'script' => PortPayload::success(
                value: $script,
                schemaType: DataType::Script,
                previewText: $script['title'] . ' · ' . count($script['beats']) . ' beats',
            ),
        ];
    }
}
```

The `$ctx->provider(Capability)` call goes through the router. The template never sees OpenAI, Anthropic, or Replicate directly.

## Review Checkpoint Flow

The review checkpoint is a special case in the execution loop. When the executor hits a `reviewCheckpoint` node:

1. Node status → `awaitingReview`, run status → `awaitingReview`
2. Broadcast `NodeAwaitingReview` event (frontend shows review UI)
3. The job **polls** the `node_run_records` table for a decision (set via REST API)
4. Frontend user hits `POST /runs/{run}/review-decisions` with `{ nodeId, decision, notes }`
5. Controller writes the decision to the node run record
6. The polling loop picks it up, resumes execution

Polling interval: 2 seconds. Timeout: configurable, default 1 hour. If the job times out or is cancelled, the review is auto-rejected.

This is simpler than holding a WebSocket connection or using complex event-driven resume. The queue job just sleeps and checks. For v1, this is fine.

## API Endpoints

```
# Workflows
GET    /api/workflows                    List all workflows
POST   /api/workflows                    Create workflow
GET    /api/workflows/{id}               Get workflow with document
PUT    /api/workflows/{id}               Update workflow document
DELETE /api/workflows/{id}               Delete workflow + all runs

# Runs
POST   /api/workflows/{id}/runs          Trigger a run (body: { trigger, targetNodeId? })
GET    /api/runs/{id}                    Get run with all node records
GET    /api/runs/{id}/stream             SSE stream of run events
POST   /api/runs/{id}/cancel             Cancel active run
GET    /api/runs/{id}/nodes/{nodeId}     Get specific node run record

# Review
POST   /api/runs/{id}/review-decisions   Submit review decision

# Artifacts
GET    /api/artifacts/{id}               Download artifact file
GET    /api/artifacts/{id}/meta          Artifact metadata

# Registry (optional, for frontend sync)
GET    /api/node-templates               List all registered templates with ports/config
```

## Event Stream Format

SSE events on `GET /runs/{id}/stream`:

```
event: run.started
data: {"runId":"...","status":"running","plannedNodeIds":["n1","n2","n3"]}

event: node.running
data: {"runId":"...","nodeId":"n1","status":"running"}

event: node.success
data: {"runId":"...","nodeId":"n1","status":"success","outputPayloads":{...},"durationMs":1250,"usedCache":false}

event: node.skipped
data: {"runId":"...","nodeId":"n2","status":"skipped","skipReason":"upstreamFailed"}

event: node.error
data: {"runId":"...","nodeId":"n3","status":"error","errorMessage":"Provider timeout","durationMs":30000}

event: node.awaitingReview
data: {"runId":"...","nodeId":"n4","status":"awaitingReview"}

event: run.completed
data: {"runId":"...","status":"error","terminationReason":"nodeError","completedAt":"..."}
```

Each event carries enough data for the frontend to update its run store without an additional API call.

## Delivery Plan

### Step 1: Skeleton (Day 1)

- Fresh Laravel app in `backend/` subdirectory
- `docker-compose.yml`: app, worker, postgres, redis
- `.env.example` with DB, Redis, provider API key placeholders
- Health endpoint returning `{ "status": "ok" }`
- Verify: `docker compose up`, `curl localhost:8000/api/health`

### Step 2: Domain Layer (Day 2-3)

- PHP enums: `DataType`, `NodeCategory`, `RunStatus`, `NodeRunStatus`, `RunTrigger`, `Capability`
- Readonly classes: `PortDefinition`, `PortPayload`, `PortSchema`, `NodeExecutionContext`
- Abstract `NodeTemplate` class
- All 11 concrete templates (execute returns stub/mock data initially)
- `NodeTemplateRegistry` singleton
- `TypeCompatibility` service (17x17 matrix)
- Config validation rules per template
- **Tests**: template registration, config validation, type compatibility

### Step 3: Persistence (Day 3-4)

- Migrations for all 5 tables
- Eloquent models with JSONB casts
- `WorkflowController` with full CRUD
- API resources for serialization
- **Tests**: CRUD happy path, validation rejection, document round-trip

### Step 4: Execution Engine (Day 4-6)

- `ExecutionPlanner`: scope extraction + Kahn's topological sort
- `InputResolver`: upstream output → cache → preview fallback
- `RunCache`: composite key build, lookup, store, LRU eviction
- `PayloadHasher`: stable JSON hashing with sorted keys, input normalization
- `RunExecutor`: the main loop (uses StubAdapter initially)
- `RunWorkflowJob`: thin wrapper dispatching to RunExecutor
- `RunController`: trigger endpoint, run detail endpoint
- **Tests**: planner for all 4 trigger types, input resolution priority, cache hit/miss, full run with stubs

### Step 5: Streaming + Review (Day 6-7)

- Laravel events: `RunStarted`, `NodeStatusChanged`, `RunCompleted`
- Broadcasting config (Redis driver)
- `RunStreamController`: SSE endpoint consuming broadcast events
- `ReviewController`: submit decision endpoint
- Review polling loop in `RunExecutor`
- Cancellation: `POST /runs/{id}/cancel` sets status, executor checks on next iteration
- **Tests**: event emission, review flow, cancellation mid-run

### Step 6: Real Providers (Day 7-9)

- `ProviderContract` interface
- `ProviderRouter` reading from `config/providers.php`
- `OpenAiAdapter` for text generation (scriptWriter, sceneSplitter, promptRefiner)
- `ReplicateAdapter` or `FalAdapter` for text-to-image (imageGenerator)
- Wire real execution into templates
- `ArtifactStore` interface + `LocalArtifactStore`
- Store generated images as artifacts, reference in output payloads
- **Tests**: provider adapters with recorded fixtures, end-to-end run producing real artifacts

### Step 7: Polish + Docs (Day 9-10)

- Seed workflow fixture for demo
- API documentation (endpoint reference + event stream format)
- Architecture doc for frontend integration phase
- Retention/GC for old runs and cache entries
- Error handling and logging improvements

## What I Deliberately Leave Out

- **Parallel node execution**: Sequential is correct for v1. Add parallelism when we have dependency barrier proof.
- **Authentication**: Single-user. Add Laravel Sanctum when multi-user matters.
- **Workflow snapshots/autosave**: The frontend can keep doing client-side autosave. Backend persistence is the save-of-record.
- **Multi-tab safety**: Not the backend's problem. Frontend handles this.
- **Schema migrations for workflow documents**: Backend validates on ingest. If schema version is wrong, reject with clear error. Migration is a frontend concern until we own the document format.
- **Template metadata API**: Nice to have, not blocking. Frontend already has its own registry.

## Risks

1. **Provider latency blowing queue timeouts**: Set queue timeout high (900s). Image generation can take 30-60s per node. A 5-node workflow could take 5 minutes.
2. **JSONB payload bloat**: Output payloads with large preview text or base64 thumbnails will balloon `node_run_records`. Keep payloads lean — reference artifacts by ID, don't inline binary data.
3. **Contract drift**: The 17 data types, port keys, and config shapes must match frontend exactly. Publish a shared contract doc and validate both sides against it.
4. **Review checkpoint job timeout**: A job polling for review could sit for hours. Use a long-running queue with `--timeout=0` or re-dispatch the remainder as a continuation job.

## Definition of Done

Milestone 1 is complete when:
- `docker compose up` starts the full stack (app, worker, postgres, redis)
- Workflows can be created, listed, updated, deleted via API
- `POST /workflows/{id}/runs` triggers real execution
- At least `scriptWriter → sceneSplitter → promptRefiner → imageGenerator` executes with real AI API calls
- Every node state transition broadcasts an SSE event
- Generated images are stored as artifacts and downloadable
- Cache reuse works (re-running same workflow with same config skips execution)
- Cancellation stops execution and marks remaining nodes cancelled
- All planner/resolver/cache logic has unit test coverage
