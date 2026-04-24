---
name: workflow runner design
overview: Concrete design for the V2 workflow runner — the durable-execution core that replaces the current planner/template stack. Nodes are thin PHP classes with a single execute() method; a replay-based runtime makes human-loop pauses feel synchronous to implementers while surviving Laravel queue restarts.
todos:
  - id: node-base
    content: Build the abstract Node class with step counter, helper surface (llm, ask, askApproval, askChoice, notify, input, prompt, emit), and boot() hook registration.
    status: pending
  - id: journal-schema
    content: Ship runs, journal, and pending_interactions tables with UNIQUE(run_id, node_name, step) constraint and expiry column.
    status: pending
  - id: runner-core
    content: Implement Runner::advance() — topological walk, input resolution from journal, node instantiation, PauseException/Throwable handling.
    status: pending
  - id: queue-jobs
    content: Implement RunStepJob and DeliverReplyJob with row-locking, status-guard idempotency, and clean pause/resume handoff.
    status: pending
  - id: determinism-guards
    content: Add PHPStan rule banning non-deterministic symbols inside execute(); add runtime step-kind check raising NonDeterminismDetected on mismatch.
    status: pending
  - id: dev-tooling
    content: Add php artisan workflow:trace {runId} to print journal timeline; add drain-check command for deploy safety.
    status: pending
isProject: false
---

# Workflow Runner Design

**Companion to:** [2026-04-23-laravel-backend-rewrite.md](./2026-04-23-laravel-backend-rewrite.md)

**Focus:** The runner (durable-execution core) and the node shape it runs. Persister is thin; builder is deferred.

## Goal
Replace the current planner/template stack with a runtime where:
- A node is a ~15-line PHP class + a prompt template.
- `execute()` reads linearly even though it pauses for hours on Telegram replies.
- Runs survive worker restarts, redeploys, and retries without re-paying for LLM calls.
- No new services (no Temporal, no workflow server) — pure Laravel + Postgres + queue.

## Non-goals
- Timers, scheduled workflows, child workflows — YAGNI.
- Workflow-document format and builder — deferred, see the parent rewrite plan.
- Frontend editor integration — later phase.
- Multi-node parallelism within a run — sequential is fine for MVP.

## Node contract

### Shape
Every node type is a class extending `App\WorkflowV2\Runtime\Node`. Implementer writes:
1. `execute(): array` — main logic.
2. Optional `boot(): void` — subscribe to lifecycle hooks.
3. A co-located `prompt.md` template (optional).

```php
class ScriptWriter extends Node {
    public function execute(): array {
        $draft = $this->llmWithPrompt(['topic' => $this->input('topic')]);
        if (!$this->askApproval("Approve this draft?\n\n$draft")) {
            $draft = $this->llm("Rewrite with more energy:\n$draft");
        }
        return ['script' => $draft];
    }
}
```

### Helper surface (provided by base class)

**LLM**
- `llm(string $prompt): string`
- `llmJson(string $prompt, array $schema): array`
- `prompt(array $vars = []): string` — render this node's own `prompt.md`
- `llmWithPrompt(array $vars, ?array $schema = null): string|array` — shortcut combining prompt + llm

**Human / Telegram (blocking — runtime pauses)**
- `ask(string $question): string`
- `askChoice(string $question, array $options): string`
- `askApproval(string $subject): bool`
- `notify(string $message): void` — fire-and-forget, never pauses

**I/O**
- `input(string $key): mixed` — read named input from upstream node
- `inputs(): array` — whole input array
- Output is the return value of `execute()`

**Context (read-only properties)**
- `runId`, `nodeName`, `chatId`
- `config(string $key): mixed`

**Hooks**
- Automatic lifecycle events: `started`, `finished`, `failed`, `paused`, `resumed`.
- `emit(string $event, array $data = []): void` for rare custom events.
- `boot()` registers listeners via `$this->on('finished', fn($out) => ...)`.

### Implementer rule (the one rule)
**All side effects must go through helpers.** No direct `Http::`, no `DB::`, no `rand()`, no `now()`. PHPStan enforces this; runtime detects drift.

## Runtime model — durable execution via replay

### What replay does
`execute()` is a pure function of `(journal, inputs)`. On every invocation (first run or resume), it runs from the top. Each helper call increments a step counter and consults the journal:

- **Journal has entry for this step** → return memoized value, no external call.
- **No entry** → do the real call, append to journal, return result.
- **`ask*` with no entry** → send Telegram, create `pending_interactions` row, throw `PauseException`.

The "resume point" is emergent: the first step without a journal entry is where real work happens. Nothing in the runtime tracks line numbers, ASTs, or continuations.

### Concrete helper internals (canonical pattern)

```php
protected function llm(string $prompt): string {
    $step = ++$this->stepCounter;
    $entry = $this->journal->find($this->runId, $this->nodeName, $step);

    if ($entry) {
        if ($entry->kind !== 'llm') {
            throw new NonDeterminismDetected("Step {$step} was '{$entry->kind}', now 'llm'");
        }
        return $entry->payload['result'];
    }

    $result = $this->llmGateway->complete($prompt, idempotencyKey: $this->stepKey($step));
    $this->journal->append($this->runId, $this->nodeName, $step, 'llm', ['result' => $result]);
    return $result;
}

protected function ask(string $question, string $type = 'text', array $options = []): string {
    $step = ++$this->stepCounter;
    $entry = $this->journal->find($this->runId, $this->nodeName, $step);

    if ($entry) {
        if ($entry->kind !== 'ask') {
            throw new NonDeterminismDetected("Step {$step} was '{$entry->kind}', now 'ask'");
        }
        return $entry->payload['result'];
    }

    $pending = $this->pending->openOrFind(
        runId: $this->runId, nodeName: $this->nodeName, step: $step,
        type: $type, options: $options, question: $question,
    );
    if (!$pending->message_id) {
        $messageId = $this->telegram->send($this->chatId, $question, $this->keyboardFor($type, $options));
        $this->pending->attachMessageId($pending->id, $messageId);
    }
    throw new PauseException($pending->id, $step);
}
```

## Runner loop

### `Runner::advance(Run $run)` responsibilities
1. Load workflow graph for the run.
2. Query journal for node statuses (`finished` markers per node).
3. Pick next node with all upstream inputs available.
4. Resolve inputs by reading upstream nodes' `finished` journal entries (outputs are stored in the marker payload).
5. Instantiate node class via container, inject `Context`, `Journal`, `Telegram`, `PendingInteractions`.
6. Call `execute()`.
7. On return: append `finished` marker to journal with output payload. Loop to step 2.
8. On `PauseException`: stop walking, caller marks run `paused`.
9. On `\Throwable`: stop walking, caller marks run `failed`. Let Laravel retry if transient.

### Topological walk
Simple Kahn's algorithm over the workflow document's edges. Cycles are rejected at builder-time; runner trusts the graph is valid. No parallelism in v1.

### Input resolution
Each edge maps `{from: nodeA.output.key, to: nodeB.input.key}`. Runner reads nodeA's `finished` journal row and passes the mapped value into nodeB's input bag.

## Queue integration

### Two jobs only

**`RunStepJob(runId)`** — advances a run until pause/finish/fail.
```php
public function handle(Runner $runner, Hooks $hooks): void {
    DB::transaction(function () use ($runner, $hooks) {
        $run = Run::lockForUpdate()->find($this->runId);
        if (!in_array($run->status, ['queued', 'resuming'])) return;  // idempotent exit
        $run->update(['status' => 'running']);
    });

    try {
        $runner->advance(Run::find($this->runId));
        Run::where('id', $this->runId)->update(['status' => 'finished']);
    } catch (PauseException $e) {
        Run::where('id', $this->runId)->update(['status' => 'paused']);
        // Clean exit — do NOT re-throw
    } catch (\Throwable $e) {
        Run::where('id', $this->runId)->update(['status' => 'failed', 'error' => $e->getMessage()]);
        throw $e;  // let Laravel retry/backoff handle transient errors
    }
}
```

**`DeliverReplyJob(runId, reply, chatId)`** — writes a reply to the journal and wakes the run.
```php
public function handle(): void {
    DB::transaction(function () {
        $pending = PendingInteraction::where('chat_id', $this->chatId)
            ->where('status', 'open')
            ->lockForUpdate()->latest()->firstOrFail();

        $value = $this->coerce($this->reply, $pending->type, $pending->options);
        Journal::create([
            'run_id'    => $pending->run_id,
            'node_name' => $pending->node_name,
            'step'      => $pending->step,
            'kind'      => 'ask',
            'payload'   => ['result' => $value, 'raw' => $this->reply],
        ]);
        $pending->update(['status' => 'delivered', 'raw_reply' => $this->reply]);
        Run::where('id', $pending->run_id)->update(['status' => 'resuming']);
    });
    RunStepJob::dispatch($pending->run_id);
}
```

### Concurrency invariants
- `RunStepJob` does `SELECT ... FOR UPDATE` on the `runs` row inside a transaction, checks status — double dispatch is a no-op.
- `DeliverReplyJob` takes the same lock; ordering with in-flight pauses is guaranteed.
- `UNIQUE(run_id, node_name, step)` on `journal` makes double writes impossible.

## Data model (runtime only)

```
runs
  id, workflow_id, status (queued|running|paused|resuming|finished|failed),
  inputs (jsonb), output (jsonb), error (text),
  created_at, updated_at

journal
  id, run_id, node_name, step, kind (llm|ask|llm_json|finished|emit),
  payload (jsonb), created_at
  UNIQUE(run_id, node_name, step)
  INDEX(run_id, node_name)

pending_interactions
  id, run_id, node_name, step, chat_id, message_id, type, options (jsonb),
  question, status (open|delivered|timed_out), raw_reply,
  expires_at, created_at
  INDEX(chat_id, status), INDEX(run_id)
```

Workflow document storage is a builder concern and left to the parent plan.

## Safety invariants + defenses

| Bug class | Defense | Residual risk |
|---|---|---|
| Non-determinism in `execute()` | PHPStan rule bans `rand/now/Http/DB/Cache` inside execute(); runtime kind-check raises `NonDeterminismDetected` | None if CI enforced |
| Double-spend on LLM (crash between call and journal) | Idempotency key `hash(runId+nodeName+step)` sent to Fireworks; journal write immediately follows HTTP response | Sub-millisecond window, bounded cost |
| Reply races in-flight pause | Row lock on `runs`; `DeliverReplyJob` blocks until pause tx commits | Eliminated |
| Two workers on same run | `lockForUpdate` + status guard | Eliminated |
| Orphaned paused runs | `pending_interactions.expires_at` default +24h; daily scheduled sweep marks `timed_out` and resumes with null or fails per node config | Operator visible |
| Deploy breaks in-flight runs | Drain-check: block deploy if `runs.status='paused' > 0`; mismatched step-kind raises loud failure | Operator visible, never silent |
| Retry storm | Laravel `tries=5` + exponential backoff; replay of prior steps is free (memoized) | Eliminated |
| Journal dup write | `UNIQUE(run_id, node_name, step)` | DB enforces |
| Implementer misuse | PHPStan + `workflow:trace` dev tool + short "Writing a Node" doc | Low |

The "eliminated" rows are impossible if the defenses land. Everything else fails **loudly and visibly** — never silently corrupts a run.

## Implementation phases

### Phase A — Node base + journal
- Migrations: `runs`, `journal`, `pending_interactions`.
- Abstract `Node` class with step counter and the full helper surface.
- `Journal`, `PendingInteractions` services.
- Unit tests: helper memoization, step-kind mismatch detection.

### Phase B — Runner core
- `Runner::advance()` with topological walk and input resolution.
- `PauseException`, `NonDeterminismDetected` exceptions.
- Unit tests: end-to-end single-node run with mock LLM, mock Telegram.

### Phase C — Queue wiring
- `RunStepJob`, `DeliverReplyJob`.
- Row-lock + idempotency tests.
- Integration test: run → pause → deliver reply → resume → finish.

### Phase D — Safety nets
- PHPStan custom rule for banned symbols in `execute()`.
- `workflow:trace {runId}` artisan command.
- `workflow:drain-check` artisan command.
- Scheduled sweep for expired `pending_interactions`.

### Phase E — Narrow MVP proof
- One hardcoded workflow (e.g. `userPrompt → scriptWriter → humanGate → finalOutput`).
- End-to-end: create run, pause on approval, reply on Telegram, resume, finish.
- Verified via `workflow:trace` showing full journal.

## Success criteria
- A 15-line node class + a prompt template is enough to ship a new node type.
- A run can pause on a Telegram approval and resume cleanly after a worker restart.
- Replay never calls Fireworks twice for the same logical step under normal operation.
- Every failure mode is visible in the run row or logs — nothing fails silently.
- `php artisan workflow:trace` gives a readable timeline for any run.
- The old planner/template stack is reachable only as reference; V2 runtime has no dependency on it.

## Deferred (not this plan)

**Persister (thin).** Beyond the three runtime tables above, workflow CRUD is standard Laravel. Specify when the builder design lands.

**Builder.** Document format, node registry wiring, graph validation. Tied to frontend editor contract. Design separately once runner is proven on a hardcoded workflow.

**Parallel node execution.** Sequential walk is enough for the MVP.

**Timers and scheduled workflows.** Use Laravel's scheduler / delayed dispatch when the rare case arises; do not build into runner.
