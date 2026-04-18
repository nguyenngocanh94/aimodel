# Migrate TelegramAgent LLM layer to Prism

> **For Claude:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Goal:** Replace our hand-rolled `AnthropicToolUseClient` + `FireworksToolUseClient` + `ToolUseClientContract` + `ToolDefinition` + `ToolUseResult` stack with the `prism-php/prism` library. Prism provides unified tool-use across Anthropic, OpenAI, Fireworks (via OpenAI-compatible), Gemini, Groq, Mistral, and more — and handles `tool_use`/`tool_result` pairing, multi-turn stepping, streaming, retries, and message serialization internally.

**Why now:** the custom code grew to ~800 LOC and one real bug (orphan `tool_result` after naive trim → Fireworks 400). Prism's internal invariants eliminate the whole class of problems and let us swap providers with a single env/config flip — no translator needed.

**Out of scope:** streaming replies to the user, structured-output mode, Prism's RAG / memory helpers. This is a mechanical migration; new capabilities come in a follow-up epic.

**Architecture.** The `TelegramAgent`'s shape stays the same: slash-command short-circuit → LLM loop → session persist. What changes: the `llm` dependency becomes Prism's `Prism::text()` fluent builder; the five `AgentTool` classes stay but get a thin `toPrismTool()` adapter; `AgentSession` stores Prism message objects instead of Anthropic-shaped arrays; provider selection moves from our `match()` in the service provider to `config/prism.php`. Fireworks speaks OpenAI-compat, so Prism's `openai` driver with a custom `url` covers it.

**Tech Stack:** PHP 8.4, Laravel 11, `prism-php/prism` (add), PHPUnit 11, existing `PendingInteraction` / `RunWorkflowJob` / Telegram webhook infrastructure.

---

## Task 1 — Install Prism + provider config

**Files:**
- Edit: `backend/composer.json`, `backend/composer.lock`.
- Create: `backend/config/prism.php` (publish Prism's config).
- Edit: `backend/config/services.php` — drop `services.telegram_agent.provider` (Prism handles it).
- Edit: `backend/.env.example` — add `PRISM_PROVIDER` / `PRISM_MODEL` guidance.

**Steps:**
1. `docker exec backend-app-1 composer require prism-php/prism`.
2. Publish the Prism config: `docker exec backend-app-1 php artisan vendor:publish --tag=prism-config` — produces `config/prism.php`. If no such tag exists, write the config by hand using Prism's documented schema.
3. In `config/prism.php`, configure named providers:
   - `'anthropic'` → api_key from `ANTHROPIC_API_KEY`, default model `claude-sonnet-4-6`.
   - `'fireworks'` → use Prism's `openai` driver with `url: https://api.fireworks.ai/inference/v1`, api_key from `FIREWORKS_API_KEY`, default model from `FIREWORKS_MODEL` (currently `accounts/fireworks/models/minimax-m2p7`).
4. Add `PRISM_PROVIDER` env (default `fireworks`) and `PRISM_MODEL` env (optional override).

**Acceptance:**
- `composer show prism-php/prism` inside the container returns a version.
- `config/prism.php` exists with both providers wired.
- `docker exec backend-app-1 php artisan config:show prism` shows the merged config.

**Finish protocol:** commit `chore(agent-bridge): add prism-php/prism dependency (T1)`.

---

## Task 2 — Prism tool adapter

**Files:**
- Create: `backend/app/Services/TelegramAgent/PrismToolAdapter.php`
- Test: `backend/tests/Unit/Services/TelegramAgent/PrismToolAdapterTest.php`

Prism uses its own `Prism\Prism\Tool` class with a fluent builder. Our existing five tools (`ListWorkflowsTool`, `RunWorkflowTool`, `GetRunStatusTool`, `CancelRunTool`, `ReplyTool`) already implement `AgentTool::definition() + execute()`. Keep those classes unchanged; wrap them via an adapter.

**Adapter:**
```php
class PrismToolAdapter {
    public static function wrap(AgentTool $tool, AgentContext $ctx): \Prism\Prism\Tool { … }
}
```

Responsibilities:
1. Read `AgentTool::definition()` → pull `name`, `description`, `inputSchema` (JSON Schema object).
2. Build a Prism `Tool::as($name)->for($description)` call.
3. Translate the input schema's `properties` into Prism's parameter declarations (`withStringParameter`, `withNumberParameter`, `withObjectParameter`, etc.). Walk the top-level properties; for object-typed fields pass through the nested schema.
4. The Prism `->using(fn(...$args) => ...)` callback should call `$tool->execute($inputArray, $ctx)` and return the tool-result payload (Prism accepts arrays or strings; we return JSON-encoded strings to preserve structure).

**Test:** for each of the five tools, assert `PrismToolAdapter::wrap()` produces a Prism `Tool` whose name/description match the `AgentTool`'s definition, and that invoking the Prism tool with a sample input reaches our `AgentTool::execute()` with the right shape. Use `AgentContext` as a plain fixture.

**Acceptance:**
- Unit test green: `docker exec backend-app-1 php artisan test --filter=PrismToolAdapterTest`.
- No changes to the five `AgentTool` classes or their existing tests.

---

## Task 3 — Rewrite TelegramAgent on Prism

**Files:**
- Edit (heavy): `backend/app/Services/TelegramAgent/TelegramAgent.php`
- Edit: `backend/app/Services/TelegramAgent/AgentSession.php`
- Edit: `backend/app/Providers/TelegramAgentServiceProvider.php`
- Keep intact: `AgentSessionStore`, `SlashCommandRouter`, `SystemPrompt`, `HandlesTelegramUpdate`, all five `Tools/*`.

**New `TelegramAgent` flow:**
```
public function handle(array $update, string $botToken): void {
    $chatId / userId / text = extract(update);
    if (text === '') return;

    if (starts_with(text, '/')) { … slash router as before … }

    $session = $sessionStore->load(chatId, botToken);
    $ctx     = new AgentContext(...);

    $tools = array_map(
        fn(AgentTool $t) => PrismToolAdapter::wrap($t, $ctx),
        $this->tools
    );

    $catalogPreview = Workflow::triggerable()->get([...])->toArray();
    $systemPrompt   = SystemPrompt::build($catalogPreview, $chatId);

    // Load Prism-shaped history from session, add the new user turn
    $messages   = $session->prismMessages();
    $messages[] = new UserMessage($text);

    $response = Prism::text()
        ->using(config('prism.default_provider'), $model)
        ->withSystemPrompt($systemPrompt)
        ->withTools($tools)
        ->withMessages($messages)
        ->withMaxSteps(self::MAX_ITERATIONS)
        ->asText();

    // Emit final text to Telegram if non-empty and not already sent by ReplyTool
    if ($response->text !== '') {
        $this->sendTelegramMessage(botToken, chatId, $response->text);
    }

    // Persist the *full* updated message list (Prism appends assistant/tool turns)
    $session->setPrismMessages($response->messages);
    $sessionStore->save($session);
}
```

**`AgentSession` changes:**
- Replace the free-form `array $messages` with a typed Prism message store. Add:
  - `array $messagesSerialized` (Prism messages as primitive arrays, suitable for JSON/Redis).
  - `prismMessages(): array` — hydrate serialized entries back into Prism message objects.
  - `setPrismMessages(array $messages): void` — serialize Prism messages into `messagesSerialized`.
- `trimMessages($max = 20)` simplified: tail-slice on `messagesSerialized`. The orphan-tool-result concern is gone because Prism trims at well-formed boundaries — but keep the existing smart-trim test as a regression. If Prism's own trim respects pairings, document that; if not, port the forward-walk guard.

**Service provider:**
- Remove the `ToolUseClientContract` binding.
- `TelegramAgent` constructor no longer takes an `$llm`. It depends only on `AgentSessionStore`, `SlashCommandRouter`, `tools`.
- Prism is resolved statically via its facade/singleton registered by the Prism service provider.

**Acceptance:**
- `docker exec backend-app-1 php artisan test --filter=TelegramAgentTest` green (rewritten — see Task 5).
- No references to `ToolUseClientContract`, `ToolUseResult`, or the old client classes remain outside their own files (which will be deleted in Task 4).

---

## Task 4 — Delete the custom LLM client layer

**Files to delete:**
- `backend/app/Services/Anthropic/AnthropicToolUseClient.php`
- `backend/app/Services/Anthropic/ToolUseClientContract.php`
- `backend/app/Services/Anthropic/ToolDefinition.php`
- `backend/app/Services/Anthropic/ToolUseResult.php`
- `backend/app/Services/Fireworks/FireworksToolUseClient.php`
- `backend/tests/Unit/Services/Anthropic/AnthropicToolUseClientTest.php`
- `backend/tests/Unit/Services/Fireworks/FireworksToolUseClientTest.php`

**Guardrails:**
- Before deletion, `grep -rn "AnthropicToolUseClient\|ToolUseClientContract\|FireworksToolUseClient\|ToolUseResult\|Services\\\\Anthropic\\\\ToolDefinition" backend/app backend/tests` must return **only** references inside files that are about to be deleted (or already updated in Task 3).
- If the `ToolDefinition` name is still used by the five tool classes' `AgentTool::definition()` return type, **either** (a) change the interface to return a Prism `Tool` directly (more invasive) or (b) keep `ToolDefinition` as a lightweight neutral value object not under `Services\Anthropic`. Pick (b): move `ToolDefinition` to `App\Services\TelegramAgent\ToolDefinition`. Update the five tool classes and `AgentTool` interface accordingly. Update `PrismToolAdapter` to read from the new location.

**Acceptance:**
- The grep above returns nothing.
- Full test suite for remaining agent tests passes: `docker exec backend-app-1 php artisan test --filter="TelegramAgentTest|TelegramAgentEndToEndTest|TelegramWebhookAgentRoutingTest|SystemPromptTest|AgentSessionStoreTest|AgentSessionTrimTest|SlashCommandRouterTest|ListWorkflowsToolTest|RunWorkflowToolTest|GetRunStatusToolTest|CancelRunToolTest|ReplyToolTest|PrismToolAdapterTest"`.

---

## Task 5 — Rewrite agent feature tests against Prism's fake

**Files:**
- Rewrite: `backend/tests/Feature/TelegramAgentTest.php`
- Rewrite: `backend/tests/Feature/TelegramAgentEndToEndTest.php`

Prism ships a test harness — `Prism::fake($responses)` or `PrismServer::fake([...])` (exact API depends on the Prism version; read the docs). Replace every `Http::fake(['api.fireworks.ai/*' => Http::sequence()…])` scaffold with the Prism fake:
- Queue a `TextResponseFake` for each expected model round.
- Queue `ToolCallResponseFake` (name + args) for tool-use rounds.
- Assert the tools were invoked with the expected inputs.

The six cases from T7 stay: slash path / tool-use happy path / loop cap / session persistence / empty-text noop / unknown-slash. The E2E test keeps its two acts (NL intent + `/status`).

**Acceptance:**
- All six `TelegramAgentTest` cases green.
- E2E passes in < 2s using Prism fakes, no real network.

---

## Task 6 — Live smoke + docs

**Files:**
- Edit: `docs/plans/2026-04-18-migrate-to-prism.md` — add a "Done — results" footer.
- Add/Edit: one-paragraph note in `AGENTS.md` under "Tech Stack" pointing new readers at Prism for any LLM work.

**Steps:**
1. `docker exec backend-app-1 php artisan config:clear`.
2. Run the full relevant test filter (same list as Task 4 plus the rewritten feature tests).
3. Fire a live smoke request at the local webhook with a real Fireworks call and confirm the agent still creates an `ExecutionRun`, dispatches `RunWorkflowJob`, and `PendingInteraction` is written. Clean up any smoke script.
4. Commit with message `docs: record Prism migration results`.

**Acceptance:**
- Live smoke against real Fireworks returns a final agent reply within ~15 s.
- `git diff --stat HEAD~<N>..HEAD -- 'backend/app/Services/Anthropic' 'backend/app/Services/Fireworks'` shows only deletions.

---

## Dependency order

```
T1 (install) ───┬─► T2 (adapter) ─┐
                │                 ├─► T3 (rewrite agent) ─► T4 (delete old code) ─► T5 (rewrite tests) ─► T6 (smoke + docs)
                └─────────────────┘
```

T1 is strictly first. T2 can proceed in parallel with T3's planning but not its edits (T3 consumes T2). T4 and T5 can partially overlap but T4 must complete before T5's final run. T6 closes the loop.
