# AiModel Backend API

Base URL: `http://localhost:8000/api`

## Health

### GET /health

```json
// Response 200
{
  "status": "ok",
  "timestamp": "2026-04-05T00:00:00.000Z",
  "services": {
    "database": "ok",
    "redis": "ok",
    "queue": "ok"
  }
}
```

## Workflows

### GET /workflows

List all workflows.

```json
// Response 200
{
  "data": [
    {
      "id": "uuid",
      "name": "My Video Pipeline",
      "description": "Generates AI videos",
      "schemaVersion": 1,
      "tags": ["demo"],
      "createdAt": "2026-04-05T00:00:00.000Z",
      "updatedAt": "2026-04-05T00:00:00.000Z"
    }
  ]
}
```

### POST /workflows

Create a new workflow.

```json
// Request
{
  "name": "My Pipeline",
  "description": "Optional description",
  "tags": ["video", "ai"],
  "document": {
    "nodes": [
      {"id": "n1", "type": "userPrompt", "config": {"prompt": ""}, "position": {"x": 0, "y": 0}},
      {"id": "n2", "type": "scriptWriter", "config": {"provider": "stub", "style": "conversational", "structure": "three_act", "includeHook": true, "includeCTA": true, "targetDurationSeconds": 90}, "position": {"x": 300, "y": 0}}
    ],
    "edges": [
      {"id": "e1", "source": "n1", "sourceHandle": "prompt", "target": "n2", "targetHandle": "prompt"}
    ]
  }
}

// Response 201
{
  "data": {
    "id": "uuid",
    "name": "My Pipeline",
    ...
  }
}
```

### GET /workflows/{id}

Get a single workflow with its document.

```json
// Response 200
{
  "data": {
    "id": "uuid",
    "name": "My Pipeline",
    "document": { "nodes": [...], "edges": [...] },
    ...
  }
}
```

### PUT /workflows/{id}

Update a workflow.

```json
// Request
{
  "name": "Updated Name",
  "document": { "nodes": [...], "edges": [...] }
}

// Response 200
{ "data": { ... } }
```

### DELETE /workflows/{id}

```json
// Response 204 (no content)
```

## Runs

### POST /workflows/{id}/runs

Trigger a workflow execution.

```json
// Request
{
  "trigger": "runWorkflow",      // runWorkflow | runNode | runFromHere | runUpToHere
  "targetNodeId": "node-id"      // required for runNode, runFromHere, runUpToHere
}

// Response 202
{
  "data": {
    "id": "run-uuid",
    "workflowId": "workflow-uuid",
    "trigger": "runWorkflow",
    "targetNodeId": null,
    "plannedNodeIds": null,
    "status": "pending",
    "startedAt": null,
    "completedAt": null,
    "terminationReason": null,
    "nodeRunRecords": []
  }
}
```

### GET /runs/{id}

Get run status with all node records.

```json
// Response 200
{
  "data": {
    "id": "run-uuid",
    "workflowId": "workflow-uuid",
    "status": "success",
    "nodeRunRecords": [
      {
        "id": "record-uuid",
        "nodeId": "n1",
        "status": "success",
        "outputPayloads": {"script": {"value": {...}, "status": "success"}},
        "durationMs": 150,
        "usedCache": false,
        "errorMessage": null
      }
    ]
  }
}
```

### POST /runs/{id}/cancel

Cancel a running or awaiting-review run.

```json
// Response 200
{
  "data": {
    "id": "run-uuid",
    "status": "cancelled",
    "terminationReason": "userCancelled",
    ...
  }
}

// Response 422 (if already completed)
{
  "error": "Run cannot be cancelled in its current status",
  "status": "success"
}
```

### POST /runs/{id}/review

Submit a review decision for a ReviewCheckpoint node.

```json
// Request
{
  "nodeId": "review-node-id",
  "decision": "approve",          // approve | reject
  "notes": "Optional reviewer notes"
}

// Response 200
{
  "message": "Review submitted",
  "nodeId": "review-node-id",
  "decision": "approve"
}

// Response 422 (not awaiting review)
{
  "error": "Run is not awaiting review",
  "status": "running"
}
```

## SSE Stream

### GET /runs/{id}/stream

Server-Sent Events stream for real-time run progress.

**Headers:**
- `Content-Type: text/event-stream`
- `Cache-Control: no-cache`
- `Connection: keep-alive`

**Event types:**

#### run.catchup
Sent immediately on connection with current state.

```
event: run.catchup
data: {"run": {"id": "...", "status": "running", "plannedNodeIds": [...]}, "nodeRunRecords": [...]}
```

#### run.started
Sent when execution begins.

```
event: run.started
data: {"runId": "...", "status": "running", "plannedNodeIds": ["n1", "n2", "n3"]}
```

#### node.status
Sent on each node status change.

```
event: node.status
data: {"runId": "...", "nodeId": "n1", "status": "success", "outputPayloads": {...}, "durationMs": 150, "usedCache": false}
```

```
event: node.status
data: {"runId": "...", "nodeId": "n2", "status": "error", "errorMessage": "Provider timeout"}
```

#### run.completed
Sent when run finishes.

```
event: run.completed
data: {"runId": "...", "status": "success", "terminationReason": null, "completedAt": "2026-04-05T00:00:00+00:00"}
```

## Artifacts

### GET /artifacts/{id}

Download a generated artifact (image, audio, video).

**Response:** Binary file with correct `Content-Type` header.

```
HTTP/1.1 200 OK
Content-Type: image/png
Content-Disposition: attachment; filename="image-0.png"

<binary data>
```

**404** if artifact not found.

## Error Format

All errors follow a consistent structure:

```json
{
  "error": {
    "code": "not_found",
    "message": "Resource not found"
  }
}
```

Provider errors (502):

```json
{
  "error": {
    "code": "provider_error",
    "message": "OpenAI request failed",
    "provider": "openai",
    "capability": "text_generation",
    "retryable": true,
    "original": "API rate limit exceeded"
  }
}
```

Validation errors (422):

```json
{
  "message": "The trigger field is required.",
  "errors": {
    "trigger": ["The trigger field is required."]
  }
}
```
