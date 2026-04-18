# Telegram Agent Bridge

> **For Claude:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Goal:** Replace the per-workflow `telegramTrigger` dispatch with a single `TelegramAgent` that owns every inbound Telegram update. The agent (a) answers slash commands, (b) uses an LLM tool-use loop to match free-text messages to a registered workflow and run it, and (c) keeps ownership of the conversation while any `PendingInteraction` gate is open.

Today's problem: the bot only dispatches to a workflow whose document contains a `telegramTrigger` node with a matching `botToken`. That leaks Telegram into every graph, supports at most one workflow per bot token, gives no answer to commands (`/status`, `/list`), and produces the confusing *"❌ Không tìm thấy workflow phù hợp"* any time the user's intent can't be auto-matched.

**Architecture.** A chat-scoped `TelegramAgent` service sits between `TelegramWebhookController` and the rest of the system. The gate-resume branches (Priority 0/1/1b) stay as-is — they already work off `PendingInteraction` rows and don't need workflow identity. The intake/buffer branches (Priority 2/3) are **replaced** by the agent. Workflows gain a `WorkflowCatalog` metadata layer (`slug`, `triggerable`, `nl_description`, `param_schema`) that the agent reads as tool-use context. Anthropic Sonnet 4.6 drives the tool loop via an extended adapter that supports `tools` + `tool_use`/`tool_result` content blocks.

**Tech Stack:** PHP 8.4, Laravel 11, Redis (existing session store), Anthropic Messages API with tool use (`claude-sonnet-4-6`), existing `ProviderRouter` + `PendingInteraction` + `RunWorkflowJob` infrastructure. PHPUnit 11, no frontend work.

**Out of scope:** frontend UI for catalog management (CLI seeder is enough for v1); voice/audio intents; multi-turn NL workflow *design* (the agent picks from the catalog, it doesn't author new workflows).

---

## Task 1 — Workflow catalog metadata

**Files:**
- Create: `backend/database/migrations/2026_04_18_000001_add_catalog_fields_to_workflows_table.php`
- Edit: `backend/app/Models/Workflow.php`
- Test: `backend/tests/Unit/Models/WorkflowCatalogTest.php`

**Migration:** add nullable columns to `workflows`:
- `slug` (string, 120, unique nullable) — stable identifier the agent references.
- `triggerable` (boolean, default false) — whether the agent is allowed to pick it.
- `nl_description` (text nullable) — natural-language description shown to the LLM (what the workflow does, what it needs, when to use it).
- `param_schema` (json nullable) — Laravel-style validator rules describing the inputs the agent must collect before running (`{field: ["required","string"]}`).

**Model:** add `slug`, `triggerable`, `nl_description`, `param_schema` to `$fillable`; cast `param_schema` to `array`, `triggerable` to `bool`. Add `scopeTriggerable(Builder)` and `scopeBySlug(Builder, string)`.

**Test:** factory builds a triggerable workflow, `Workflow::triggerable()->bySlug('x')->first()` returns it, non-triggerable is filtered out.

**Acceptance:** migration runs cleanly up/down; model casts work; scope test passes.

---

## Task 2 — Catalog seeder + retrofit demo workflows

**Files:**
- Create: `backend/database/seeders/WorkflowCatalogSeeder.php`
- Edit: `backend/database/seeders/HumanGateDemoSeeder.php`

**Seeder:** backfill catalog fields for existing demo workflows:
- `"StoryWriter (per-node gate) – Telegram"` → `slug: story-writer-gated`, `triggerable: true`, `nl_description: "Viết kịch bản video TVC ngắn tiếng Việt (GenZ). Dùng khi người dùng yêu cầu tạo kịch bản / ý tưởng video / story cho một sản phẩm."`, `param_schema: {"productBrief": ["required","string","min:5"]}`.
- `"HumanGate Demo – UI"` / `"HumanGate Demo – Telegram"` → leave `triggerable = false` (internal demos).
- `"M1 Demo – AI Video Pipeline"` → `slug: tvc-pipeline`, `triggerable: true`, `nl_description: "Pipeline đầy đủ: prompt → script → scenes → refined prompts → images → review checkpoint."`, `param_schema: {"prompt": ["required","string","min:10"]}`.

**Edit `HumanGateDemoSeeder`:** emit catalog fields when seeding, so running the seeder once covers both document + metadata.

**Acceptance:** `Workflow::triggerable()->get()` returns exactly two rows after seeding; each has a non-empty `slug`, `nl_description`, and `param_schema`.

---

## Task 3 — Anthropic tool-use client

**Files:**
- Create: `backend/app/Services/Anthropic/AnthropicToolUseClient.php`
- Create: `backend/app/Services/Anthropic/ToolDefinition.php`
- Create: `backend/app/Services/Anthropic/ToolUseResult.php`
- Test: `backend/tests/Unit/Services/Anthropic/AnthropicToolUseClientTest.php`

Existing `AnthropicAdapter` only speaks plain text. The agent needs `tools` + `tool_choice` in the request and parsing of `tool_use` / `tool_result` content blocks across multi-turn rounds.

**`ToolDefinition`** (readonly): `name`, `description`, `inputSchema` (JSON Schema array).

**`ToolUseResult`** (readonly): `stopReason` (`end_turn` | `tool_use` | `max_tokens`), `toolCalls` (list of `{id, name, input}`), `textBlocks` (list of strings).

**`AnthropicToolUseClient`**
- Constructor: `apiKey`, `model` (default `claude-sonnet-4-6`), `maxTokens` (default 1024).
- `send(array $messages, string $systemPrompt, array $tools): ToolUseResult` — single round-trip.
- Caller (the agent) drives the loop: receives `ToolUseResult` with `stopReason === tool_use`, executes tools locally, appends a `tool_result` message, calls `send()` again until `stopReason === end_turn`.
- Use Laravel `Http::fake()` in tests for deterministic responses (happy path, tool-use path, error path). Do **not** call the real API from tests.

**Config:** `config/services.php` → `anthropic.api_key` from `env('ANTHROPIC_API_KEY')`.

**Acceptance:** faked HTTP responses produce correct `ToolUseResult`s; malformed responses throw descriptive exceptions; request payload includes `tools` array and `system` field.

---

## Task 4 — Agent tool contract + concrete tools

**Files:**
- Create: `backend/app/Services/TelegramAgent/AgentTool.php` (interface)
- Create: `backend/app/Services/TelegramAgent/AgentContext.php` (readonly: `chatId`, `userId`, `sessionId`, `botToken`)
- Create: `backend/app/Services/TelegramAgent/Tools/ListWorkflowsTool.php`
- Create: `backend/app/Services/TelegramAgent/Tools/RunWorkflowTool.php`
- Create: `backend/app/Services/TelegramAgent/Tools/GetRunStatusTool.php`
- Create: `backend/app/Services/TelegramAgent/Tools/CancelRunTool.php`
- Create: `backend/app/Services/TelegramAgent/Tools/ReplyTool.php`
- Tests: one per tool under `backend/tests/Unit/Services/TelegramAgent/Tools/`

**`AgentTool` interface:**
```php
interface AgentTool {
    public function definition(): ToolDefinition;
    public function execute(array $input, AgentContext $ctx): array; // returns tool_result content
}
```

**`ListWorkflowsTool`** — no input. Returns `Workflow::triggerable()` with `slug`, `nl_description`, `param_schema`. The agent calls this once per conversation; cache per request.

**`RunWorkflowTool`** — input `{slug, params}`. Validates `params` against the workflow's `param_schema` via Laravel `Validator`. On valid, injects `params` into the document (merged into the first node's config as `_agentParams`, plus a synthetic `_triggerPayload` for backward compat) and dispatches `RunWorkflowJob`. Returns `{runId, status}`. On invalid, returns `{error, fields}` so the agent can re-ask.

**`GetRunStatusTool`** — input `{runId}`. Returns status, current node, any `PendingInteraction` summary, last error.

**`CancelRunTool`** — input `{runId}`. Updates run to `cancelled`, returns `{runId, status}`.

**`ReplyTool`** — input `{text}`. Sends a plain Telegram message via `Http::post(...sendMessage...)`. Returns `{delivered: true}`. This is how the agent talks back to the user mid-loop; it's deliberately a tool (not an implicit return) so the agent can plan "reply then keep thinking".

**Acceptance:** each tool has a happy-path test with fake HTTP / in-memory models; validator errors surface as structured `tool_result` payloads, not exceptions.

---

## Task 5 — Agent session store

**Files:**
- Create: `backend/app/Services/TelegramAgent/AgentSession.php`
- Create: `backend/app/Services/TelegramAgent/AgentSessionStore.php`
- Test: `backend/tests/Unit/Services/TelegramAgent/AgentSessionStoreTest.php`

**Shape:** one session per `(chatId, botToken)`. Stored in Redis at `telegram_agent:{chatId}:{botToken}` with 1-hour TTL, refreshed on every interaction.

**`AgentSession`** (mutable-but-encapsulated, not readonly): `chatId`, `botToken`, `messages` (list of `{role, content}` Anthropic-format entries), `lastActiveAt`, `pendingWorkflowSlug` (nullable — what the agent is currently trying to run), `collectedParams` (array — accumulated inputs the agent has gathered across turns).

**`AgentSessionStore`:** `load(chatId, botToken): AgentSession`, `save(AgentSession): void`, `forget(chatId, botToken): void`.

**Retention policy:** trim `messages` to last 20 entries on save; drop anything older. This caps context growth over long chats.

**Acceptance:** round-trip serialization preserves all fields; `forget()` deletes the Redis key; trimming works at boundary.

---

## Task 6 — Slash command router

**Files:**
- Create: `backend/app/Services/TelegramAgent/SlashCommandRouter.php`
- Test: `backend/tests/Unit/Services/TelegramAgent/SlashCommandRouterTest.php`

Handles the hard-coded, no-LLM path. Commands:
- `/start` — welcome + list of triggerable workflows (reuses `ListWorkflowsTool`).
- `/help` — short usage.
- `/list` — list triggerable workflows.
- `/status [runId]` — no arg: list the caller's 5 most recent runs with status; with arg: detail for that run.
- `/cancel <runId>` — cancel a run owned by this chat (match via `ExecutionRun.trigger === 'telegramWebhook'` + stored chat id metadata).
- `/reset` — `AgentSessionStore::forget()`, reply "Session cleared".

Each command returns a plain text reply string; the router itself does not call Telegram — the caller does.

**Acceptance:** routing table is exhaustive; unknown command returns `null` (signals to caller "fall through to LLM"); argv parsing handles extra whitespace.

---

## Task 7 — TelegramAgent service

**Files:**
- Create: `backend/app/Services/TelegramAgent/TelegramAgent.php`
- Create: `backend/app/Services/TelegramAgent/SystemPrompt.php` (static provider for the system prompt)
- Test: `backend/tests/Feature/TelegramAgentTest.php`

**`TelegramAgent::handle(Update $update, string $botToken): void`**:

1. Extract `chatId`, `text`, `messageId`.
2. If `text` starts with `/`, delegate to `SlashCommandRouter`; send reply; return.
3. Load `AgentSession`. Append `{role: 'user', content: text}` to `messages`.
4. Run the tool-use loop:
   - Build `systemPrompt` + tool list (inject `AgentContext`).
   - Call `AnthropicToolUseClient::send($messages, $systemPrompt, $tools)`.
   - If `stopReason === 'tool_use'`: execute each `tool_use` locally, append `{role: 'assistant', content: [tool_use blocks]}` and `{role: 'user', content: [tool_result blocks]}` to `messages`, loop.
   - If `stopReason === 'end_turn'`: emit any remaining text via Telegram `sendMessage`, break.
   - Safety cap: **max 8 loop iterations**, then force `reply` with "Tôi bị lạc — gõ /reset nếu cần bắt đầu lại." and break.
5. `AgentSessionStore::save($session)`.

**`SystemPrompt::build(array $catalogPreview, string $chatId): string`** — Vietnamese-first prompt. Tells the agent:
- Its job (bridge between the user and workflows).
- To prefer `list_workflows` before guessing.
- To call `run_workflow` only once the `param_schema` is satisfied; otherwise ask the user for the missing fields via `reply`.
- To surface errors plainly, not with stack traces.
- To refuse unrelated requests politely.

**Feature test:** with `Http::fake()`ed Anthropic responses and a seeded catalog, simulate a user saying *"tạo kịch bản video chocopie"* — expect `list_workflows` → `run_workflow(slug=story-writer-gated, params={productBrief: "chocopie"})` → `reply` confirming run was queued; assert one `ExecutionRun` created with the right `workflow_id`.

**Acceptance:** feature test passes; loop cap is enforced; session persists across calls; reply tool calls actually hit the fake Telegram endpoint.

---

## Task 8 — Wire TelegramAgent into the webhook controller

**Files:**
- Edit: `backend/app/Http/Controllers/TelegramWebhookController.php`
- Delete: the `triggerWorkflow`, `findWorkflowByBotToken`, `bufferMessage`, `handleConfirmation`, `getSession`/`saveSession`/`deleteSession` intake helpers (and the `ProcessTelegramBatchJob` if unused elsewhere).
- Edit/Delete: `backend/app/Jobs/ProcessTelegramBatchJob.php` — remove if no callers remain.
- Edit: `backend/tests/Feature/TelegramWebhookControllerTest.php` (or create if missing).

Controller becomes:
1. Parse update.
2. Callback query? → existing handler.
3. Reply-to-pending gate? → existing handler.
4. Bare-text matching a single pending gate? → existing handler.
5. **Otherwise** → `resolve(TelegramAgent::class)->handle($update, $botToken)`.

Drop the "❌ Không tìm thấy workflow phù hợp" codepath entirely; the agent either runs something or politely explains.

**Acceptance:** existing gate-resume tests still pass; new controller routing test confirms non-gate messages land in the agent; legacy intake helpers are gone from the controller (`grep -n 'bufferMessage' …` returns nothing).

---

## Task 9 — Deprecate `telegramTrigger` (soft)

**Files:**
- Edit: `backend/app/Domain/Nodes/Templates/TelegramTriggerTemplate.php`
- Edit: `backend/app/Providers/NodeTemplateServiceProvider.php` (no-op — template stays registered)
- Edit: `backend/database/seeders/HumanGateDemoSeeder.php` — leave existing `telegramTrigger` seeds alone.

Mark `TelegramTriggerTemplate` with a class-level `@deprecated` docblock pointing at `WorkflowCatalog`. Keep runtime behavior: the agent's `RunWorkflowTool` still injects `_triggerPayload` into any `telegramTrigger` node in the target document, so legacy workflows keep working.

**Acceptance:** existing `TelegramTriggerTemplateTest` still passes; grep for `@deprecated` on the class.

---

## Task 10 — End-to-end smoke test

**Files:**
- Create: `backend/tests/Feature/TelegramAgentEndToEndTest.php`

Single integration test that exercises the full path with `Http::fake()` for both Anthropic and Telegram:
1. Seed the catalog.
2. POST `/api/telegram/webhook/{botToken}` with `{"message": {"chat": {"id": "123"}, "text": "viết kịch bản cho chocopie"}}`.
3. Assert the agent called Anthropic with the seeded catalog in its system prompt.
4. Assert `ExecutionRun` for `story-writer-gated` was created with the expected params.
5. Assert a Telegram `sendMessage` was called with a confirmation.
6. Second POST with `/status` — assert the reply contains the runId from step 4.

**Acceptance:** test runs under 2 s; no real network calls.

---

## Non-goals / follow-ups

- **Webhook-per-workflow.** If a workflow genuinely needs its own dedicated bot, the `telegramTrigger` node still works — the agent path just doesn't apply.
- **Vietnamese NLU nuance.** The system prompt handles language detection; heavy NLU (slang, multi-turn disambiguation beyond param collection) is follow-up.
- **Agent configurability in UI.** Editing `nl_description` / `param_schema` via the canvas inspector — follow-up epic.
- **Rate limiting / cost control.** Anthropic token budget per chat per day — follow-up.
