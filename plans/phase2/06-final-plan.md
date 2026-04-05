# AI Video Workflow Backend — Final Plan

## Product Thesis

This backend is a **workflow runtime**, not a CRUD layer with queues bolted on. Its primary job is to accept a workflow document from the existing frontend, validate it against known node templates, derive a legal execution plan, execute nodes in dependency order by calling real AI provider APIs, persist every meaningful state transition, and stream live progress back to the frontend canvas.

The most important design constraint is **consistent node design methodology** with the frontend. The backend does not share code with the TypeScript frontend. It replicates the same conceptual model in PHP: nodes declare typed input/output ports, config schemas with runtime validation, and an execution contract. The frontend's `mockExecute()` becomes the backend's `execute()` — same interface shape, real provider calls instead of deterministic stubs.

The second constraint is **staged delivery**. Get one vertical slice running end-to-end (prompt → script → scenes → images) before polishing the remaining 7 node types. Prove the architecture works with real API calls, not just abstractions.

---

## 1. Repository Structure

Monorepo. The existing frontend moves to `frontend/`. Backend lives in `backend/`. Shared project files stay at root.

```
AiModel/
├── backend/
│   ├── app/
│   │   ├── Domain/
│   │   ├── Models/
│   │   ├── Jobs/
│   │   ├── Events/
│   │   ├── Http/
│   │   └── Services/
│   ├── database/
│   │   └── migrations/
│   ├── routes/
│   │   ├── api.php
│   │   └── channels.php
│   ├── config/
│   ├── tests/
│   ├── docker/
│   │   ├── Dockerfile
│   │   └── nginx.conf (if needed)
│   ├── docker-compose.yml
│   ├── composer.json
│   ├── .env.example
│   └── artisan
├── frontend/
│   ├── src/
│   ├── package.json
│   ├── vite.config.ts
│   ├── tailwind.config.js
│   └── tsconfig.json
├── plans/
├── .beads/
├── .claude/
└── AGENTS.md
```

The `docker-compose.yml` in `backend/` defines four services: `app` (PHP-FPM + Nginx), `worker` (queue worker), `postgres`, `redis`.

---

## 2. Domain Layer

### 2.1 Enums

PHP 8.1+ backed enums for all bounded domains:

```php
// app/Domain/DataType.php
enum DataType: string
{
    case Text = 'text';
    case TextList = 'textList';
    case Prompt = 'prompt';
    case PromptList = 'promptList';
    case Script = 'script';
    case Scene = 'scene';
    case SceneList = 'sceneList';
    case ImageFrame = 'imageFrame';
    case ImageFrameList = 'imageFrameList';
    case ImageAsset = 'imageAsset';
    case ImageAssetList = 'imageAssetList';
    case AudioPlan = 'audioPlan';
    case AudioAsset = 'audioAsset';
    case SubtitleAsset = 'subtitleAsset';
    case VideoAsset = 'videoAsset';
    case ReviewDecision = 'reviewDecision';
    case Json = 'json';
}
```

```php
// app/Domain/NodeCategory.php
enum NodeCategory: string
{
    case Input = 'input';
    case Script = 'script';
    case Visuals = 'visuals';
    case Audio = 'audio';
    case Video = 'video';
    case Utility = 'utility';
    case Output = 'output';
}
```

```php
// app/Domain/RunStatus.php
enum RunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case AwaitingReview = 'awaitingReview';
    case Success = 'success';
    case Error = 'error';
    case Cancelled = 'cancelled';
    case Interrupted = 'interrupted';
}
```

```php
// app/Domain/NodeRunStatus.php
enum NodeRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case AwaitingReview = 'awaitingReview';
    case Success = 'success';
    case Error = 'error';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';
}
```

```php
// app/Domain/RunTrigger.php
enum RunTrigger: string
{
    case RunWorkflow = 'runWorkflow';
    case RunNode = 'runNode';
    case RunFromHere = 'runFromHere';
    case RunUpToHere = 'runUpToHere';
}
```

```php
// app/Domain/Capability.php
enum Capability: string
{
    case TextGeneration = 'text_generation';
    case TextToImage = 'text_to_image';
    case TextToSpeech = 'text_to_speech';
    case StructuredTransform = 'structured_transform';
    case MediaComposition = 'media_composition';
}
```

### 2.2 Value Objects

```php
// app/Domain/PortDefinition.php
readonly class PortDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $direction,  // 'input' | 'output'
        public DataType $dataType,
        public bool $required,
        public bool $multiple,
        public ?string $description = null,
    ) {}
}
```

```php
// app/Domain/PortPayload.php
readonly class PortPayload
{
    public function __construct(
        public mixed $value,
        public string $status,      // idle, ready, running, success, error, skipped, cancelled
        public DataType $schemaType,
        public ?string $producedAt = null,
        public ?string $sourceNodeId = null,
        public ?string $sourcePortKey = null,
        public ?string $previewText = null,
        public ?string $previewUrl = null,
        public ?int $sizeBytesEstimate = null,
        public ?string $errorMessage = null,
    ) {}

    public static function success(mixed $value, DataType $schemaType, ?string $previewText = null): self
    {
        return new self(
            value: $value,
            status: 'success',
            schemaType: $schemaType,
            producedAt: now()->toISOString(),
            previewText: $previewText,
        );
    }

    public static function error(DataType $schemaType, string $message): self
    {
        return new self(value: null, status: 'error', schemaType: $schemaType, errorMessage: $message);
    }

    public static function idle(DataType $schemaType, ?string $previewText = null): self
    {
        return new self(value: null, status: 'idle', schemaType: $schemaType, previewText: $previewText);
    }
}
```

```php
// app/Domain/PortSchema.php
readonly class PortSchema
{
    /** @param PortDefinition[] $inputs */
    /** @param PortDefinition[] $outputs */
    public function __construct(
        public array $inputs,
        public array $outputs,
    ) {}
}
```

### 2.3 Node Execution Context

What a node template receives when `execute()` is called:

```php
// app/Domain/Nodes/NodeExecutionContext.php
readonly class NodeExecutionContext
{
    public function __construct(
        public string $nodeId,
        public array $config,
        /** @var array<string, PortPayload> */
        public array $inputs,
        public string $runId,
        private ProviderRouter $providerRouter,
        private ArtifactStoreContract $artifactStore,
    ) {}

    public function input(string $key): ?PortPayload
    {
        return $this->inputs[$key] ?? null;
    }

    public function inputValue(string $key): mixed
    {
        return $this->inputs[$key]?->value;
    }

    public function provider(Capability $capability): ProviderContract
    {
        return $this->providerRouter->resolve($capability, $this->config);
    }

    public function storeArtifact(string $name, string $contents, string $mimeType): Artifact
    {
        return $this->artifactStore->put($this->runId, $this->nodeId, $name, $contents, $mimeType);
    }
}
```

Note: `provider()` passes `$this->config` to the router — this is how **node-level API keys** work. The router reads provider credentials from the node's config, not from global `.env`.

---

## 3. Node Template System

### 3.1 Abstract Base Class

```php
// app/Domain/Nodes/NodeTemplate.php
abstract class NodeTemplate
{
    abstract public string $type { get; }
    abstract public string $version { get; }
    abstract public string $title { get; }
    abstract public NodeCategory $category { get; }
    abstract public string $description { get; }

    abstract public function ports(): PortSchema;
    abstract public function configRules(): array;
    abstract public function defaultConfig(): array;

    /**
     * Execute the node. Every node has this — non-executable nodes
     * return preview output. Executable nodes call AI providers.
     */
    abstract public function execute(NodeExecutionContext $ctx): array;

    /**
     * Which ports are active for a given config?
     * Default: all ports active. Override for config-dependent nodes like imageGenerator.
     */
    public function activePorts(array $config): PortSchema
    {
        return $this->ports();
    }
}
```

One class. No interface hierarchy. No Executable/NonExecutable discrimination.

### 3.2 The 11 Templates

Each template is a concrete class extending `NodeTemplate`:

| File | Type | Category | Capability | Milestone 1 Live? |
|------|------|----------|------------|-------------------|
| `UserPromptTemplate.php` | userPrompt | input | _(none — returns config as output)_ | Yes (no provider needed) |
| `ScriptWriterTemplate.php` | scriptWriter | script | TextGeneration | Yes |
| `SceneSplitterTemplate.php` | sceneSplitter | script | TextGeneration | Yes |
| `PromptRefinerTemplate.php` | promptRefiner | script | TextGeneration | Yes |
| `ImageGeneratorTemplate.php` | imageGenerator | visuals | TextToImage | Yes |
| `ImageAssetMapperTemplate.php` | imageAssetMapper | visuals | StructuredTransform | No (stub) |
| `TtsVoiceoverPlannerTemplate.php` | ttsVoiceoverPlanner | audio | TextToSpeech | No (stub) |
| `SubtitleFormatterTemplate.php` | subtitleFormatter | audio | StructuredTransform | No (stub) |
| `VideoComposerTemplate.php` | videoComposer | video | MediaComposition | No (stub) |
| `ReviewCheckpointTemplate.php` | reviewCheckpoint | utility | _(special — pauses for review)_ | Yes |
| `FinalExportTemplate.php` | finalExport | output | _(packages final output)_ | No (stub) |

### 3.3 Example: ScriptWriterTemplate

```php
class ScriptWriterTemplate extends NodeTemplate
{
    public string $type = 'scriptWriter';
    public string $version = '1.0.0';
    public string $title = 'Script Writer';
    public NodeCategory $category = NodeCategory::Script;
    public string $description = 'Turns a structured prompt into a script with title, hook, beats, narration, and CTA.';

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                new PortDefinition('prompt', 'Prompt', 'input', DataType::Prompt, required: true, multiple: false),
            ],
            outputs: [
                new PortDefinition('script', 'Script', 'output', DataType::Script, required: true, multiple: false),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'style'                  => ['required', 'string', 'min:1', 'max:200'],
            'structure'              => ['required', Rule::in(['three_act', 'problem_solution', 'story_arc', 'listicle'])],
            'includeHook'            => ['required', 'boolean'],
            'includeCTA'             => ['required', 'boolean'],
            'targetDurationSeconds'  => ['required', 'integer', 'min:5', 'max:600'],
            // Provider config (node-level)
            'provider'               => ['required', 'string'],
            'apiKey'                 => ['required', 'string'],
            'model'                  => ['required', 'string'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'style' => 'Clear, conversational narration with concrete examples',
            'structure' => 'three_act',
            'includeHook' => true,
            'includeCTA' => true,
            'targetDurationSeconds' => 90,
            'provider' => 'openai',
            'apiKey' => '',
            'model' => 'gpt-4o',
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $prompt = $ctx->inputValue('prompt');
        $config = $ctx->config;

        $result = $ctx->provider(Capability::TextGeneration)->execute(
            capability: Capability::TextGeneration,
            input: [
                'system' => $this->buildSystemPrompt($config),
                'user' => $this->buildUserPrompt($prompt, $config),
                'response_format' => 'json',
            ],
            config: ['temperature' => 0.7],
        );

        $script = $this->parseScript($result);

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

### 3.4 Example: ImageGeneratorTemplate (config-dependent ports)

```php
class ImageGeneratorTemplate extends NodeTemplate
{
    public string $type = 'imageGenerator';
    // ...

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                new PortDefinition('sceneList', 'Scene List', 'input', DataType::SceneList, required: false, multiple: false),
                new PortDefinition('promptList', 'Prompt List', 'input', DataType::PromptList, required: false, multiple: false),
            ],
            outputs: [
                new PortDefinition('imageFrameList', 'Image Frame List', 'output', DataType::ImageFrameList, required: false, multiple: false),
                new PortDefinition('imageAssetList', 'Image Asset List', 'output', DataType::ImageAssetList, required: false, multiple: false),
            ],
        );
    }

    /**
     * Only one input and one output are active based on config.
     */
    public function activePorts(array $config): PortSchema
    {
        $activeInputKey = $config['inputMode'] === 'scenes' ? 'sceneList' : 'promptList';
        $activeOutputKey = $config['outputMode'] === 'frames' ? 'imageFrameList' : 'imageAssetList';

        $allPorts = $this->ports();
        return new PortSchema(
            inputs: array_filter($allPorts->inputs, fn($p) => $p->key === $activeInputKey),
            outputs: array_filter($allPorts->outputs, fn($p) => $p->key === $activeOutputKey),
        );
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $config = $ctx->config;
        $activeInputKey = $config['inputMode'] === 'scenes' ? 'sceneList' : 'promptList';
        $prompts = $this->extractPrompts($ctx->inputValue($activeInputKey), $config['inputMode']);

        $results = [];
        foreach ($prompts as $i => $prompt) {
            $imageBytes = $ctx->provider(Capability::TextToImage)->execute(
                capability: Capability::TextToImage,
                input: ['prompt' => $prompt['text'], 'resolution' => $config['resolution']],
                config: ['style' => $config['stylePreset']],
            );

            $artifact = $ctx->storeArtifact(
                name: "scene-{$prompt['sceneIndex']}.png",
                contents: $imageBytes,
                mimeType: 'image/png',
            );

            $results[] = [
                'sceneIndex' => $prompt['sceneIndex'],
                'artifactId' => $artifact->id,
                'url' => $artifact->url,
                'resolution' => $config['resolution'],
            ];
        }

        $outputKey = $config['outputMode'] === 'frames' ? 'imageFrameList' : 'imageAssetList';
        $outputType = $config['outputMode'] === 'frames' ? DataType::ImageFrameList : DataType::ImageAssetList;

        return [
            $outputKey => PortPayload::success(
                value: ['items' => $results, 'count' => count($results)],
                schemaType: $outputType,
                previewText: count($results) . " images · {$config['resolution']}",
            ),
        ];
    }
}
```

### 3.5 Registry

```php
// app/Domain/Nodes/NodeTemplateRegistry.php
class NodeTemplateRegistry
{
    /** @var array<string, NodeTemplate> */
    private array $templates = [];

    public function register(NodeTemplate $template): void
    {
        $this->templates[$template->type] = $template;
    }

    public function get(string $type): ?NodeTemplate
    {
        return $this->templates[$type] ?? null;
    }

    public function all(): array
    {
        return $this->templates;
    }

    public function metadata(): array
    {
        return array_map(fn(NodeTemplate $t) => [
            'type' => $t->type,
            'version' => $t->version,
            'title' => $t->title,
            'category' => $t->category->value,
            'description' => $t->description,
            'inputs' => $t->ports()->inputs,
            'outputs' => $t->ports()->outputs,
        ], $this->templates);
    }
}
```

Registered in a Laravel service provider. All 11 templates registered at boot.

---

## 4. Provider System

### 4.1 Provider Contract

```php
// app/Domain/Providers/ProviderContract.php
interface ProviderContract
{
    public function execute(Capability $capability, array $input, array $config): mixed;
}
```

### 4.2 Provider Router

The router reads provider settings from the **node's config** (not `.env`):

```php
// app/Domain/Providers/ProviderRouter.php
class ProviderRouter
{
    public function resolve(Capability $capability, array $nodeConfig): ProviderContract
    {
        $driver = $nodeConfig['provider'] ?? 'stub';

        return match ($driver) {
            'openai' => new OpenAiAdapter($nodeConfig['apiKey'], $nodeConfig['model'] ?? null),
            'anthropic' => new AnthropicAdapter($nodeConfig['apiKey'], $nodeConfig['model'] ?? null),
            'replicate' => new ReplicateAdapter($nodeConfig['apiKey'], $nodeConfig['model'] ?? null),
            'fal' => new FalAdapter($nodeConfig['apiKey'], $nodeConfig['model'] ?? null),
            'stub' => new StubAdapter(),
            default => throw new \InvalidArgumentException("Unknown provider: {$driver}"),
        };
    }
}
```

Each node instance decides its own provider. Two `scriptWriter` nodes in the same workflow can use different AI providers and different API keys.

### 4.3 Adapters

Each adapter translates between the generic `execute()` contract and the vendor-specific HTTP API:

```php
// app/Domain/Providers/Adapters/OpenAiAdapter.php
class OpenAiAdapter implements ProviderContract
{
    public function __construct(
        private string $apiKey,
        private ?string $model = null,
    ) {}

    public function execute(Capability $capability, array $input, array $config): mixed
    {
        return match ($capability) {
            Capability::TextGeneration => $this->textGeneration($input, $config),
            Capability::TextToImage => $this->textToImage($input, $config),
            Capability::TextToSpeech => $this->textToSpeech($input, $config),
            default => throw new \RuntimeException("OpenAI adapter does not support: {$capability->value}"),
        };
    }

    private function textGeneration(array $input, array $config): array
    {
        $response = Http::timeout(120)
            ->withToken($this->apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model ?? 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $input['system']],
                    ['role' => 'user', 'content' => $input['user']],
                ],
                'temperature' => $config['temperature'] ?? 0.7,
                'response_format' => isset($input['response_format'])
                    ? ['type' => 'json_object']
                    : null,
            ]);

        $response->throw();

        return json_decode($response->json('choices.0.message.content'), true);
    }
}
```

`StubAdapter` returns deterministic mock data for non-live nodes, using the same hash-based approach as the frontend mock executor.

---

## 5. Database Schema

### 5.1 workflows

```sql
CREATE TABLE workflows (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT '',
    schema_version INTEGER NOT NULL DEFAULT 1,
    tags JSONB DEFAULT '[]',
    document JSONB NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_workflows_name ON workflows (name);
CREATE INDEX idx_workflows_updated_at ON workflows (updated_at);
CREATE INDEX idx_workflows_tags ON workflows USING GIN (tags);
```

### 5.2 execution_runs

```sql
CREATE TABLE execution_runs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    workflow_id UUID NOT NULL REFERENCES workflows(id) ON DELETE CASCADE,
    mode VARCHAR(20) NOT NULL DEFAULT 'live',
    trigger VARCHAR(20) NOT NULL,
    target_node_id VARCHAR(255),
    planned_node_ids JSONB NOT NULL DEFAULT '[]',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    document_snapshot JSONB NOT NULL,
    document_hash VARCHAR(64) NOT NULL,
    node_config_hashes JSONB DEFAULT '{}',
    started_at TIMESTAMP NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMP,
    termination_reason VARCHAR(30)
);

CREATE INDEX idx_runs_workflow ON execution_runs (workflow_id);
CREATE INDEX idx_runs_status ON execution_runs (status);
CREATE INDEX idx_runs_started ON execution_runs (started_at);
```

### 5.3 node_run_records

```sql
CREATE TABLE node_run_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    run_id UUID NOT NULL REFERENCES execution_runs(id) ON DELETE CASCADE,
    node_id VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    skip_reason VARCHAR(30),
    blocked_by_node_ids JSONB,
    input_payloads JSONB DEFAULT '{}',
    output_payloads JSONB DEFAULT '{}',
    error_message TEXT,
    used_cache BOOLEAN DEFAULT FALSE,
    duration_ms INTEGER,
    started_at TIMESTAMP,
    completed_at TIMESTAMP
);

CREATE INDEX idx_records_run ON node_run_records (run_id);
CREATE UNIQUE INDEX idx_records_run_node ON node_run_records (run_id, node_id);
```

### 5.4 run_cache_entries

```sql
CREATE TABLE run_cache_entries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    cache_key VARCHAR(255) NOT NULL UNIQUE,
    node_type VARCHAR(50) NOT NULL,
    template_version VARCHAR(20) NOT NULL,
    output_payloads JSONB NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    last_accessed_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_cache_node_type ON run_cache_entries (node_type);
CREATE INDEX idx_cache_accessed ON run_cache_entries (last_accessed_at);
```

### 5.5 artifacts

```sql
CREATE TABLE artifacts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    run_id UUID NOT NULL REFERENCES execution_runs(id) ON DELETE CASCADE,
    node_id VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes BIGINT NOT NULL,
    disk VARCHAR(20) NOT NULL DEFAULT 'local',
    path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_artifacts_run ON artifacts (run_id);
```

---

## 6. Execution Engine

### 6.1 Execution Planner

Pure domain code with no framework dependencies:

```php
// app/Domain/Execution/ExecutionPlanner.php
class ExecutionPlanner
{
    public function plan(array $document, RunTrigger $trigger, ?string $targetNodeId = null): ExecutionPlan
    {
        $nodes = $document['nodes'];
        $edges = $document['edges'];
        $incomingIndex = $this->buildIncomingIndex($edges);
        $outgoingIndex = $this->buildOutgoingIndex($edges);

        // 1. Compute candidate scope
        $candidateIds = match ($trigger) {
            RunTrigger::RunWorkflow => $this->allNodeIds($nodes),
            RunTrigger::RunNode => $this->nodeWithUpstream($targetNodeId, $incomingIndex),
            RunTrigger::RunFromHere => $this->nodeWithDownstream($targetNodeId, $outgoingIndex),
            RunTrigger::RunUpToHere => $this->nodeWithUpstream($targetNodeId, $incomingIndex),
        };

        // 2. Prune disabled nodes
        $skippedIds = [];
        foreach ($candidateIds as $id) {
            $node = $this->findNode($nodes, $id);
            if ($node && ($node['disabled'] ?? false)) {
                $skippedIds[] = $id;
            }
        }
        $candidateIds = array_diff($candidateIds, $skippedIds);

        // 3. Topological sort (Kahn's algorithm)
        $orderedIds = $this->topologicalSort($candidateIds, $edges);

        return new ExecutionPlan(
            orderedNodeIds: $orderedIds,
            skippedNodeIds: $skippedIds,
            trigger: $trigger,
            targetNodeId: $targetNodeId,
        );
    }
}
```

Supports all four triggers: `runWorkflow`, `runNode`, `runFromHere`, `runUpToHere`.

### 6.2 Input Resolver

```php
// app/Domain/Execution/InputResolver.php
class InputResolver
{
    /**
     * Resolve inputs for a node. Returns [ok => true, inputs => [...]] or [ok => false, reason => ...].
     */
    public function resolve(
        array $node,
        NodeTemplate $template,
        array $document,
        array $nodeRunRecords,
        RunCache $cache,
    ): array
    {
        $activePorts = $template->activePorts($node['config']);
        $edges = $document['edges'];
        $inputs = [];

        foreach ($activePorts->inputs as $port) {
            $edge = $this->findEdgeForPort($edges, $node['id'], $port->key);

            if (!$edge) {
                if ($port->required) {
                    return ['ok' => false, 'reason' => 'missingRequiredInputs', 'blockedBy' => []];
                }
                continue;
            }

            // Priority 1: Successful upstream output from this run
            $upstreamRecord = $nodeRunRecords[$edge['sourceNodeId']] ?? null;
            if ($upstreamRecord && $upstreamRecord['status'] === 'success') {
                $payload = $upstreamRecord['output_payloads'][$edge['sourcePortKey']] ?? null;
                if ($payload) {
                    $inputs[$port->key] = PortPayload::fromArray($payload);
                    continue;
                }
            }

            // Priority 2: Cache hit
            $cached = $this->tryCache($edge, $document, $cache);
            if ($cached) {
                $inputs[$port->key] = $cached;
                continue;
            }

            // Priority 3: Preview from non-executable upstream
            $preview = $this->tryPreview($edge, $document);
            if ($preview) {
                $inputs[$port->key] = $preview;
                continue;
            }

            if ($port->required) {
                return ['ok' => false, 'reason' => 'upstreamFailed', 'blockedBy' => [$edge['sourceNodeId']]];
            }
        }

        return ['ok' => true, 'inputs' => $inputs];
    }
}
```

### 6.3 Run Executor (The Main Loop)

```php
// app/Domain/Execution/RunExecutor.php
class RunExecutor
{
    public function __construct(
        private ExecutionPlanner $planner,
        private InputResolver $inputResolver,
        private NodeTemplateRegistry $registry,
        private RunCache $cache,
        private ProviderRouter $providerRouter,
        private ArtifactStoreContract $artifactStore,
    ) {}

    public function execute(ExecutionRun $run): void
    {
        $document = $run->document_snapshot;
        $plan = $this->planner->plan($document, RunTrigger::from($run->trigger), $run->target_node_id);

        $run->update([
            'status' => RunStatus::Running->value,
            'planned_node_ids' => $plan->orderedNodeIds,
        ]);
        broadcast(new RunStarted($run));

        $nodeRecords = []; // In-memory index of completed records for this run

        foreach ($plan->orderedNodeIds as $nodeId) {
            // Cooperative cancellation
            $run->refresh();
            if ($run->status === RunStatus::Cancelled->value) {
                $this->cancelRemaining($run, $plan->orderedNodeIds, $nodeId, $nodeRecords);
                return;
            }

            $node = collect($document['nodes'])->firstWhere('id', $nodeId);
            $template = $this->registry->get($node['type']);

            if (!$template) {
                $this->writeRecord($run, $nodeId, NodeRunStatus::Error, errorMessage: "Unknown node type: {$node['type']}");
                continue;
            }

            // Skip disabled
            if ($node['disabled'] ?? false) {
                $record = $this->writeRecord($run, $nodeId, NodeRunStatus::Skipped, skipReason: 'disabled');
                $nodeRecords[$nodeId] = $record;
                continue;
            }

            // Resolve inputs
            $resolution = $this->inputResolver->resolve($node, $template, $document, $nodeRecords, $this->cache);
            if (!$resolution['ok']) {
                $record = $this->writeRecord($run, $nodeId, NodeRunStatus::Skipped,
                    skipReason: $resolution['reason'],
                    blockedBy: $resolution['blockedBy'] ?? [],
                );
                $nodeRecords[$nodeId] = $record;
                continue;
            }

            // Check cache
            $cacheKey = $this->cache->buildKey($node, $template, $document['schemaVersion'], $resolution['inputs']);
            $cached = $this->cache->get($cacheKey);
            if ($cached) {
                $record = $this->writeRecord($run, $nodeId, NodeRunStatus::Success,
                    outputPayloads: $cached,
                    usedCache: true,
                );
                $nodeRecords[$nodeId] = $record;
                continue;
            }

            // Execute
            $this->writeRecord($run, $nodeId, NodeRunStatus::Running);
            $startTime = microtime(true);

            try {
                $ctx = new NodeExecutionContext(
                    nodeId: $nodeId,
                    config: $node['config'],
                    inputs: $resolution['inputs'],
                    runId: $run->id,
                    providerRouter: $this->providerRouter,
                    artifactStore: $this->artifactStore,
                );

                $outputs = $template->execute($ctx);
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);

                $this->cache->put($cacheKey, $outputs);

                $record = $this->writeRecord($run, $nodeId, NodeRunStatus::Success,
                    inputPayloads: $resolution['inputs'],
                    outputPayloads: $outputs,
                    durationMs: $durationMs,
                );
                $nodeRecords[$nodeId] = $record;

            } catch (ReviewPendingException $e) {
                $this->handleReviewCheckpoint($run, $nodeId, $resolution['inputs'], $nodeRecords);

            } catch (\Throwable $e) {
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);
                $record = $this->writeRecord($run, $nodeId, NodeRunStatus::Error,
                    inputPayloads: $resolution['inputs'],
                    errorMessage: $e->getMessage(),
                    durationMs: $durationMs,
                );
                $nodeRecords[$nodeId] = $record;
            }
        }

        $this->deriveTerminalStatus($run);
    }
}
```

### 6.4 Run Cache

```php
// app/Domain/Execution/RunCache.php
class RunCache
{
    public function buildKey(array $node, NodeTemplate $template, int $schemaVersion, array $inputs): string
    {
        $parts = implode(':', [
            $node['type'],
            $template->version,
            $schemaVersion,
            $this->hasher->hashConfig($node['config']),
            $this->hasher->hashInputs($inputs),
        ]);
        return hash('sha256', $parts);
    }

    public function get(string $key): ?array { /* query run_cache_entries */ }
    public function put(string $key, array $outputPayloads): void { /* insert/update */ }
}
```

### 6.5 Payload Hasher

```php
// app/Domain/Execution/PayloadHasher.php
class PayloadHasher
{
    /**
     * Stable hash of config. Keys sorted recursively for determinism.
     */
    public function hashConfig(array $config): string
    {
        return hash('sha256', $this->stableJson($config));
    }

    /**
     * Hash inputs, stripping volatile fields (producedAt, sourceNodeId, sourcePortKey).
     */
    public function hashInputs(array $inputs): string
    {
        $normalized = [];
        foreach ($inputs as $key => $payload) {
            $normalized[$key] = [
                'value' => $payload->value ?? ($payload['value'] ?? null),
                'schemaType' => $payload->schemaType->value ?? ($payload['schemaType'] ?? null),
                'status' => $payload->status ?? ($payload['status'] ?? null),
            ];
        }
        ksort($normalized);
        return hash('sha256', $this->stableJson($normalized));
    }

    private function stableJson(mixed $data): string
    {
        if (is_array($data) && !array_is_list($data)) {
            ksort($data);
            $data = array_map(fn($v) => is_array($v) ? $this->stableJsonRecursive($v) : $v, $data);
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
```

### 6.6 Type Compatibility

```php
// app/Domain/Execution/TypeCompatibility.php
class TypeCompatibility
{
    public function check(DataType $source, DataType $target): CompatibilityResult
    {
        // Exact match
        if ($source === $target) {
            return new CompatibilityResult(compatible: true, coercion: false, severity: 'none');
        }

        // Safe scalar-to-list wrapping
        $wrapRules = [
            'text' => 'textList',
            'prompt' => 'promptList',
            'scene' => 'sceneList',
            'imageFrame' => 'imageFrameList',
            'imageAsset' => 'imageAssetList',
        ];

        if (($wrapRules[$source->value] ?? null) === $target->value) {
            return new CompatibilityResult(compatible: true, coercion: true, severity: 'warning',
                reason: "Auto-wrapped {$source->value} into {$target->value}");
        }

        // List-to-scalar: always incompatible
        $unwrapRules = array_flip($wrapRules);
        if (isset($unwrapRules[$source->value]) && $unwrapRules[$source->value] === $target->value) {
            return new CompatibilityResult(compatible: false, coercion: false, severity: 'error',
                reason: "Cannot unwrap {$source->value} to {$target->value}");
        }

        // Everything else: incompatible
        return new CompatibilityResult(compatible: false, coercion: false, severity: 'error',
            reason: "Incompatible types: {$source->value} → {$target->value}");
    }
}
```

### 6.7 Workflow Validator

```php
// app/Domain/Execution/WorkflowValidator.php
class WorkflowValidator
{
    public function validate(array $document, NodeTemplateRegistry $registry): array
    {
        $issues = [];

        // 1. Cycle detection (topological sort — if it fails, there's a cycle)
        // 2. Unknown node types (not in registry)
        // 3. Config validation per node (template->configRules())
        // 4. Port compatibility per edge (TypeCompatibility->check())
        // 5. Missing required inputs (no incoming edge for required port)
        // 6. Inactive port connections (config-dependent ports)
        // 7. Orphan nodes (warning only)

        return $issues; // Array of ValidationIssue-like arrays
    }
}
```

### 6.8 Review Checkpoint

When the executor encounters a `reviewCheckpoint` node:

1. Write `NodeRunRecord` with status `awaitingReview`
2. Update `ExecutionRun` status to `awaitingReview`
3. Broadcast `NodeStatusChanged` event
4. Poll DB every 2 seconds for the record's status to change
5. Frontend shows review UI; user hits `POST /runs/{id}/review` with `{ nodeId, decision, notes }`
6. Controller updates the `NodeRunRecord` output payloads with the decision
7. Polling loop detects the change, resumes execution
8. Timeout: 1 hour. On timeout or cancellation: auto-reject.

### 6.9 Cancellation

- User hits `POST /runs/{id}/cancel`
- Controller sets `execution_runs.status = 'cancelled'`
- On next loop iteration, executor calls `$run->refresh()`, sees cancelled status
- Marks all remaining pending nodes as `cancelled`
- Broadcasts `RunCompleted` with `terminationReason: 'userCancelled'`

---

## 7. Queue & Job

### 7.1 Single Job

```php
// app/Jobs/RunWorkflowJob.php
class RunWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900; // 15 minutes
    public string $queue = 'workflow-runs';

    public function __construct(public string $runId) {}

    public function handle(RunExecutor $executor): void
    {
        $run = ExecutionRun::findOrFail($this->runId);
        $executor->execute($run);
    }

    public function failed(\Throwable $e): void
    {
        $run = ExecutionRun::find($this->runId);
        if ($run) {
            $run->update([
                'status' => RunStatus::Interrupted->value,
                'completed_at' => now(),
                'termination_reason' => 'jobFailed',
            ]);
            broadcast(new RunCompleted($run));
        }
    }
}
```

### 7.2 Worker

In `docker-compose.yml`:

```yaml
worker:
  build: ./docker
  command: php artisan queue:work redis --queue=workflow-runs --timeout=900 --tries=1 --sleep=3
  depends_on: [postgres, redis]
```

`--tries=1` because workflow runs should not auto-retry. If a run fails, the user decides what to do.

---

## 8. Event Streaming

### 8.1 Events

```php
// app/Events/NodeStatusChanged.php
class NodeStatusChanged implements ShouldBroadcast
{
    public function __construct(
        public string $runId,
        public string $nodeId,
        public string $status,
        public ?array $outputPayloads = null,
        public ?int $durationMs = null,
        public ?string $errorMessage = null,
        public ?string $skipReason = null,
        public bool $usedCache = false,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("run.{$this->runId}");
    }

    public function broadcastAs(): string
    {
        return 'node.status';
    }
}
```

### 8.2 SSE Endpoint

```php
// app/Http/Controllers/RunStreamController.php
class RunStreamController
{
    public function stream(string $runId): StreamedResponse
    {
        return new StreamedResponse(function () use ($runId) {
            $redis = Redis::connection('subscriber');
            $channel = "run.{$runId}";

            // Send initial state (catch up on events already fired)
            $run = ExecutionRun::with('nodeRunRecords')->find($runId);
            if ($run) {
                echo "event: run.catchup\n";
                echo "data: " . json_encode(new RunResource($run)) . "\n\n";
                ob_flush(); flush();
            }

            // Subscribe to live events
            $redis->subscribe([$channel], function ($message) {
                $event = json_decode($message, true);
                echo "event: {$event['event']}\n";
                echo "data: " . json_encode($event['data']) . "\n\n";
                ob_flush(); flush();
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

### 8.3 Event Format

Three event types:

```
event: run.started
data: {"runId":"...","status":"running","plannedNodeIds":[...]}

event: node.status
data: {"runId":"...","nodeId":"...","status":"success","outputPayloads":{...},"durationMs":1250,"usedCache":false}

event: run.completed
data: {"runId":"...","status":"success","terminationReason":"completed","completedAt":"..."}
```

Plus `run.catchup` on SSE connect (sends current state so late-joining clients sync up).

---

## 9. API Surface

```
GET    /api/workflows                     List workflows (paginated, ?search=, ?tags=)
POST   /api/workflows                     Create workflow
GET    /api/workflows/{id}                Get workflow (includes document)
PUT    /api/workflows/{id}                Update workflow document
DELETE /api/workflows/{id}                Delete workflow (cascades runs + artifacts)

POST   /api/workflows/{id}/runs           Trigger run { trigger, targetNodeId? }
GET    /api/runs/{id}                     Get run with node records
GET    /api/runs/{id}/stream              SSE event stream
POST   /api/runs/{id}/cancel              Cancel active run
POST   /api/runs/{id}/review              Submit review { nodeId, decision, notes }

GET    /api/artifacts/{id}                Download artifact file
```

All responses use Laravel API Resources for consistent envelope format.

---

## 10. Artifact Storage

```php
// app/Services/ArtifactStoreContract.php
interface ArtifactStoreContract
{
    public function put(string $runId, string $nodeId, string $name, string $contents, string $mimeType): Artifact;
    public function url(Artifact $artifact): string;
    public function get(Artifact $artifact): string;
    public function delete(Artifact $artifact): void;
    public function deleteForRun(string $runId): void;
}

// app/Services/LocalArtifactStore.php
class LocalArtifactStore implements ArtifactStoreContract
{
    public function put(string $runId, string $nodeId, string $name, string $contents, string $mimeType): Artifact
    {
        $path = "artifacts/{$runId}/{$nodeId}/{$name}";
        Storage::disk('local')->put($path, $contents);

        return Artifact::create([
            'run_id' => $runId,
            'node_id' => $nodeId,
            'name' => $name,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'disk' => 'local',
            'path' => $path,
        ]);
    }

    public function url(Artifact $artifact): string
    {
        return url("/api/artifacts/{$artifact->id}");
    }
}
```

---

## 11. Docker Compose

```yaml
# backend/docker-compose.yml
services:
  app:
    build:
      context: .
      dockerfile: docker/Dockerfile
    ports:
      - "8000:80"
    volumes:
      - .:/var/www/html
      - artifact-storage:/var/www/html/storage/app/artifacts
    depends_on:
      - postgres
      - redis
    environment:
      - DB_CONNECTION=pgsql
      - DB_HOST=postgres
      - DB_DATABASE=aimodel
      - DB_USERNAME=aimodel
      - DB_PASSWORD=secret
      - REDIS_HOST=redis
      - QUEUE_CONNECTION=redis
      - BROADCAST_CONNECTION=redis

  worker:
    build:
      context: .
      dockerfile: docker/Dockerfile
    command: php artisan queue:work redis --queue=workflow-runs --timeout=900 --tries=1 --sleep=3
    volumes:
      - .:/var/www/html
      - artifact-storage:/var/www/html/storage/app/artifacts
    depends_on:
      - postgres
      - redis
    environment:
      - DB_CONNECTION=pgsql
      - DB_HOST=postgres
      - DB_DATABASE=aimodel
      - DB_USERNAME=aimodel
      - DB_PASSWORD=secret
      - REDIS_HOST=redis
      - QUEUE_CONNECTION=redis

  postgres:
    image: postgres:16-alpine
    environment:
      - POSTGRES_DB=aimodel
      - POSTGRES_USER=aimodel
      - POSTGRES_PASSWORD=secret
    volumes:
      - pgdata:/var/lib/postgresql/data
    ports:
      - "5432:5432"

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

volumes:
  pgdata:
  artifact-storage:
```

---

## 12. Testing Strategy

### Unit Tests (deterministic, fast)
- `ExecutionPlannerTest` — all 4 trigger types, disabled node pruning, cycle detection
- `TypeCompatibilityTest` — exact match, scalar-to-list, list-to-scalar, incompatible pairs
- `InputResolverTest` — priority 1/2/3 resolution, missing required input
- `RunCacheTest` — cache hit/miss, key determinism, different configs produce different keys
- `PayloadHasherTest` — stable hashing, input normalization strips volatile fields

### Feature Tests (Laravel HTTP + DB)
- `WorkflowCrudTest` — create, read, update, delete, validation errors
- `RunExecutionTest` — trigger run, verify node records created, verify terminal status
- `RunStreamTest` — SSE endpoint returns events in order
- `ReviewFlowTest` — trigger run with reviewCheckpoint, submit decision, verify resume
- `CancellationTest` — trigger run, cancel mid-execution, verify remaining nodes cancelled

### Provider Tests
- Use `StubAdapter` for all unit/feature tests
- Provider adapter tests use recorded HTTP fixtures (Laravel `Http::fake()`)
- Optional smoke test with real API keys (opt-in, not in CI)

---

## 13. Delivery Steps

### Step 1: Skeleton
- Scaffold Laravel app in `backend/`
- Docker Compose with all 4 services
- Health endpoint
- Verify `docker compose up` + `curl localhost:8000/api/health`

### Step 2: Domain Enums + Value Objects
- All 6 enums
- `PortDefinition`, `PortPayload`, `PortSchema`, `NodeExecutionContext`
- `TypeCompatibility` with tests

### Step 3: Node Templates + Registry
- Abstract `NodeTemplate`
- All 11 concrete templates (with `StubAdapter` execution)
- `NodeTemplateRegistry`
- Config validation rules per template
- Tests for registration and config validation

### Step 4: Database + Models
- All 5 migrations
- Eloquent models with JSONB casts
- `WorkflowController` CRUD
- API resources
- Feature tests for CRUD

### Step 5: Execution Engine
- `ExecutionPlanner` + tests
- `InputResolver` + tests
- `PayloadHasher` + `RunCache` + tests
- `RunExecutor` (main loop)
- `RunWorkflowJob`
- `RunController` trigger endpoint
- Feature tests for full run with stubs

### Step 6: Streaming + Review + Cancel
- Broadcasting events
- SSE endpoint
- Review checkpoint polling + API
- Cancellation flow
- Feature tests

### Step 7: Real Providers
- `ProviderContract` + `ProviderRouter`
- `OpenAiAdapter` (text generation)
- `ReplicateAdapter` or `FalAdapter` (text-to-image)
- Wire `scriptWriter`, `sceneSplitter`, `promptRefiner`, `imageGenerator` to real providers
- `LocalArtifactStore`
- End-to-end test: one workflow producing real images

### Step 8: Polish
- Seed workflow fixture
- API documentation
- Error handling improvements
- Cache retention / garbage collection
- Logging for debugging provider calls
