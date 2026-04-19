# Workflow Plan Schema & Compatibility Validator

> Bead: AiModel-645.3 — defines the contract between the workflow-designer planner agent (AiModel-645.4) and the existing WorkflowDocument runtime.

## 1. Why a separate "plan" shape?

The workflow-designer planner emits an **AI-proposed graph** that must be critiqued before it becomes runnable. We introduce `WorkflowPlan` as a distinct value object so we can:

- Carry `reason` on every node and every edge (drift-eval 645.5 and humans need these to critique creative judgement, not just structural correctness).
- Keep planner-only fields (`intent`, `vibeMode`, `assumptions`, `rationale`, `meta`) out of the runtime document schema.
- Validate with a fail-loud `WorkflowPlanValidation` result instead of pushing half-broken graphs into persistence.
- Convert to a runnable `WorkflowDocument` only after the plan is green.

```
user brief
    │
    ▼
workflow-designer (planner LLM)  ──► WorkflowPlan
    │
    ▼
WorkflowPlanValidator            ──► WorkflowPlanValidation  (valid? errors[] warnings[])
    │ (only if valid)
    ▼
WorkflowPlanToDocumentConverter  ──► WorkflowDocument array
    │
    ▼
RunExecutor (existing)
```

## 2. Schema

All types live under `App\Domain\Planner` and are readonly.

### `WorkflowPlan`

| Field         | Type                         | Purpose                                                                                    |
|---------------|------------------------------|--------------------------------------------------------------------------------------------|
| `intent`      | `string`                     | The user brief, verbatim.                                                                  |
| `vibeMode`    | `string`                     | `funny_storytelling` \| `clean_education` \| `aesthetic_mood` \| `raw_authentic` \| ...    |
| `nodes`       | `list<PlanNode>`             | Proposed nodes.                                                                            |
| `edges`       | `list<PlanEdge>`             | Proposed edges (directed).                                                                 |
| `assumptions` | `list<string>`               | Assumptions the planner made (e.g. `"platform=tiktok"`, `"duration<=30s"`).                |
| `rationale`   | `string`                     | Free-text explanation of the plan.                                                         |
| `meta`        | `array<string, mixed>`       | Open bag: `modelUsed`, `plannerVersion`, `generatedAt`, token counts, etc.                 |

### `PlanNode`

| Field    | Type                    | Purpose                                                           |
|----------|-------------------------|-------------------------------------------------------------------|
| `id`     | `string`                | Unique within the plan.                                           |
| `type`   | `string`                | Must match a `NodeTemplate::type` registered in `NodeTemplateRegistry`. |
| `config` | `array<string, mixed>`  | Validated against the template's `configRules()`.                 |
| `reason` | `string`                | Why this node is here. Drives drift-eval scoring.                 |
| `label`  | `?string`               | Optional display label; falls back to template title.             |

### `PlanEdge`

| Field           | Type     | Purpose                                                               |
|-----------------|----------|-----------------------------------------------------------------------|
| `sourceNodeId`  | `string` | Producer `PlanNode.id`.                                               |
| `sourcePortKey` | `string` | Output port key on producer.                                          |
| `targetNodeId`  | `string` | Consumer `PlanNode.id`.                                               |
| `targetPortKey` | `string` | Input port key on consumer.                                           |
| `reason`        | `string` | Why this wire exists — e.g. `"script feeds scene splitter"`.          |

Field names mirror the frontend `WorkflowEdge` shape so conversion is a direct copy.

### `WorkflowPlanValidation`

```php
readonly class WorkflowPlanValidation {
    public bool  $valid;
    public array $errors;   // list<{path, code, message, context?}>
    public array $warnings; // same shape
}
```

### Error object shape (stable contract)

```json
{
  "path": "nodes[2].config.humanGate.channel",
  "code": "config_invalid",
  "message": "The channel field must be one of: ui, telegram, mcp, any.",
  "context": {"nodeId": "n2", "nodeType": "humanGate", "field": "channel"}
}
```

**Path addressing:** dot-notation with bracketed numeric indices. Tokens: `nodes[i]`, `edges[i]`, `.config.<field>` (dotted per Laravel's own error keys), `.inputs.<portKey>`, `.sourceNodeId`, `.sourcePortKey`, `.targetNodeId`, `.targetPortKey`, `.type`, `.id`. This is compatible with JSONPath-style UI addressing and is straight from the plan-document shape — no translation needed.

## 3. Validation Rules (English)

The validator runs checks in this order and surfaces every issue (short-circuits only on empty plan and when all edges reference valid nodes is false — in which case cycle detection is skipped to avoid follow-on noise).

| # | Code                         | Severity | Check                                                                                                      |
|---|------------------------------|----------|------------------------------------------------------------------------------------------------------------|
| 1 | `empty_plan`                 | error    | `nodes` must be non-empty.                                                                                 |
| 2 | `duplicate_node_id`          | error    | Every `PlanNode.id` appears at most once.                                                                  |
| 3 | `unknown_node_type`          | error    | Every `PlanNode.type` is registered in `NodeTemplateRegistry`.                                             |
| 4 | `missing_reason`             | warning  | Every node and edge has a non-empty `reason`.                                                              |
| 5 | `config_invalid`             | error    | Each node's `config` passes its template's `configRules()` via Laravel `Validator` (message = Laravel's, actionable by design). |
| 6 | `duplicate_edge`             | error    | No two edges share `(src, srcPort, tgt, tgtPort)`.                                                         |
| 7 | `edge_self_loop`             | error    | An edge cannot connect a node to itself.                                                                   |
| 8 | `edge_unknown_node`          | error    | Both edge endpoints reference existing nodes.                                                              |
| 9 | `edge_unknown_port`          | error    | Port key is defined on the template's static port schema.                                                  |
| 10| `edge_inactive_port`         | error    | Port is active for the current node config (see `activePorts($config)`).                                   |
| 11| `type_incompatible`          | error    | `TypeCompatibility::check(sourceType, targetType)` returns an error.                                       |
| 12| `type_coercion`              | warning  | Compatibility returned a scalar→list coercion (`Text → TextList`, etc.).                                   |
| 13| `cycle_detected`             | error    | Kahn's-algorithm topological sort visits every node.                                                       |
| 14| `required_input_missing`     | error    | Every required input on every node has an incoming edge OR a config-supplied default (`config.inputs.<key>` or `config.<key>`). |
| 15| `orphan_node`                | warning  | When plan has >1 node, every node participates in at least one edge.                                       |

## 4. Library Choice: Hand-rolled via Laravel Validator

`opis/json-schema` is **not** in `composer.json`. Adding it just for per-node config would be a new runtime dependency. We already validate node configs at runtime through `Illuminate\Support\Facades\Validator` (see `WorkflowValidator` + `ConfigValidator`). Reusing the same mechanism gives us:

- Identical error messages across the planner and the manual canvas editor (no divergence between how "I typed a bad config" and "the AI typed a bad config" surface to the user).
- Zero new deps.
- Full support for the 5 rule types used in templates (`required`, `string`/`integer`/`boolean`, `in:`, `min:`/`max:`) plus anything templates add in the future.

`ConfigSchemaTranspiler` is still kept reachable through `WorkflowPlanValidator::configSchemaFor(type)` — useful for UI form generation or exposing JSON Schema to external tooling, but not wired into the hot validation path.

## 5. Cycle Detection

Kahn's algorithm (topological sort by in-degree). Matches the existing `WorkflowValidator::detectCycles()` so behaviour is consistent across both validators. When the algorithm cannot visit every node, the remaining nodes (still with positive in-degree) are reported in the error's `context.nodeIds` — that's the set participating in at least one cycle. We deliberately do not enumerate individual cycles; agents usually only need "which nodes are tangled".

Self-loops are caught separately (with code `edge_self_loop`) and excluded from the cycle pass so the message is precise.

## 6. Worked Examples

### Happy plan (passes)

```json
{
  "intent": "Funny genz storytelling for TikTok Vietnam",
  "vibeMode": "funny_storytelling",
  "nodes": [
    {"id": "src",    "type": "userPrompt",   "config": {"prompt": "Product demo"}, "reason": "seed"},
    {"id": "writer", "type": "scriptWriter",  "config": {
        "style": "fast", "structure": "three_act",
        "includeHook": true, "includeCTA": true,
        "targetDurationSeconds": 30, "provider": "stub"
      }, "reason": "need narrative driver"}
  ],
  "edges": [
    {"sourceNodeId": "src", "sourcePortKey": "prompt",
     "targetNodeId": "writer", "targetPortKey": "prompt",
     "reason": "wire user brief into writer"}
  ],
  "assumptions": ["platform=tiktok", "duration<=30s"],
  "rationale": "Minimal two-node prototype",
  "meta": {"plannerVersion": "v0.1.0"}
}
```

Validation: `valid=true, errors=[], warnings=[]` (both nodes/edges carry reasons; both nodes connect → no orphan warning).

### Broken plan (fails with actionable errors)

```json
{
  "nodes": [
    {"id": "n1", "type": "scriptWriter",   "config": {"structure": "bogus"}, "reason": ""},
    {"id": "n1", "type": "nonexistent",    "config": {}, "reason": "dup id + unknown type"}
  ],
  "edges": [
    {"sourceNodeId": "n1", "sourcePortKey": "ghost",
     "targetNodeId": "n2", "targetPortKey": "in",
     "reason": ""}
  ]
}
```

Validation output (excerpt):

```json
{
  "valid": false,
  "errors": [
    {"path": "nodes[1].id",           "code": "duplicate_node_id",
     "message": "Duplicate node id 'n1' (also at nodes[0])"},
    {"path": "nodes[1].type",         "code": "unknown_node_type",
     "message": "Unknown node type 'nonexistent' (not registered in NodeTemplateRegistry)"},
    {"path": "nodes[0].config.style", "code": "config_invalid",
     "message": "The style field is required."},
    {"path": "nodes[0].config.structure", "code": "config_invalid",
     "message": "The selected structure is invalid."},
    {"path": "edges[0].sourcePortKey", "code": "edge_unknown_port",
     "message": "Output port 'ghost' on node 'n1' (scriptWriter) is not defined on template"},
    {"path": "edges[0].targetNodeId", "code": "edge_unknown_node",
     "message": "Edge target 'n2' does not exist in plan.nodes"},
    {"path": "nodes[0].inputs.prompt", "code": "required_input_missing",
     "message": "Required input 'prompt' on node 'n1' (scriptWriter) has no incoming edge and no config default"}
  ],
  "warnings": [
    {"path": "nodes[0].reason", "code": "missing_reason",
     "message": "Node 'n1' has no reason — drift-eval will lose explanatory signal"},
    {"path": "edges[0].reason", "code": "missing_reason", "message": "..."}
  ]
}
```

## 7. Converter Guarantees

`WorkflowPlanToDocumentConverter::convert($plan)` produces a document array that:

- Is accepted by the existing backend `WorkflowValidator::validate()` with zero errors (proven in `WorkflowPlanToDocumentConverterTest::converted_document_passes_existing_workflow_validator`).
- Emits edge keys in **both** the frontend-canonical form (`sourceNodeId`, `sourcePortKey`, `targetNodeId`, `targetPortKey`) **and** the backend-legacy aliases (`source`, `target`, `sourceHandle`, `targetHandle`). Removes naming drift between the two layers.
- Copies each node's `reason` into `notes` so the canvas shows planner intent inline.
- Fills `meta.source = "workflow-designer"` plus `intent`, `vibeMode`, `assumptions`, raw `planner` meta — so observability and drift-eval can tell runs apart.
- Uses a 5-column grid layout (`LAYOUT_STEP_X=320`, `LAYOUT_STEP_Y=180`) as a placeholder; a proper layout pass can be added later without changing the contract.

## 8. Guidance for 645.4 (planner implementation)

- Emit `PlanNode.id`s as short, unique slugs (the id surfaces in error paths; `src`, `writer`, `split` is easier to debug than a UUID).
- Every node and edge **must** carry a non-empty `reason` — missing reasons produce warnings, not errors, but drift-eval (645.5) scores on them.
- When an input is satisfied by a fixed value (no upstream node), put it under `config.inputs.<portKey>` (preferred) or as a top-level config field matching the port key. Either form satisfies `required_input_missing`.
- Prefer edges over embedded inputs when the value is the output of another plannable node — that's the whole point of the graph.
- Config fields must match each template's `configRules()` verbatim. Use `WorkflowPlanValidator::configSchemaFor($nodeType)` to fetch JSON Schema for prompt-side constraint injection.
- On `type_coercion` warnings (scalar→list), the plan is still valid — the runtime will auto-wrap. Leave as-is; don't insert adapter nodes.
- Cycles fail the whole plan, so the planner must reason about order before emitting.
- Meta fields the runtime uses: `plannerVersion`, `modelUsed`, `generatedAt`. Add anything else freely.
