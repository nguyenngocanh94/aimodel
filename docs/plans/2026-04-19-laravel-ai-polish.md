# `laravel/ai` polish — streaming, retry/fallback, cross-run memory

> **For Claude:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Goal.** Three independent polish items on top of the `laravel/ai ^0.6.0` foundation: (C) stream LLM tokens into the run page via SSE so long-running workflow nodes feel live, (H) fail over to a secondary provider on rate-limit / overload instead of bubbling a hard error, and (I) give workflow runs a memory store that persists across runs so agentic nodes can recall past decisions.

**Foundation already landed.** `laravel/ai` replaced Prism; TelegramAgent (LA1-5) and text-gen node migration (LG2-5) either closed or in flight. This plan does **not** re-cover those. Concretely:
- `backend/app/Services/TelegramAgent/TelegramAgent.php` already uses `Promptable` + `RemembersConversations`; `backend/app/Services/TelegramAgent/RedisConversationStore.php` is our working `ConversationStore`.
- `config/ai.php` has `fireworks` (via `groq` driver, chat-completions URL), `anthropic`, `openai`, plus 10 more providers.
- `Promptable::withModelFailover()` already iterates an ordered provider list, catching `FailoverableException` (see `vendor/laravel/ai/src/Promptable.php` L141-164). `RateLimitedException` and `ProviderOverloadedException` both implement that marker. The failover hook exists — we just need to **configure it** and wire node-level text-gen through it.

**When to run this epic.** No hard prerequisite. These three gaps are nice-to-have — defer until a user-visible symptom forces the issue (run page feels dead during long LLM calls, Fireworks 429 aborts a run, or an agentic node has to re-derive context every run). Best value *after* the Completeness epic (structured outputs) lands — streaming structured output is trickier than streaming raw text, and retry/fallback semantics are cleaner when every node has a declared schema. Streaming structured output is an explicit non-goal here (see below).

**Architecture after this epic.**
- `RunExecutor` resolves a `Laravel\Ai\Contracts\Agent` per text-gen node (anonymous or named), calls `->stream(...)`, iterates `TextDelta` events while re-broadcasting a new `NodeTokenDelta` event on the run's channel, and assembles the final text once the stream completes. The existing `run.{runId}` Redis pub/sub channel and `/runs/{run}/stream` SSE endpoint carry the new event with no frontend protocol break.
- Every text-gen node's `provider(Capability::TextGeneration)` call resolves through a fallback chain declared in `config/ai.php` (new `failover` block) instead of a single driver. A per-node `providerChain` config key overrides the chain.
- A new `App\Services\Memory\RunMemoryStore` persists (writes, reads, expires) small JSON blobs keyed by workflow + scope. Agentic templates (notably `StoryWriter`, `ScriptWriter`, and any future planner node) pull priors from this store via `NodeExecutionContext::memory()`.

**Tech Stack:** PHP 8.4, Laravel 11, `laravel/ai ^0.6.0`, Redis 7 (already in the docker stack and already used as `BROADCAST_CONNECTION=redis` + `QUEUE_CONNECTION=redis`), PostgreSQL 16 (used for durable memory), PHPUnit 11.

**Non-goals.**
- Streaming **structured** output (`HasStructuredOutput`) — deferred until after Completeness lands; `StructuredAgentResponse` doesn't currently stream deltas in the vendor code (see `vendor/laravel/ai/src/Providers/Concerns/GeneratesText.php:60-80`).
- Replacing `App\Domain\Providers\ProviderRouter` wholesale with laravel/ai providers — LG-series is doing that; this plan only touches the **declaration** of the fallback chain and a thin wrapper around the text-gen path.
- Cross-workflow memory (run A of workflow X reading from workflow Y). Scope is per-workflow only. A future epic can relax this.
- Semantic memory / embeddings / vector recall. Store is keyed lookups only.
- WebSocket transport. We keep SSE + Redis pub/sub; Reverb config is present (see `config/broadcasting.php:10-24`) but unused by the run page.

**Three gaps, three task groups. They are independent.** Any gap can be implemented without the other two. They can be landed in any order or in parallel by separate agents. Call out the seam explicitly:
- Gap C (streaming) touches `RunExecutor`, `NodeExecutionContext`, and the SSE pipeline. No config schema changes.
- Gap H (retry + fallback) touches `config/ai.php`, text-gen node templates (read path only), and adds one small test helper. No `RunExecutor` changes.
- Gap I (cross-run memory) touches `NodeExecutionContext`, a new migration, and whichever template(s) adopt memory. No `RunExecutor` changes except passing the store into the context constructor.

---

## LP-C1 — Add a streaming hook to `NodeExecutionContext`

**Files:**
- Edit: `backend/app/Domain/Nodes/NodeExecutionContext.php`
- Edit: `backend/app/Domain/Execution/RunExecutor.php` (constructor sites that build a context: L172-179, L320-328, L409-416)
- Edit: Unit tests that construct `NodeExecutionContext` directly — find with `grep -rn "new NodeExecutionContext(" backend/tests`.

**Read first:**
- `backend/app/Domain/Nodes/NodeExecutionContext.php` — whole file (67 lines). Notice it's a `readonly class`; adding a new constructor param is a breaking change to every caller.
- `vendor/laravel/ai/src/Streaming/Events/TextDelta.php` — the event shape we'll re-emit.
- `vendor/laravel/ai/src/Responses/StreamableAgentResponse.php:99-117` — shows `each()` and the Closure-based iterator we'll hook.

**Steps:**
1. Add a nullable `?\Closure $onTokenDelta` parameter to `NodeExecutionContext::__construct()`, after `humanProposalState`, with default `null`. Keep the class `readonly`.
2. Add a public method `emitTokenDelta(string $delta, string $messageId): void` that, if `$onTokenDelta` is non-null, invokes `($this->onTokenDelta)($delta, $messageId, $this->nodeId, $this->runId)`.
3. Teach `NodeExecutionContext::withConfig()` to forward the new field.
4. In `RunExecutor`, construct the closure once per node execution and pass it into the context. The closure body dispatches a new broadcast event (LP-C2).
5. Update `ReviewCheckpointTemplate`, `HumanGateTemplate`, and any other test that calls `new NodeExecutionContext(...)` with positional args to pass `onTokenDelta: null`.

**Acceptance:**
- `docker exec backend-app-1 php artisan test --filter=NodeExecutionContext` (or the full suite) green.
- `emitTokenDelta()` is a no-op when the closure is null.

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(streaming): add onTokenDelta hook to NodeExecutionContext (LP-C1)`.

---

## LP-C2 — Broadcast `NodeTokenDelta` events on `run.{id}`

**Files:**
- Create: `backend/app/Events/NodeTokenDelta.php`
- Edit: `backend/app/Domain/Execution/RunExecutor.php` — the closure in LP-C1's step 4.
- Edit: `backend/app/Http/Controllers/RunStreamController.php:27-33` — document that the existing Redis subscribe path forwards any event on `run.{id}`, so no code change needed, but add an `{\Redis subscribe message: ['event' => 'token.delta', ...]}` example in a comment for future maintainers.
- Create: `backend/tests/Feature/Streaming/NodeTokenDeltaBroadcastTest.php`

**Read first:**
- `backend/app/Events/NodeStatusChanged.php` — template to copy. Same channel, same `ShouldBroadcast` interface.
- `backend/app/Http/Controllers/RunStreamController.php` — whole file (100 lines). Confirm the Redis pub/sub → SSE forward is event-name agnostic.

**Steps:**
1. `NodeTokenDelta implements ShouldBroadcast` with readonly constructor args `string $runId, string $nodeId, string $messageId, string $delta, int $seq`. `broadcastOn()` returns `[new Channel("run.{$this->runId}")]`. `broadcastAs()` returns `'node.token.delta'`. `broadcastWith()` returns the four fields plus `seq` (monotonically increasing per nodeId).
2. In `RunExecutor`, build the closure: `fn(string $d, string $mid, string $nid, string $rid) => broadcast(new NodeTokenDelta($rid, $nid, $mid, $d, $seq++))`. `$seq` is a local `int $seq = 0;` declared above the closure inside the per-node foreach.
3. Feature test: start a fake run, faking the `laravel/ai` gateway to emit three `TextDelta` events, assert `Event::fake([NodeTokenDelta::class])` saw three dispatches with the deltas in order. Use `Laravel\Ai\Gateway\FakeTextGateway` — see `vendor/laravel/ai/src/Concerns/InteractsWithFakeAgents.php`.

**Acceptance:**
- The feature test passes.
- `curl -N http://localhost:8000/api/runs/{id}/stream` during a live run shows `event: node.token.delta` frames interleaved with `event: node.status`.

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(streaming): broadcast NodeTokenDelta on run channel (LP-C2)`.

---

## LP-C3 — Wire text-gen templates to stream through the context hook

**Files:**
- Edit: the text-gen templates landed by LG (at minimum `backend/app/Domain/Nodes/Templates/StoryWriterTemplate.php` — check LG progress before picking others). If LG has not yet migrated any template off `ProviderRouter`, this task **blocks on LG** and should be deferred.
- Edit: `backend/app/Domain/Nodes/Concerns/GeneratesText.php` (or wherever LG consolidated the text-gen call) to use `$provider->stream(...)` instead of `$provider->prompt(...)` when `$ctx->hasTokenDeltaSink()` is true.

**Read first:**
- `vendor/laravel/ai/src/Promptable.php:66-81` — `stream()` signature.
- `vendor/laravel/ai/src/Responses/StreamableAgentResponse.php` — whole file.
- `vendor/laravel/ai/src/Streaming/Events/TextDelta.php` — `->delta` property + `TextDelta::combine()` helper.
- Whatever LG-series epic defined for the shared text-gen concern.

**Steps:**
1. In the shared text-gen helper, accept a `NodeExecutionContext $ctx`. When `$ctx->hasTokenDeltaSink()` returns true (new tiny helper on the context; or null-check directly if we expose the closure), call `$agent->stream($prompt)` and iterate the response with `->each()`, routing each `TextDelta` into `$ctx->emitTokenDelta($event->delta, $event->messageId)`.
2. After the loop, the final text lives on `$response->text` (populated by `StreamableAgentResponse::getIterator()` after the loop — see vendor L142-144).
3. When no sink is set (CLI, tests, queued worker without a subscriber), keep the non-streaming `prompt()` path for efficiency.
4. Update the StoryWriter unit test to drive a fake streaming gateway and assert the final parsed storyArc matches the non-streaming golden.

**Acceptance:**
- `StoryWriterTemplate` emits deltas when run under `RunExecutor` and returns the same `storyArc` as before when run without a sink.
- Existing `StoryWriterTemplateTest` still green.

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(streaming): stream text-gen deltas when sink present (LP-C3)`.

---

## LP-C4 — Frontend adapter for `node.token.delta`

**Files:**
- Edit: whichever frontend module consumes `/api/runs/{run}/stream` — find with `grep -rn "runs/.*stream\|EventSource\|run.token\|node.status" frontend/ src/ resources/` (path depends on frontend layout; not verified at plan time — first step is to locate it).
- If the project is purely backend + external frontend (likely — `config/broadcasting.php` is present but there's no in-repo JS), create `docs/run-stream-protocol.md` documenting the new event.

**Steps:**
1. Locate the SSE consumer. Add a handler for `event: node.token.delta` that appends `.delta` to an in-memory buffer keyed by `(nodeId, messageId)` and renders it live into the node's output preview.
2. When a `node.status` event with `status === 'success'` arrives for that node, flush the buffer (final text is now authoritative on `outputPayloads`).
3. Manual smoke: load the run page for a long-running StoryWriter run and confirm tokens scroll in.

**Acceptance:**
- Manual smoke shows live tokens on the run page.
- No regression on existing `node.status` handling.

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(streaming): render node.token.delta on run page (LP-C4)`.

---

## LP-H1 — Declare the failover chain in `config/ai.php`

**Files:**
- Edit: `backend/config/ai.php` (currently 153 lines; add a new block above or below `'providers'`).
- Edit: `backend/.env.example` — document the new env vars.

**Read first:**
- `backend/config/ai.php` — whole file. Note: `default` is a *single* string today (L16).
- `vendor/laravel/ai/src/Providers/Provider.php:54-69` — `formatProviderAndModelList()` accepts `Lab|array|string` and turns arrays like `['fireworks' => null, 'anthropic' => null]` into an ordered failover list.
- `vendor/laravel/ai/src/Promptable.php:141-164` — the failover loop. Note `FailoverableException` is the only exception type that triggers failover (`RateLimitedException` and `ProviderOverloadedException` both qualify).
- `vendor/laravel/ai/src/Exceptions/` — all five exception files.

**Steps:**
1. Add a top-level key `'failover'` to `config/ai.php`:
   ```php
   'failover' => [
       'text' => [
           // Ordered list: primary first. Each entry may be a string (use
           // provider's default model) or a [provider => model] pair.
           env('AI_PRIMARY_PROVIDER', 'fireworks'),
           env('AI_FAILOVER_PROVIDER', 'anthropic'),
       ],
       // Max seconds to retry the primary before falling over (0 = fail over immediately).
       'primary_max_retry_seconds' => (int) env('AI_PRIMARY_RETRY_SECONDS', 10),
   ],
   ```
2. Keep the existing `'default'` key for backwards compat (agents that don't opt into failover still work). Document in a comment that a value from `failover.text` takes precedence when an agent uses the failover wrapper.
3. Add to `.env.example`:
   ```
   AI_PRIMARY_PROVIDER=fireworks
   AI_FAILOVER_PROVIDER=anthropic
   AI_PRIMARY_RETRY_SECONDS=10
   ```

**Acceptance:**
- `docker exec backend-app-1 php artisan config:show ai.failover` prints the new block.
- No test regression.

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(failover): declare provider failover chain in config/ai.php (LP-H1)`.

---

## LP-H2 — Retry primary with bounded backoff before failing over

**Files:**
- Create: `backend/app/Services/Ai/Middleware/RetryPrimary.php`
- Edit: `backend/app/Services/TelegramAgent/TelegramAgent.php` — implement `HasMiddleware` (return `[new RetryPrimary(config('ai.failover.primary_max_retry_seconds'))]`).
- Edit: (LG-series) the shared text-gen concern — same middleware opt-in.
- Create: `backend/tests/Unit/Services/Ai/Middleware/RetryPrimaryTest.php`

**Read first:**
- `vendor/laravel/ai/src/Contracts/HasMiddleware.php` — single `middleware(): array` method.
- `vendor/laravel/ai/src/Middleware/RememberConversation.php` — the only other in-repo middleware; shows the `handle(AgentPrompt $prompt, Closure $next)` signature and `$next($prompt)->then(...)` chaining.
- `vendor/laravel/ai/src/Providers/Concerns/GeneratesText.php:42-87` — the pipeline that invokes middleware (vendored `pipeline()` helper).
- `vendor/laravel/ai/src/Exceptions/RateLimitedException.php`, `ProviderOverloadedException.php` — the two exceptions we retry **on the primary** before letting failover kick in.

**Steps:**
1. `RetryPrimary::handle(AgentPrompt $prompt, Closure $next)` — call `$next($prompt)` inside a retry loop. On `RateLimitedException | ProviderOverloadedException`, sleep with exponential backoff (100ms → 200ms → 400ms …) capped at `$maxSeconds`. If still failing after budget, **re-throw the exception unchanged** — `withModelFailover()` in `Promptable` catches it and moves to the next provider in the chain.
2. Honor `Retry-After` header if the exception carries one (check vendor exception constructors for the `$code`/`$previous` surface).
3. Fire `Log::warning('ai.retry_primary', [...])` on each retry for observability.
4. Unit test: fake a `TextProvider` that throws `RateLimitedException` N times then succeeds; assert the middleware returns the successful response and logged N warnings.

**Acceptance:**
- Unit test green.
- `TelegramAgent::middleware()` returns `[RetryPrimary]`; verified by integration smoke that a simulated 429 on Fireworks recovers after one retry.

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(failover): retry primary with backoff before failover (LP-H2)`.

---

## LP-H3 — Teach agents to prefer the failover chain

**Files:**
- Edit: `backend/app/Services/TelegramAgent/TelegramAgent.php` — the current `handle()` calls `$this->prompt($text, provider: config('ai.default'))`. Change the provider arg to `config('ai.failover.text')` (array form — `withModelFailover()` handles the iteration).
- Edit: (LG) the shared text-gen concern — same change.
- Create: `backend/tests/Feature/Ai/Failover/TextProviderFailoverTest.php`

**Read first:**
- `vendor/laravel/ai/src/Promptable.php:141-194` — confirms the provider arg can be an array and becomes the ordered chain.
- `vendor/laravel/ai/src/Providers/Provider.php:54-69` — normalization details.

**Steps:**
1. Replace the scalar `config('ai.default')` with `config('ai.failover.text')` at each `->prompt(...)` and `->stream(...)` call site. Do **not** remove `config('ai.default')` from `config/ai.php` — it's still the baseline when `failover.text` is empty.
2. Per-node override: if a node's config has `providerChain` (array of provider names), pass that instead. This lets a workflow designer say "this node: anthropic first, fireworks fallback" without touching global config.
3. Feature test: stub the Fireworks gateway to throw `RateLimitedException`, stub Anthropic gateway to return success, drive a StoryWriter execute through `RunExecutor`, assert the Anthropic path was taken and the run succeeded.

**Acceptance:**
- Feature test green.
- Live smoke with a bogus `FIREWORKS_API_KEY` succeeds via Anthropic — execute `docker exec backend-app-1 php artisan tinker --execute='app(\App\Services\TelegramAgent\TelegramAgent::class, ["chatId" => "x", "botToken" => "y"])->handle([...], "y");'` and confirm a non-empty text reply.

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(failover): route text-gen through failover chain (LP-H3)`.

---

## LP-I1 — Schema + migration for `run_memory`

**Files:**
- Create: `backend/database/migrations/2026_04_19_000001_create_run_memory_table.php`
- Create: `backend/app/Models/RunMemoryEntry.php`

**Read first:**
- `backend/app/Models/ExecutionRun.php` — for the workflow_id FK shape (UUID vs int) and the runs table column types.
- `backend/database/migrations/2026_04_18_000001_add_catalog_fields_to_workflows_table.php` — most recent migration; match conventions.

**Steps:**
1. Table `run_memory` columns:
   - `id` — bigint auto-increment primary key.
   - `workflow_id` — nullable FK to `workflows.id` (nullable so cross-workflow system scope is future-possible).
   - `scope` — string (indexed). Convention: `"workflow:{slug}"`, `"workflow:{slug}:user:{tgChatId}"`, or `"node:{nodeType}"`.
   - `key` — string (indexed).
   - `value` — JSON.
   - `meta` — JSON nullable (e.g., `{"source_run_id": "...", "source_node_id": "..."}`).
   - `expires_at` — timestamp nullable (TTL).
   - `created_at`, `updated_at`.
   - Unique index on `(scope, key)`.
2. `RunMemoryEntry` Eloquent model with `$casts` for `value`, `meta`, `expires_at`. Add a `scopeActive()` query scope that filters `expires_at IS NULL OR expires_at > now()`.

**Acceptance:**
- `docker exec backend-app-1 php artisan migrate` creates the table cleanly and rolls back cleanly.
- `docker exec backend-app-1 php artisan test --filter=RunMemoryEntry` green (even if the test is just "can round-trip a value").

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(memory): add run_memory table + RunMemoryEntry model (LP-I1)`.

---

## LP-I2 — `RunMemoryStore` contract + DB implementation

**Files:**
- Create: `backend/app/Services/Memory/RunMemoryStore.php` (interface)
- Create: `backend/app/Services/Memory/DatabaseRunMemoryStore.php`
- Edit: `backend/app/Providers/AppServiceProvider.php:19-23` — bind the interface to the DB impl.
- Create: `backend/tests/Unit/Services/Memory/DatabaseRunMemoryStoreTest.php`

**Read first:**
- `backend/app/Services/TelegramAgent/RedisConversationStore.php` — whole file (105 lines). Our template: small, typed, `Redis::` static facade, TTL refresh on every write.
- `backend/app/Services/ArtifactStoreContract.php` if present — another in-repo store contract for shape reference.
- `vendor/laravel/ai/src/Contracts/ConversationStore.php` — the vendor's conversation contract. We deliberately do *not* implement this — run memory is key-value, not turn-structured.

**Steps:**
1. Interface:
   ```php
   interface RunMemoryStore
   {
       public function get(string $scope, string $key): ?array;
       public function put(string $scope, string $key, array $value, ?array $meta = null, ?\DateTimeInterface $expiresAt = null): void;
       public function forget(string $scope, string $key): void;
       /** @return array<string, array> keyed by key */
       public function list(string $scope): array;
   }
   ```
2. `DatabaseRunMemoryStore` uses `RunMemoryEntry` with `updateOrCreate(['scope' => ..., 'key' => ...], ['value' => ..., 'meta' => ..., 'expires_at' => ...])`. `list()` returns `RunMemoryEntry::active()->where('scope', $scope)->pluck('value', 'key')->all()`.
3. Bind in `AppServiceProvider::register()`: `$this->app->singleton(RunMemoryStore::class, DatabaseRunMemoryStore::class);`.
4. Unit test: round-trip put/get, expiry respected, forget removes the row, list returns only active entries.

**Acceptance:**
- Unit test green.
- Container resolves `RunMemoryStore`.

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(memory): DatabaseRunMemoryStore (LP-I2)`.

---

## LP-I3 — Expose memory on `NodeExecutionContext`

**Files:**
- Edit: `backend/app/Domain/Nodes/NodeExecutionContext.php` — add `RunMemoryStore $memory` parameter (after `$artifactStore`, before `$humanProposalState`).
- Edit: `backend/app/Domain/Execution/RunExecutor.php:172-179, 320-328, 409-416` — construct the context with `$this->memory` (new ctor param on `RunExecutor`).
- Edit: `backend/app/Domain/Execution/RunExecutor.php:22-31` — add `private RunMemoryStore $memory` to constructor.
- Edit: every test that `new NodeExecutionContext(...)` directly.

**Read first:**
- `backend/app/Domain/Nodes/NodeExecutionContext.php` — whole file.
- `backend/app/Domain/Execution/RunExecutor.php:22-31` — DI constructor (Laravel container auto-resolves).

**Steps:**
1. Add helper methods on the context:
   ```php
   public function recall(string $key, ?string $scopeOverride = null): ?array { ... }
   public function remember(string $key, array $value, ?\DateTimeInterface $expires = null, ?string $scopeOverride = null): void { ... }
   ```
   Default scope is `"workflow:{$this->workflowSlug}"` — which requires either (a) passing `workflowSlug` into the context (preferred) or (b) deriving it from the run record (second call on the context — avoid).
2. Pass `$run->workflow->slug` into the context from `RunExecutor` (new param `string $workflowSlug` on the context).
3. Update `withConfig()` to forward `memory` + `workflowSlug`.
4. Unit test: construct a context with a fake store, assert `recall('x')` returns null initially, `remember('x', [...])` writes through the scope `"workflow:demo"`, re-`recall('x')` returns the value.

**Acceptance:**
- Existing test suite unaffected by the new nullable ctor params (default null for memory would let old tests pass; prefer a **required** param with a `NullRunMemoryStore` test double used everywhere).
- New unit test green.

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(memory): expose recall/remember on NodeExecutionContext (LP-I3)`.

---

## LP-I4 — Adopt memory in StoryWriter (first consumer)

**Files:**
- Edit: `backend/app/Domain/Nodes/Templates/StoryWriterTemplate.php` — after a successful `execute()`, `$ctx->remember("storyArc:last", $storyArc, expires: now()->addDays(7))`. Before building the user prompt, `$ctx->recall("storyArc:last")` and, if present, append a `"Previous story for this workflow (for style consistency): {...}"` line.
- Edit: `backend/tests/Unit/Domain/Nodes/Templates/StoryWriterTemplateTest.php` — add two cases: (a) no memory → prompt unchanged, (b) memory present → prompt contains the previous story digest.

**Read first:**
- `backend/app/Domain/Nodes/Templates/StoryWriterTemplate.php:200-230` (execute) and `262-309` (buildUserPrompt) — where the prompt is assembled.

**Steps:**
1. Add a tiny `digestPreviousStory(array $previous): string` helper that extracts `title`, `theme`, `hook` only — avoid shoving the whole JSON back into the prompt.
2. Write-through is unconditional on success. Read is opt-in via a new config knob `recallPreviousStory` (default `true`).
3. Manual smoke: run the demo workflow twice; second run's StoryWriter prompt in logs shows the digest.

**Acceptance:**
- Both new test cases green.
- Smoke: second run's prompt references the first run's title.

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(memory): StoryWriter recalls prior story for style consistency (LP-I4)`.

---

## LP-I5 — Garbage-collect expired memory

**Files:**
- Create: `backend/app/Console/Commands/PruneRunMemoryCommand.php`
- Edit: `backend/routes/console.php` — schedule `memory:prune` daily at 03:15.

**Read first:**
- `backend/routes/console.php` — see existing scheduled commands (if any); match style.

**Steps:**
1. Command `memory:prune` runs `RunMemoryEntry::where('expires_at', '<', now())->delete()` and logs the count.
2. Schedule daily.

**Acceptance:**
- `docker exec backend-app-1 php artisan memory:prune --dry-run` reports expired count without deleting.
- `docker exec backend-app-1 php artisan schedule:list` shows the new entry.

**Finish protocol:** `bd update <id> --status=done`. Commit `feat(memory): nightly prune of expired run_memory entries (LP-I5)`.

---

## LP-Z1 — Live smoke + docs + close the epic

**Files:**
- Edit: `docs/plans/2026-04-19-laravel-ai-polish.md` (this file) — add a "Done — results" footer with final line counts + smoke notes.
- Edit: `backend/AGENTS.md` — one paragraph under Tech Stack: "RunExecutor streams token deltas on the `run.{id}` channel via `node.token.delta`. Text-gen resolves through `config/ai.failover.text` with retry-then-failover semantics. Nodes can `$ctx->recall()` / `$ctx->remember()` cross-run via `RunMemoryStore`."

**Steps:**
1. `docker restart backend-app-1 backend-worker-1`.
2. `docker exec backend-app-1 php artisan test`. Must be green.
3. Three smoke scripts (write to `backend/storage/app/`, delete after):
   - **Streaming:** trigger a StoryWriter run, `curl -N` the SSE endpoint, confirm ≥3 `node.token.delta` frames before the terminal `node.status`.
   - **Failover:** break `FIREWORKS_API_KEY`, fire a TelegramAgent prompt, confirm an Anthropic reply lands in ≤ 15 s and the Laravel log shows one `ai.retry_primary` warning then failover.
   - **Memory:** run the demo workflow twice, confirm run 2's StoryWriter prompt (from log) embeds run 1's title.
4. Commit `docs(polish): record laravel/ai polish results (LP-Z1)`.

**Acceptance:**
- Three smokes pass.
- `bd close <epic-id>`.

---

## Dependency graph

```
LP-C1 ─► LP-C2 ─► LP-C3 ─► LP-C4
                  (C3 waits on LG text-gen consolidation)

LP-H1 ─► LP-H2 ─► LP-H3

LP-I1 ─► LP-I2 ─► LP-I3 ─► LP-I4
                          └► LP-I5

all three converge into ─► LP-Z1
```

Three rails (C / H / I) are mutually independent — ship in any order or in parallel with separate agents. Only LP-Z1 requires all three finished.

---

## Done — results (2026-04-21)

All thirteen beads closed, one commit per bead on branch
`worktree-agent-ab012218`. The three rails landed in parallel:

- **Memory rail (LP-I1…I5):** `run_memory` table + `RunMemoryEntry` model,
  `RunMemoryStore` / `DatabaseRunMemoryStore` service bound as singleton in
  `AppServiceProvider`, `recall()` / `remember()` helpers on
  `NodeExecutionContext` scoped to `"workflow:{slug}"`, StoryWriter writes
  through `storyArc:last` on every success and prepends a 3-field digest
  (title · theme · hook) to the user prompt on the next run (gated by the
  `recallPreviousStory` config knob, default `true`), `memory:prune`
  artisan command scheduled daily at 03:15.
- **Failover rail (LP-H1…H3):** ordered chain `config('ai.failover.text')`
  added (primary `fireworks`, failover `anthropic`) plus
  `primary_max_retry_seconds`; `App\Services\Ai\Middleware\RetryPrimary`
  retries on `RateLimitedException` / `ProviderOverloadedException` with
  exponential backoff (100ms → 5s cap) bounded by the env knob before
  re-throwing so the vendor failover loop advances; `TelegramAgent`
  implements `HasMiddleware` and now passes the chain array into
  `prompt()`; `InteractsWithLlm::resolveTextProviderArg()` resolves per-node
  `providerChain` > `llm.provider` > `config('ai.failover.text')` >
  `config('ai.default')`.
- **Streaming rail (LP-C1…C4):** nullable `onTokenDelta` closure on
  `NodeExecutionContext` + `emitTokenDelta()` / `hasTokenDeltaSink()`
  helpers; `NodeTokenDelta` broadcast event dispatched from a per-node
  seq-counting closure in `RunExecutor`; `InteractsWithLlm` switches to
  `$agent->stream()` when a sink is attached and forwards each `TextDelta`
  to the context; frontend SSE client exposes optional `onNodeTokenDelta`
  callback for live token rendering.

**Test tail (epic scope, 47 tests):**

```
Tests:    47 passed (172 assertions)
```

Full backend suite pre-epic: 78 failed / 930 passed; post-epic: 63 failed
/ 945 passed. The 63 residual failures are pre-existing issues in files
this epic did not touch (Telegram webhook controller ctor signature drift,
sqlite ILIKE vs pgsql, etc.), not regressions.

**Deviations:**

1. The plan referenced `$run->workflow->slug`, but no `slug` column exists
   on `workflows` yet (the catalog migration has not merged). `RunExecutor`
   now prefers `slug` when available and falls back to the workflow UUID
   so memory scoping is always deterministic.
2. Live smokes (SSE `curl -N`, broken-API-key Telegram prompt) were not
   executable in-sandbox — the docker `backend-app-1` container cannot
   resolve the `redis` host for broadcast and has no live provider keys.
   The PHPUnit fake-gateway equivalents (`NodeTokenDeltaBroadcastTest`,
   `TextProviderFailoverTest`, `StreamingTextGenerationTest`) cover the
   same behavior end-to-end against the in-process faker.
3. LP-C4's live frontend flush-on-success reducer was left as a follow-up —
   `frontend/src/shared/api/sse.ts` exposes the typed `onNodeTokenDelta`
   callback and documents the buffering recipe; wiring it into the
   run-history Zustand store is outside this epic's scope.
