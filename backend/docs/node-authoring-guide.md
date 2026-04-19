# Node Authoring Guide — Planner-Compatible Nodes

> **Audience:** Engineers adding a new node to the AiModel catalog.
> After reading this guide you should be able to write, register, and test a fully
> planner-compatible node without asking anyone for help.

---

## 1. What is a node?

A node is a single, discrete processing step in an AI video-generation pipeline. Every
node is a PHP class that extends `NodeTemplate` and serves **two audiences simultaneously**:

- **The runtime** executes the node by calling `execute(NodeExecutionContext $ctx)`. The
  runtime cares about typed input/output ports and a validated config array. It calls
  `needsHumanLoop()` first; if that returns `true` it enters the `propose`/`handleResponse`
  cycle instead of calling `execute` directly. The runtime knows nothing about the planner.

- **The planner** selects and configures nodes autonomously. It reads `plannerGuide()`, which
  returns a `NodeGuide` value object describing what the node does, where it sits in the
  pipeline, which creative knobs it exposes, and under which vibe modes it should (or should
  not) be used. The planner never calls `execute()` — it only reads the guide.

Both audiences are first-class. A node whose `execute()` is perfect but whose `plannerGuide()`
is vague will be misused by the planner. A node whose guide is beautifully written but whose
`execute()` is broken will fail at runtime.

---

## 2. The minimum viable node

The following skeleton is a complete, copy-pasteable `ExampleNodeTemplate` that satisfies
every acceptance test. Replace `ExampleNode`/`exampleNode` with your actual names throughout.

### 2.1 The PHP class

```php
<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Domain\Nodes\GuideKnob;
use App\Domain\Nodes\GuidePort;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\VibeImpact;

class ExampleNodeTemplate extends NodeTemplate
{
    // ── Identity ──────────────────────────────────────────────────────────

    /**
     * Unique machine identifier. Must match plannerGuide()->nodeId exactly.
     * Use camelCase, no spaces, no hyphens.
     */
    public string $type { get => 'exampleNode'; }

    /** Semantic version. Bump MINOR when adding ports; MAJOR when removing. */
    public string $version { get => '1.0.0'; }

    /** Human-readable title shown in the node library sidebar. */
    public string $title { get => 'Example Node'; }

    /** Logical grouping in the sidebar. Pick the closest: Input, Script,
     *  Visuals, Audio, Video, Utility, Output. */
    public NodeCategory $category { get => NodeCategory::Utility; }

    /** One sentence shown in tooltips and the manifest. Do not repeat the
     *  category. Explain WHAT it produces, not HOW. */
    public string $description { get => 'Transforms raw text into a formatted summary suitable for downstream script nodes.'; }

    // ── Ports ─────────────────────────────────────────────────────────────

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input(
                    key: 'rawText',
                    label: 'Raw Text',
                    dataType: DataType::Text,
                    required: true,
                ),
                PortDefinition::input(
                    key: 'contextHints',
                    label: 'Context Hints',
                    dataType: DataType::Json,
                    required: false,
                ),
            ],
            outputs: [
                PortDefinition::output(
                    key: 'summary',
                    label: 'Summary',
                    dataType: DataType::Text,
                ),
            ],
        );
    }

    // ── Config ────────────────────────────────────────────────────────────

    /**
     * Laravel validation rules. Keys map 1-to-1 with defaultConfig() keys.
     * Use `required` for mandatory fields and `sometimes` for optional ones.
     * Dot-notation keys (e.g. `style.tone`) produce nested JSON Schema objects.
     */
    public function configRules(): array
    {
        return [
            'maxWords'   => ['required', 'integer', 'min:10', 'max:500'],
            'style'      => ['sometimes', 'string', 'in:bullet,paragraph,numbered'],
            'includeEmoji' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Defaults must pass the rules above. The frontend inspector pre-fills
     * from here, and the planner uses these when it does not override a knob.
     */
    public function defaultConfig(): array
    {
        return [
            'maxWords'     => 100,
            'style'        => 'paragraph',
            'includeEmoji' => false,
        ];
    }

    // ── Execution ─────────────────────────────────────────────────────────

    /**
     * Perform the node's work. Every code path must return an array keyed
     * by output port key. Use PortPayload::success(...) for happy paths and
     * PortPayload::error(...) for recoverable failures.
     *
     * @return array<string, PortPayload>
     */
    public function execute(NodeExecutionContext $ctx): array
    {
        $rawText = $ctx->inputValue('rawText');
        $config  = $ctx->config;

        if (!is_string($rawText) || $rawText === '') {
            return [
                'summary' => PortPayload::error(
                    schemaType: DataType::Text,
                    errorMessage: 'rawText input is empty or not a string',
                    sourceNodeId: $ctx->nodeId,
                    sourcePortKey: 'summary',
                ),
            ];
        }

        // --- real work happens here ---
        $words   = explode(' ', $rawText);
        $maxWords = (int) ($config['maxWords'] ?? 100);
        $trimmed  = implode(' ', array_slice($words, 0, $maxWords));

        return [
            'summary' => PortPayload::success(
                value: $trimmed,
                schemaType: DataType::Text,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'summary',
                previewText: mb_substr($trimmed, 0, 80),
            ),
        ];
    }

    // ── Planner guide ─────────────────────────────────────────────────────

    /**
     * Machine-readable description consumed by the AI planner.
     * This is NOT optional — the planner CANNOT reason about the node without it.
     */
    public function plannerGuide(): NodeGuide
    {
        return new NodeGuide(
            // Must equal $this->type exactly.
            nodeId: $this->type,

            // 1-2 sentences. The planner reads this to decide whether to include
            // the node. Be precise about inputs, outputs, and creative effect.
            purpose: 'Condense raw text into a short, styled summary. Controls output word count and visual style (bullet, paragraph, numbered).',

            // Human-readable pipeline position hint. The planner uses this for
            // ordering reasoning but does NOT parse it as code.
            position: 'after raw text producer, before any script or prompt node',

            // How much this node affects the creative feel of the final video.
            // Critical = planner MUST match vibe. Neutral = vibe-agnostic.
            vibeImpact: VibeImpact::Neutral,

            // true when the node uses InteractsWithHuman or calls a human-gate
            // node in the workflow. Controls planner scheduling assumptions.
            humanGate: false,

            // Each GuideKnob is a config key the planner can set. Include only
            // knobs the planner can meaningfully vary. Omit internal/debug keys.
            knobs: [
                new GuideKnob(
                    name: 'style',
                    type: 'enum',
                    options: ['bullet', 'paragraph', 'numbered'],
                    default: 'paragraph',
                    effect: 'Controls the visual layout of the output summary.',
                    vibeMapping: [
                        'funny_storytelling' => 'bullet',
                        'clean_education'    => 'numbered',
                        'aesthetic_mood'     => 'paragraph',
                        'raw_authentic'      => 'paragraph',
                    ],
                ),
                new GuideKnob(
                    name: 'maxWords',
                    type: 'int',
                    options: null,           // null = free-range, not an enum
                    default: 100,
                    effect: 'Maximum words in the output summary. Lower = tighter copy.',
                    // No vibeMapping — this is a creator-local knob (see §6).
                ),
            ],

            // Node types this node expects upstream. Used for graph topology.
            readsFrom: ['userPrompt', 'scriptWriter'],

            // Node types this node writes to downstream.
            writesTo: ['promptRefiner', 'sceneSplitter'],

            // Mirror of ports() using GuidePort. Must match the actual PortSchema.
            ports: [
                GuidePort::input('rawText', 'text', required: true),
                GuidePort::input('contextHints', 'json', required: false),
                GuidePort::output('summary', 'text'),
            ],

            // One sentence. Concrete vibe modes or brief shapes where this belongs.
            whenToInclude: 'when the workflow needs to condense long text before passing it to a downstream prompt node',

            // One sentence. When the planner should PREFER a different node.
            whenToSkip: 'when the upstream node already produces a short, structured output — adding this node would truncate useful data',
        );
    }
}
```

### 2.2 Registration in `NodeTemplateServiceProvider`

Open `backend/app/Providers/NodeTemplateServiceProvider.php` and add two lines:

```php
// At the top, with the other use statements:
use App\Domain\Nodes\Templates\ExampleNodeTemplate;

// Inside boot(), after the last register() call:
$registry->register(new ExampleNodeTemplate());
```

The registry is a singleton; order of registration determines order in the manifest but not
execution order (that is determined by the DAG).

---

## 3. Port design

Ports are typed connections. The runtime enforces type compatibility when wiring nodes.

### 3.1 `DataType` cases

| Case | Value | Typical use |
|------|-------|-------------|
| `Text` | `text` | Raw strings, user input, script fragments |
| `TextList` | `textList` | Arrays of strings (multiple scenes, captions) |
| `Prompt` | `prompt` | A single LLM prompt string |
| `PromptList` | `promptList` | Multiple prompts for batch generation |
| `Script` | `script` | Structured script object (title, scenes, beats) |
| `Scene` | `scene` | A single scene definition |
| `SceneList` | `sceneList` | Array of scenes |
| `ImageFrame` | `imageFrame` | A single image with metadata |
| `ImageFrameList` | `imageFrameList` | Array of frames |
| `ImageAsset` | `imageAsset` | A stored image artifact reference |
| `ImageAssetList` | `imageAssetList` | Array of stored image references |
| `AudioPlan` | `audioPlan` | Audio timing and voice-over plan |
| `AudioAsset` | `audioAsset` | Stored audio artifact reference |
| `SubtitleAsset` | `subtitleAsset` | Subtitle/caption artifact |
| `VideoAsset` | `videoAsset` | Stored video artifact reference |
| `ReviewDecision` | `reviewDecision` | Output of a human-gate node |
| `VideoUrl` | `videoUrl` | URL pointing to a rendered video |
| `VideoUrlList` | `videoUrlList` | Multiple video URLs |
| `Json` | `json` | Arbitrary structured data (use as a last resort) |

Prefer the most specific type available. Use `Json` only when the schema is genuinely
polymorphic or not yet stabilized. `Json` ports cannot participate in automatic coercion.

### 3.2 `required` vs optional inputs

Set `required: true` (the default for `PortDefinition::input`) when the node cannot produce
any meaningful output without that input. Set `required: false` when the input enriches output
but the node can run without it — for example, `contextHints` in the skeleton above.

The runtime will not call `execute()` if any required input port is unconnected and has no
payload. The frontend inspector highlights missing required connections before the user runs
the pipeline.

### 3.3 `multiple` inputs

Set `multiple: true` when the node should accept multiple wires into the same port (fan-in).
The runtime delivers the payloads as an array. This is uncommon — most nodes take exactly one
value per port. Use `TextList`/`SceneList` types instead of `multiple: true` when the producer
already batches values.

### 3.4 Type compatibility and coercion

The runtime allows upcasting between related types. The canonical coercions are:

| Producer type | Accepted by consumer |
|---------------|----------------------|
| `Text` | `TextList` (wrapped in single-element array) |
| `Prompt` | `Text` (unwrapped) |
| `Scene` | `SceneList` (wrapped) |
| `ImageFrame` | `ImageFrameList` (wrapped) |
| `ImageAsset` | `ImageAssetList` (wrapped) |
| `VideoUrl` | `VideoUrlList` (wrapped) |

There is no coercion involving `Json`. If you need `Json` output from one node to feed a
`Text` input on another, you must add an explicit transformer node in between.

**Checklist for port design:** confirm the `DataType` on your output port matches what the
downstream template expects on its input port (or falls within the coercion table above) before
opening a PR.

---

## 4. Config rules vs JSON Schema

`configRules()` returns Laravel validator rules. `ConfigSchemaTranspiler` converts those rules
into a JSON Schema Draft-07 object that the manifest serves to the frontend inspector.

### 4.1 Mapping table

| Laravel rule | JSON Schema effect |
|---|---|
| `required` | Key added to root `required` array |
| `sometimes` | Key absent from `required` array |
| `string` | `"type": "string"` |
| `integer` | `"type": "integer"` |
| `numeric` | `"type": "number"` |
| `boolean` | `"type": "boolean"` |
| `array` | `"type": "array"` |
| `nullable` | `"type": ["<base>", "null"]` |
| `in:a,b,c` | `"enum": ["a", "b", "c"]` |
| `min:N` on string | `"minLength": N` |
| `max:N` on string | `"maxLength": N` |
| `min:N` on integer/numeric | `"minimum": N` |
| `max:N` on integer/numeric | `"maximum": N` |
| dot-notation key | Nested `"type": "object"` |

### 4.2 Worked example — nested `humanGate` config

When you mix in `InteractsWithHuman`, it calls `humanGateConfigRules()`, which returns:

```php
[
    'humanGate'                  => ['sometimes', 'array'],
    'humanGate.enabled'          => ['sometimes', 'boolean'],
    'humanGate.channel'          => ['sometimes', 'string', 'in:ui,telegram,mcp,any'],
    'humanGate.messageTemplate'  => ['sometimes', 'string'],
    'humanGate.options'          => ['sometimes', 'nullable', 'array'],
    'humanGate.botToken'         => ['sometimes', 'string'],
    'humanGate.chatId'           => ['sometimes', 'string'],
    'humanGate.timeoutSeconds'   => ['sometimes', 'integer', 'min:0', 'max:86400'],
]
```

`ConfigSchemaTranspiler` detects the `humanGate.` prefix, groups all child keys under a single
parent, and emits:

```json
{
  "humanGate": {
    "type": "object",
    "properties": {
      "enabled":         { "type": "boolean",  "default": false },
      "channel":         { "type": "string",   "enum": ["ui","telegram","mcp","any"], "default": "telegram" },
      "messageTemplate": { "type": "string",   "default": "" },
      "options":         { "type": ["array","null"], "default": ["Approve","Revise"] },
      "botToken":        { "type": "string",   "default": "" },
      "chatId":          { "type": "string",   "default": "" },
      "timeoutSeconds":  { "type": "integer",  "minimum": 0, "maximum": 86400, "default": 0 }
    },
    "required": [],
    "additionalProperties": false
  }
}
```

The frontend inspector renders `humanGate` as a **collapsible fieldset** with the child fields
inside. No manual schema work required — you only maintain `configRules()` and `defaultConfig()`.

### 4.3 Rule: every rule key needs a matching default

If `configRules()` declares a key, `defaultConfig()` must contain a value that passes those
rules. The manifest builder will call `transpile($rules, $defaults)` and embed the defaults
directly in the JSON Schema as `"default"` annotations. Missing defaults produce `null` in the
schema, which confuses the frontend inspector.

---

## 5. Planner guide fields — the `NodeGuide` contract

The planner reads `NodeGuide` (serialized as YAML) to understand the catalog. Every field
matters. Below is the full field reference.

### 5.1 `nodeId`

**Must** equal `$this->type` exactly. The planner uses this as the primary key when it refers
to a node in its reasoning. A mismatch causes the planner to think it has a guide for a
different node than the one it will actually instantiate.

```php
nodeId: $this->type,
```

### 5.2 `purpose`

One or two sentences. The planner reads `purpose` during **node selection** — before it decides
whether to include this node at all. It should answer: *what does this node produce, and what
creative constraint does it apply?*

Good: `'Condense raw text into a short, styled summary. Controls word count and visual style (bullet, paragraph, numbered).'`

Bad: `'A utility node that does text processing.'` — too vague to reason on.

### 5.3 `position`

A human-readable hint that helps the planner reason about ordering. Not parsed as code. Write
it as a brief prepositional phrase referencing the types of nodes before and after.

Good: `'after hook selection gate, before casting'`
Bad: `'middle of the pipeline'`

### 5.4 `vibeImpact`

Enum with two cases:

| Case | Value | Meaning |
|------|-------|---------|
| `VibeImpact::Critical` | `critical` | The planner must set vibe-driven knobs on this node. Skipping `vibeMapping` for any vibe-capable knob is an error. |
| `VibeImpact::Neutral` | `neutral` | The node's output does not shift the creative feel. `vibeMapping` is optional. |

Set `Critical` for nodes that write scripts, generate images, compose music, or do anything
that directly shapes the video's tone. Set `Neutral` for utility nodes (formatters, exporters,
routers).

### 5.5 `humanGate`

`true` when the node pauses execution to wait for a human response. Set this to `true` when:
- The template uses `InteractsWithHuman` and the node config may set `humanGate.enabled = true`.
- The node always gates (e.g. a standalone `HumanGateTemplate`).

The planner uses this flag to schedule execution: human-gated nodes are marked as async steps
in the plan, and the planner knows it cannot continue the downstream branch until a response
arrives.

### 5.6 `knobs`

A list of `GuideKnob` objects. Include only the config keys the planner is allowed to vary.
Do not expose internal bookkeeping keys (`_humanFeedback`, `_humanFeedbackHistory`, etc.).

Each knob has:

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | `string` | yes | Matches a key in `configRules()` exactly |
| `type` | `string` | yes | One of: `enum`, `int`, `float`, `bool`, `string` |
| `options` | `list\|null` | yes for `enum` | Valid values; null for free-range types |
| `default` | `scalar` | yes | Must pass the knob's own validation rule |
| `effect` | `string` | yes | One sentence: "Controls X — lower/higher/earlier = Y" |
| `vibeMapping` | `array` | conditional | Required for vibe-driven knobs (see §6) |

### 5.7 `readsFrom` / `writesTo`

Lists of node `type` strings (e.g. `['humanGate', 'intentOutcomeSelector']`). The planner
uses these for **graph topology validation** — it checks that the nodes listed in `readsFrom`
exist upstream and `writesTo` nodes exist downstream in the plan it is building.

You do not need to list every possible upstream/downstream node — list the ones your node
explicitly depends on or produces for.

### 5.8 `ports`

A mirror of `ports()` using `GuidePort` static constructors. Must match the actual
`PortSchema` returned by `ports()`. The planner reads this to know what wire types to look for.

```php
// GuidePort::input($key, $type, $required)
GuidePort::input('rawText', 'text', required: true),
GuidePort::input('contextHints', 'json', required: false),

// GuidePort::output($key, $type) — required is always false for outputs
GuidePort::output('summary', 'text'),
```

The `$type` string is the `DataType` enum value (e.g. `'text'`, `'json'`, `'scene'`).

### 5.9 `whenToInclude`

One sentence describing the vibe modes or brief shapes where the planner should select this
node. The planner parses this as natural language. Be specific — name vibe modes by their
canonical identifier.

Good: `'when vibe_mode is funny_storytelling or raw_authentic'`
Good: `'always — every pipeline needs a final export step'`
Bad: `'sometimes, when you need to summarize text'`

### 5.10 `whenToSkip`

One sentence describing when the planner should prefer a different node. The planner uses this
to avoid over-inclusion. Name the alternative node type when possible.

Good: `'when vibe_mode is clean_education or aesthetic_mood — use beat-planner or mood-sequencer instead'`
Good: `'never — this node is always required'`
Bad: `'when it is not needed'`

---

## 6. Vibe mapping discipline

The four canonical vibes are:

| Identifier | Creative feel |
|------------|---------------|
| `funny_storytelling` | Humor-led narrative; fast pacing; punch-lines |
| `clean_education` | Factual clarity; structured flow; CTA-driven |
| `aesthetic_mood` | Sensory atmosphere; slow reveal; minimal text |
| `raw_authentic` | Unpolished; real-person energy; emotional honesty |

### 6.1 Rules

1. **Every vibe-driven knob MUST map all four vibes.** If a knob affects creative feel
   (e.g. `story_tension_curve`, `humor_density`, `style`) and `vibeImpact` is `Critical`,
   every entry in the dict is required. The planner will error if a vibe key is missing.

2. **The recommended value must exist in `options`.** A `vibeMapping` value that is not in the
   knob's `options` list will cause the planner to set an invalid config value. The conformance
   test catches this.

3. **Creator-local knobs omit `vibeMapping`.** If a knob does not cleanly map across all four
   vibes (e.g. `maxWords`, `targetDurationSeconds`, `story_versions_for_human`), leave
   `vibeMapping` as the default empty array `[]`. The planner will leave that knob at its
   default and let the creator or operator override it manually.

4. **`VibeImpact::Neutral` nodes may skip all vibe mappings.** If the node is neutral (a
   formatter, exporter, router), no knob needs a `vibeMapping`.

### 6.2 Example

```php
new GuideKnob(
    name: 'humor_density',
    type: 'enum',
    options: ['none', 'punchline_only', 'throughout'],
    default: 'throughout',
    effect: 'How much humor is woven into the story.',
    vibeMapping: [
        'funny_storytelling' => 'throughout',  // value IS in options
        'clean_education'    => 'none',         // value IS in options
        'aesthetic_mood'     => 'none',         // value IS in options
        'raw_authentic'      => 'none',         // value IS in options
    ],
),
```

All four vibes are present. All four values (`'throughout'`, `'none'`) exist in `options`.
This knob is valid.

---

## 7. Human-in-the-loop nodes

### 7.1 When to use `InteractsWithHuman`

Mix in `InteractsWithHuman` when the node needs a human to review, pick, or edit its output
before the pipeline continues. Common cases:

- The node generates multiple candidate outputs and the human picks one.
- The output quality is hard to validate automatically (creative copy, story arcs).
- The operator wants a manual gate before committing to an expensive downstream step.

Do **not** mix it in for purely confirmatory gates — use `HumanGateTemplate` for those.

### 7.2 What the trait provides

`InteractsWithHuman` adds to your template:

| Method | Who calls it | What it does |
|--------|-------------|--------------|
| `needsHumanLoop(array $config)` | Runtime | Returns `true` when `config.humanGate.enabled = true` |
| `propose(NodeExecutionContext $ctx)` | Runtime | Calls `execute()`, packages outputs into a `HumanProposal` |
| `handleResponse(NodeExecutionContext $ctx, HumanResponse $response)` | Runtime | Routes `pick`/`edit`/`prompt_back` responses |
| `humanGateConfigRules()` | Your `configRules()` | Returns the `humanGate.*` rule group to merge |
| `humanGateDefaultConfig()` | Your `defaultConfig()` | Returns the `humanGate` defaults object to merge |
| `humanGateFormatMessage(array $outputs, array $config)` | `propose()` internally | Renders the message sent to the human |

### 7.3 The `propose` / `handleResponse` cycle

```
Runtime                       Node (trait)              Human
  │                             │                          │
  │─── propose(ctx) ──────────►│                          │
  │                             │── execute(ctx) ─────────►│ (node runs)
  │                             │◄─ PortPayload[] ─────────│
  │                             │── buildProposal() ───────►│
  │◄── HumanProposal ───────────│                          │
  │── [send to Telegram/UI] ───────────────────────────────►│
  │                             │                          │
  │                             │                    picks / edits / rejects
  │                             │                          │
  │── handleResponse(ctx, r) ──►│                          │
  │   r.action == 'pick'        │── return storedOutputs ──►│
  │   r.action == 'edit'        │── applyEdit → outputs ───►│
  │   r.action == 'prompt_back' │── execute(with feedback)  │
  │                             │── new HumanProposal ──────►│
  │◄── HumanProposal (loop) ────│                          │
```

A `prompt_back` response folds the human's feedback text into `config._humanFeedback` (latest)
and `config._humanFeedbackHistory` (all rounds). Your `execute()` can read these keys to revise
its output, as `StoryWriterTemplate` does in `buildUserPrompt()`.

### 7.4 Config wiring

```php
public function configRules(): array
{
    return array_merge([
        // ... your node's own rules ...
        'targetDurationSeconds' => ['required', 'integer', 'min:15', 'max:120'],
        'style' => ['required', 'string', 'in:bullet,paragraph'],
    ], $this->humanGateConfigRules()); // merge the humanGate.* rule group
}

public function defaultConfig(): array
{
    return array_merge([
        // ... your node's own defaults ...
        'targetDurationSeconds' => 30,
        'style' => 'paragraph',
    ], $this->humanGateDefaultConfig()); // merge the humanGate defaults object
}
```

### 7.5 Customising the proposal message

Override `humanGateFormatMessage()` to produce a richer message. The default renders the
primary output as JSON. `StoryWriterTemplate` overrides it to produce a Telegram-formatted
Markdown summary of the story arc with numbered beats:

```php
protected function humanGateFormatMessage(array $outputs, array $config): string
{
    $primary = $outputs[$this->humanGatePrimaryOutputKey()] ?? null;
    $value   = $primary?->value;

    if (!is_array($value)) {
        return (string) ($primary?->previewText ?? 'Draft ready — please review.');
    }

    $attempt = count($config['_humanFeedbackHistory'] ?? []) + 1;
    $lines = [
        "Draft — round {$attempt}",
        $value['title'] ?? '(untitled)',
        '',
        'Reply 1 to approve, 2 to revise, or send feedback.',
    ];

    return implode("\n", $lines);
}
```

### 7.6 Setting `humanGate: true` in the planner guide

When your template uses `InteractsWithHuman`, set `humanGate: true` in `plannerGuide()` so
the planner knows this node introduces an async human step:

```php
return new NodeGuide(
    nodeId: $this->type,
    // ...
    humanGate: true,
    // ...
);
```

The Telegram Assistant routes incoming messages to the correct pending interaction using the
`runId` and `nodeId` stored in `HumanProposal->state`.

---

## 8. Testing a new node

### 8.1 Required test files

Create one test class per template at:
`backend/tests/Unit/Domain/Nodes/Templates/YourNodeTemplateTest.php`

### 8.2 Guide conformance — use `AllGuidesConformanceTest` as scaffold

The existing `AllGuidesConformanceTest` runs data-provider assertions against every registered
template. Once you register your node in `NodeTemplateServiceProvider`, it is automatically
included. That test checks:

- `guide->nodeId === $template->type`
- `guide->purpose` is non-empty
- `guide->toArray()` contains all required keys
- `guide->toYaml()` contains the node ID
- Each knob has `name`, `type`, `default`, and `effect`
- `vibe_impact` is one of `['critical', 'neutral']`

For additional guide assertions specific to your node, add a dedicated test method:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function planner_guide_covers_all_four_vibes_for_style_knob(): void
{
    $guide = $this->template->plannerGuide();

    $styleKnob = null;
    foreach ($guide->knobs as $k) {
        if ($k->name === 'style') {
            $styleKnob = $k;
            break;
        }
    }

    $this->assertNotNull($styleKnob);
    $vibes = array_keys($styleKnob->vibeMapping);
    $this->assertContains('funny_storytelling', $vibes);
    $this->assertContains('clean_education', $vibes);
    $this->assertContains('aesthetic_mood', $vibes);
    $this->assertContains('raw_authentic', $vibes);

    // Every mapped value must exist in options
    foreach ($styleKnob->vibeMapping as $vibe => $value) {
        $this->assertContains($value, $styleKnob->options,
            "vibeMapping[{$vibe}] = '{$value}' is not in options");
    }
}
```

### 8.3 Port schema assertions

```php
#[\PHPUnit\Framework\Attributes\Test]
public function ports_have_correct_types(): void
{
    $schema = $this->template->ports();

    $inputKeys = array_map(fn ($p) => $p->key, $schema->inputs);
    $this->assertContains('rawText', $inputKeys);

    $rawTextPort = $schema->inputs[array_search('rawText', $inputKeys)];
    $this->assertSame(\App\Domain\DataType::Text, $rawTextPort->dataType);
    $this->assertTrue($rawTextPort->required);

    $outputKeys = array_map(fn ($p) => $p->key, $schema->outputs);
    $this->assertContains('summary', $outputKeys);
}
```

### 8.4 `configRules()` vs `defaultConfig()` coherence

```php
#[\PHPUnit\Framework\Attributes\Test]
public function default_config_passes_its_own_rules(): void
{
    $validator = \Illuminate\Support\Facades\Validator::make(
        $this->template->defaultConfig(),
        $this->template->configRules(),
    );

    $this->assertFalse($validator->fails(),
        'defaultConfig() failed its own configRules(): ' .
        implode(', ', $validator->errors()->all()));
}
```

### 8.5 `execute()` happy path and one error path

```php
#[\PHPUnit\Framework\Attributes\Test]
public function execute_returns_summary_on_valid_input(): void
{
    $ctx = $this->makeContext(
        config: $this->template->defaultConfig(),
        inputs: [
            'rawText' => \App\Domain\PortPayload::success(
                value: 'The quick brown fox jumped over the lazy dog.',
                schemaType: \App\Domain\DataType::Text,
                sourceNodeId: 'upstream',
                sourcePortKey: 'text',
            ),
        ],
    );

    $outputs = $this->template->execute($ctx);

    $this->assertArrayHasKey('summary', $outputs);
    $this->assertTrue($outputs['summary']->isSuccess());
    $this->assertIsString($outputs['summary']->value);
}

#[\PHPUnit\Framework\Attributes\Test]
public function execute_returns_error_on_empty_input(): void
{
    $ctx = $this->makeContext(
        config: $this->template->defaultConfig(),
        inputs: [],
    );

    $outputs = $this->template->execute($ctx);

    $this->assertArrayHasKey('summary', $outputs);
    $this->assertTrue($outputs['summary']->isError());
}
```

### 8.6 Human-loop nodes — additional tests

If `needsHumanLoop(['humanGate' => ['enabled' => true]])` returns `true`, also test:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function needs_human_loop_returns_true_when_enabled(): void
{
    $config = array_merge($this->template->defaultConfig(), [
        'humanGate' => ['enabled' => true],
    ]);

    $this->assertTrue($this->template->needsHumanLoop($config));
    $this->assertFalse($this->template->needsHumanLoop(
        $this->template->defaultConfig()
    ));
}

#[\PHPUnit\Framework\Attributes\Test]
public function propose_returns_human_proposal(): void
{
    $config = array_merge($this->template->defaultConfig(), [
        'humanGate' => ['enabled' => true, 'channel' => 'telegram'],
    ]);
    $ctx = $this->makeContext(config: $config, inputs: [/* valid inputs */]);

    $proposal = $this->template->propose($ctx);

    $this->assertInstanceOf(\App\Domain\Nodes\HumanProposal::class, $proposal);
    $this->assertNotEmpty($proposal->message);
    $this->assertSame('telegram', $proposal->channel);
}

#[\PHPUnit\Framework\Attributes\Test]
public function handle_response_pick_finalises_outputs(): void
{
    // Build a proposal first, then simulate a pick response
    $config = array_merge($this->template->defaultConfig(), [
        'humanGate' => ['enabled' => true],
    ]);
    $ctx       = $this->makeContext(config: $config, inputs: [/* valid inputs */]);
    $proposal  = $this->template->propose($ctx);

    $response  = new \App\Domain\Nodes\HumanResponse(action: 'pick');
    $pickCtx   = $ctx->withConfig(array_merge($config, [
        '_humanProposalState' => $proposal->state,
    ]));

    $result = $this->template->handleResponse($pickCtx, $response);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('summary', $result); // your primary output key
}

#[\PHPUnit\Framework\Attributes\Test]
public function handle_response_prompt_back_returns_new_proposal(): void
{
    $config   = array_merge($this->template->defaultConfig(), [
        'humanGate' => ['enabled' => true],
    ]);
    $ctx      = $this->makeContext(config: $config, inputs: [/* valid inputs */]);
    $proposal = $this->template->propose($ctx);

    $response = new \App\Domain\Nodes\HumanResponse(
        action: 'prompt_back',
        feedback: 'Make it shorter and funnier.',
    );
    $loopCtx  = $ctx->withConfig(array_merge($config, [
        '_humanProposalState' => $proposal->state,
    ]));

    $next = $this->template->handleResponse($loopCtx, $response);

    $this->assertInstanceOf(\App\Domain\Nodes\HumanProposal::class, $next);
}
```

---

## 9. Common anti-patterns

- **Hard-coding `provider`, `apiKey`, or `model` in node config.** These belong to the
  provider layer, not the node config. Use the `ProviderRouter` (via `$ctx->provider(...)`) and
  let the system configuration supply credentials. Hard-coded keys break the provider
  abstraction and make nodes untestable. (Note: `StoryWriterTemplate` currently has these keys
  in its `configRules()` — that is a known legacy issue tracked as a prerequisite for the LG1
  bead. New nodes must not repeat this pattern.)

- **Making every knob `required`.** Most config knobs should be `sometimes` with a sensible
  default. `required` means the runtime will reject a node instance that does not specify the
  value — suitable for a required API endpoint, but not for a creative style preference that
  has a safe default.

- **Skipping `vibeMapping` on a vibe-critical knob.** If `vibeImpact` is `Critical` and a
  knob directly affects the creative output (tension curve, humor density, tone), the planner
  needs the mapping to set it correctly. Without it, the planner leaves the knob at its
  default for all vibes, which defeats the purpose of vibe-controlled pipelines.

- **Vague `whenToInclude` / `whenToSkip`.** Strings like `'sometimes use this node'` or `'when
  appropriate'` give the planner nothing to act on. It will either always include or always
  skip the node based on whichever heuristic it falls back to. Be precise: name the vibe modes,
  the upstream conditions, or the pipeline shape.

- **Writing a `plannerGuide()` that disagrees with `execute()`.** If the guide says `purpose`
  is "summarizes text" but `execute()` actually generates images, the planner will
  incorrectly position the node. Keep purpose and execute in sync. When you refactor
  `execute()`, update `plannerGuide()` in the same commit.

- **Knob `effect` sentences that don't match the `vibeMapping`.** If `effect` says "higher =
  more formal" but `funny_storytelling` maps to the `high` option, the planner gets a
  contradiction. The effect sentence should be consistent with the mapping direction.

- **Declaring `humanGate: false` in `plannerGuide()` when using `InteractsWithHuman`.** The
  planner will not schedule the async wait step, and the pipeline will appear to hang. Always
  set `humanGate: true` when the trait is mixed in, even if the gate is opt-in via config.

- **Registering the node but not returning it from `ports()`.** The manifest builder calls
  `ports()` during `buildAll()`. A node that throws in `ports()` will crash the manifest
  endpoint for every node in the catalog.

---

## 10. Pre-PR checklist

Work through this list before opening a pull request. Every item should be ticked.

- [ ] `$type` is camelCase, unique across all registered templates, and matches `plannerGuide()->nodeId`.
- [ ] `$version` follows SemVer. If you added, removed, or renamed a port since the last
      published version, you bumped MINOR (added) or MAJOR (removed/renamed).
- [ ] All `DataType` cases on output ports match what downstream templates expect on their
      input ports, or fall within the known coercion table (§3.4).
- [ ] Every required input port (`required: true`) has a sensible error path in `execute()`
      for when the input is missing or malformed.
- [ ] `configRules()` uses `required` only for keys that have no safe default. All other keys
      use `sometimes`.
- [ ] `defaultConfig()` passes `configRules()` validation (run the coherence test in §8.4).
- [ ] Every key in `configRules()` has a corresponding entry in `defaultConfig()`.
- [ ] `plannerGuide()->nodeId === $this->type` (enforced by `AllGuidesConformanceTest`).
- [ ] `plannerGuide()->purpose` is one or two specific sentences, not a rephrasing of the
      category name.
- [ ] `plannerGuide()->whenToInclude` and `whenToSkip` name concrete vibe modes or pipeline
      conditions, not vague language.
- [ ] For every `GuideKnob` with `vibeMapping`, all four canonical vibes (`funny_storytelling`,
      `clean_education`, `aesthetic_mood`, `raw_authentic`) are present as keys, and every
      mapped value exists in the knob's `options` list.
- [ ] If `vibeImpact` is `Critical`, every knob that affects creative output has a
      `vibeMapping`; knobs that do not have a `vibeMapping` are documented as creator-local in
      a code comment.
- [ ] If the template uses `InteractsWithHuman`: `humanGate: true` in `plannerGuide()`,
      `humanGateConfigRules()` merged into `configRules()`, `humanGateDefaultConfig()` merged
      into `defaultConfig()`, and tests for `propose()` and `handleResponse()` (pick, edit,
      prompt_back) exist.
- [ ] The node is registered in `NodeTemplateServiceProvider::boot()` and appears in the
      output of `php artisan node:guides`.
- [ ] `AllGuidesConformanceTest` passes with the new node registered (run it via
      `php artisan test --filter=AllGuidesConformanceTest`).

---

## Appendix A — Key files reference

| File | Purpose |
|------|---------|
| `backend/app/Domain/Nodes/NodeTemplate.php` | Abstract base; defines the full method contract |
| `backend/app/Domain/Nodes/NodeGuide.php` | Planner guide value object |
| `backend/app/Domain/Nodes/GuideKnob.php` | Knob descriptor within a guide |
| `backend/app/Domain/Nodes/GuidePort.php` | Port descriptor within a guide |
| `backend/app/Domain/Nodes/VibeImpact.php` | `Critical`/`Neutral` enum |
| `backend/app/Domain/Nodes/NodeExecutionContext.php` | Runtime context (inputs, config, provider) |
| `backend/app/Domain/Nodes/Concerns/InteractsWithHuman.php` | Human-loop trait |
| `backend/app/Domain/Nodes/ConfigSchemaTranspiler.php` | Converts `configRules()` to JSON Schema |
| `backend/app/Domain/Nodes/NodeManifestBuilder.php` | Builds the manifest served to frontend/planner |
| `backend/app/Providers/NodeTemplateServiceProvider.php` | Registration of all templates |
| `backend/app/Domain/DataType.php` | All valid port data types |
| `backend/app/Domain/PortDefinition.php` | Port definition (key, label, type, required, multiple) |
| `backend/app/Domain/PortPayload.php` | Runtime payload (`success`, `error`, `idle`) |
| `backend/app/Domain/NodeCategory.php` | Sidebar category enum |
| `backend/tests/Unit/Domain/Nodes/AllGuidesConformanceTest.php` | Auto-runs against every registered template |
| `backend/app/Domain/Nodes/Templates/StoryWriterTemplate.php` | Canonical reference implementation |
