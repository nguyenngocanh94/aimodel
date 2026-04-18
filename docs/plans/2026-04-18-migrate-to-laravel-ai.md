# Migrate TelegramAgent LLM layer to `laravel/ai`

> **For Claude:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Goal:** Replace our hand-rolled LLM stack (`AnthropicToolUseClient`, `FireworksToolUseClient`, `ToolUseClientContract`, `ToolDefinition`, `ToolUseResult` — ~800 LOC) with the first-party `laravel/ai` package. Fireworks slots in via the built-in OpenAI driver with a custom `url`, no custom provider code. The `RemembersConversations` trait replaces our `AgentSessionStore`, and the whole `tool_use`/`tool_result` pairing invariant becomes the framework's problem.

**Foundation already landed.** The previous commit swapped Prism for `laravel/ai v0.6.0`, published `config/ai.php`, and wired a `fireworks` provider using the openai driver + `https://api.fireworks.ai/inference/v1`. Live round-trip via `Laravel\Ai\AnonymousAgent` returned `"OK"` from `accounts/fireworks/models/minimax-m2p7`. Remaining work is rewriting `TelegramAgent` + its five tools on top of `laravel/ai`'s contracts.

**Architecture after this migration.**
- `TelegramAgent` becomes a `Laravel\Ai\Contracts\Agent` (via extending the package's base / using the `Promptable` trait). Its `handle(array $update, string $botToken): void` entry point still exists, but internally it just calls `$this->prompt($text, provider: config('ai.default'))` and streams the final text back to Telegram.
- Our five tools (`ListWorkflowsTool`, `RunWorkflowTool`, `GetRunStatusTool`, `CancelRunTool`, `ReplyTool`) become `Laravel\Ai\Contracts\Tool` implementations — each with `description()`, `handle(Tools\Request)`, and `schema(JsonSchema)`.
- `AgentSession` + `AgentSessionStore` are replaced by the package's `Concerns\RemembersConversations` trait + `Contracts\ConversationStore`. We keep Redis as the backing store by implementing a thin `ConversationStore` over Redis (or using whatever the package ships by default — prefer that).
- Custom client classes, translator logic, orphan-tool-result defensive code, and their tests are all deleted.

**Tech Stack:** PHP 8.4, Laravel 11, `laravel/ai ^0.6.0`, existing catalog / `PendingInteraction` / webhook controller infrastructure. PHPUnit 11.

**Non-goals.** Streaming replies (package supports it; we defer until after the bot's stable), structured-output mode, image/audio/embeddings capabilities, the `Tools\WebSearch`/`FileSearch`/`WebFetch` built-ins. This is a mechanical migration, not a feature expansion.

---

## LA1 — Rewrite the five tools as `Laravel\Ai\Contracts\Tool`

**Files:**
- Rewrite: `backend/app/Services/TelegramAgent/Tools/ListWorkflowsTool.php`
- Rewrite: `backend/app/Services/TelegramAgent/Tools/RunWorkflowTool.php`
- Rewrite: `backend/app/Services/TelegramAgent/Tools/GetRunStatusTool.php`
- Rewrite: `backend/app/Services/TelegramAgent/Tools/CancelRunTool.php`
- Rewrite: `backend/app/Services/TelegramAgent/Tools/ReplyTool.php`
- Delete: `backend/app/Services/TelegramAgent/AgentTool.php`, `AgentContext.php` (if unreachable after the rewrite — keep `AgentContext` if the tools still need chat/bot id injection; see below)
- Rewrite each existing tool test.

**Read first:**
- `vendor/laravel/ai/src/Contracts/Tool.php` — the three methods you must implement.
- `vendor/laravel/ai/src/Tools/Request.php` — what `handle()` receives. It exposes tool arguments and the enclosing agent/invocation context.
- `vendor/laravel/ai/src/Tools/SimilaritySearch.php` — a working in-repo example to copy shape from.
- `vendor/laravel/ai/src/Contracts/Schemable.php` + how `schema(JsonSchema $schema): array` is used.
- Look for `Request` → how to access the active `Agent`/chat-id. If there's no direct access, the tool receives whatever the agent exposes on itself; see LA2.

**Per-tool translation:**

| Old method | New method | Notes |
|---|---|---|
| `AgentTool::definition()` | `description(): Stringable\|string` | Plain string/Stringable; no name/input-schema. |
| — | `schema(JsonSchema $schema): array<string, Type>` | Re-express the old JSON-Schema properties via `$schema->string('field')->description('...')`, `$schema->object('params')->properties([...])`, etc. |
| `AgentTool::execute(array $input, AgentContext $ctx): array` | `handle(Request $request): Stringable\|string` | Pull typed inputs off `$request` (exact accessor names: read the source). Return a JSON-encoded string (`Stringable` with `__toString` returning JSON) so structured data survives. |

Tool *name* in `laravel/ai` is derived from class name by default; verify this by reading how Agents enumerate tools. If the package exposes a `name()` method on the contract we're missing above, add it with the existing slugs (`list_workflows`, `run_workflow`, ...).

**Access to chat state (bot token, chat id) inside tools:**
- `ReplyTool` needs bot token + chat id to hit `api.telegram.org/sendMessage`.
- `RunWorkflowTool` uses chat id when synthesizing the `_triggerPayload`.

Options (pick whichever the package makes idiomatic — verify in the source):
1. **Agent-scoped constructor args.** If tools are re-instantiated per-prompt, the Agent can construct them with chat context: `new ReplyTool(botToken: $this->botToken, chatId: $this->chatId)`.
2. **Context bag on the Request.** If `Tools\Request` exposes the owning agent or a context bag, the tool pulls chat id from there.
3. **A small `AgentContext` singleton** bound per-invocation in the container and resolved inside each tool's `handle()`.

Default to (1) — it's the simplest and matches how Laravel AI's own examples look. If the tool set needs per-invocation rebinding (a new chat per webhook hit), the Agent class spins up a fresh tool list in its `tools()` method each time.

**Tests.** Rewrite each tool's test file. Hit the public contract: construct the tool, call `schema($schema)` and assert the returned Type tree, then drive `handle($request)` with a crafted `Tools\Request` (instantiate directly or via a test double) and assert the side effects (DB row for RunWorkflowTool, `Http::assertSent` for ReplyTool, etc.).

**Acceptance:** all five tool tests green. No compile errors. No reference to `AgentTool`, `AgentContext` (unless preserved), `ToolDefinition`.

---

## LA2 — Rewrite `TelegramAgent` as a `Laravel\Ai\Contracts\Agent`

**Files:**
- Rewrite: `backend/app/Services/TelegramAgent/TelegramAgent.php`
- Rewrite: `backend/app/Services/TelegramAgent/SystemPrompt.php` (or merge into the agent's `instructions()`)
- Edit: `backend/app/Providers/TelegramAgentServiceProvider.php`
- Delete: `backend/app/Services/TelegramAgent/AgentSession.php`, `AgentSessionStore.php`, `HandlesTelegramUpdate.php` if the controller can now type-hint `TelegramAgent` directly, `AgentSessionTrimTest`, `AgentSessionStoreTest`.
- Keep intact: `SlashCommandRouter` — still useful for the no-LLM short-circuit.

**Read first:**
- `vendor/laravel/ai/src/Contracts/Agent.php`
- `vendor/laravel/ai/src/Promptable.php` (the trait providing `prompt()`/`stream()`/`queue()`/`broadcast*()`).
- `vendor/laravel/ai/src/AnonymousAgent.php` — a minimal Agent example (already verified to work with Fireworks).
- `vendor/laravel/ai/src/Concerns/RemembersConversations.php` — replaces AgentSessionStore. Trace the `Contracts\ConversationStore` it expects.
- `vendor/laravel/ai/src/Contracts/Conversational.php`
- `vendor/laravel/ai/src/Responses/AgentResponse.php` — the `->text`, `->messages`, `->toolCalls`, `->toolResults`, `->steps`, `->usage`, `->meta` public properties.
- `vendor/laravel/ai/src/Contracts/HasTools.php` — the `tools(): iterable` method contract.

**`TelegramAgent` shape:**

```php
final class TelegramAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations; // owns conversation memory per (chatId, botToken)

    public function __construct(
        public string $chatId,
        public string $botToken,
        private SlashCommandRouter $slashRouter,
    ) {}

    public function instructions(): string
    {
        $catalog = Workflow::triggerable()->get(['slug','name','nl_description','param_schema'])->toArray();
        return SystemPrompt::build($catalog, $this->chatId);
    }

    public function tools(): iterable
    {
        return [
            new ListWorkflowsTool(),
            new RunWorkflowTool(chatId: $this->chatId),
            new GetRunStatusTool(),
            new CancelRunTool(),
            new ReplyTool(botToken: $this->botToken, chatId: $this->chatId),
        ];
    }

    /** Entry point from the webhook controller. */
    public function handle(array $update, string $botToken): void
    {
        $this->chatId   = (string) data_get($update, 'message.chat.id');
        $this->botToken = $botToken;
        $text           = data_get($update, 'message.text', data_get($update, 'message.caption', ''));

        if ($text === '') return;

        if (str_starts_with($text, '/')) {
            $reply = $this->slashRouter->route($text, $this->chatId);
            if ($reply === '🔄 Session reset. (Storage cleared by caller.)') {
                $this->forgetConversation();          // whatever RemembersConversations exposes
                $reply = "🔄 Session cleared.";
            }
            $this->sendTelegram($reply);
            return;
        }

        $response = $this->prompt($text, provider: config('ai.default'));

        if ($response->text !== '' && ! $this->replyToolHandledOutput($response)) {
            $this->sendTelegram($response->text);
        }
    }

    // sendTelegram(...) helper, replyToolHandledOutput(...) helper, etc.
}
```

(Exact APIs — `forgetConversation()`, how `$this->chatId` reaches `RemembersConversations` as the conversation key, where the step cap lives — depend on the trait's shape. Read it before committing to the above.)

**Conversation memory:**
- `RemembersConversations` ships with a default `ConversationStore`. Verify whether that's Redis-backed or session-backed; inspect `config/ai.php` for any caching-related knobs. If Redis isn't the default, implement a 40-line `RedisConversationStore implements ConversationStore` writing to `telegram_agent:{chatId}:{botToken}`.
- Conversation-trimming that caused our T-fixed orphan-tool-result bug is now handled by the package.

**Service provider:**
- Bind `TelegramAgent::class` as **not a singleton** — it's per-request state (chatId/botToken live on it). Either rebuild on each controller hit or move to a factory.
- Drop the old `ToolUseClientContract` / `HandlesTelegramUpdate` bindings unless the webhook controller test suite still mocks them.

**Acceptance:**
- `TelegramAgent` instantiates cleanly for a given chat/bot pair.
- `handle()` routes slash commands through `SlashCommandRouter` (no LLM call), and free text through `$this->prompt($text)`.
- Integration smoke: invoke `TelegramAgent::handle()` with a fake update against live Fireworks (same as the last smoke script we used), assert ExecutionRun created + Telegram sendMessage fired.

---

## LA3 — Wire TelegramAgent into the webhook controller

**Files:**
- Edit: `backend/app/Http/Controllers/TelegramWebhookController.php`
- Edit/Delete: `backend/tests/Feature/TelegramWebhookAgentRoutingTest.php` (rewrite to mock the new `TelegramAgent`)

**Shape:** The controller's gate-resume priorities (0, 1, 1b, 1c) stay unchanged. The "fall through to agent" branch now constructs a per-request `TelegramAgent`:

```php
$agent = new TelegramAgent(
    chatId: (string) data_get($update, 'message.chat.id'),
    botToken: $botToken,
    slashRouter: $this->slashRouter,
);
$agent->handle($update, $botToken);
```

No container binding needed. If the controller is currently constructor-injected with `HandlesTelegramUpdate`, replace that with a lighter injection of `SlashCommandRouter` (or accept `TelegramAgent` as a transient request-scoped type) and build the agent inside the action.

**Tests:** the existing `TelegramWebhookAgentRoutingTest`'s five cases still make sense; update them to mock a fresh `TelegramAgent` (use `Mockery::mock(TelegramAgent::class)` and container-bind it, or pass in via a factory closure).

**Acceptance:** `docker exec backend-app-1 php artisan test --filter=TelegramWebhookAgentRoutingTest` green. No references to `HandlesTelegramUpdate` remain.

---

## LA4 — Delete the old LLM client stack and the Anthropic-shaped session

**Files to delete:**
- `backend/app/Services/Anthropic/AnthropicToolUseClient.php`
- `backend/app/Services/Anthropic/ToolUseClientContract.php`
- `backend/app/Services/Anthropic/ToolDefinition.php`
- `backend/app/Services/Anthropic/ToolUseResult.php`
- `backend/app/Services/Fireworks/FireworksToolUseClient.php`
- `backend/tests/Unit/Services/Anthropic/AnthropicToolUseClientTest.php`
- `backend/tests/Unit/Services/Fireworks/FireworksToolUseClientTest.php`
- `backend/app/Services/TelegramAgent/AgentTool.php`
- `backend/app/Services/TelegramAgent/AgentContext.php` (if LA1 decided the new tools don't need it)
- `backend/app/Services/TelegramAgent/AgentSession.php`
- `backend/app/Services/TelegramAgent/AgentSessionStore.php`
- `backend/app/Services/TelegramAgent/HandlesTelegramUpdate.php`
- `backend/tests/Unit/Services/TelegramAgent/AgentSessionStoreTest.php`
- `backend/tests/Unit/Services/TelegramAgent/AgentSessionTrimTest.php`
- `backend/config/services.php` block for `fireworks` / `anthropic` — only if nothing else references them.

**Guardrails before each delete:** `grep -rn "<ClassName>" backend/app backend/tests backend/config` must return zero hits outside files being deleted in this task.

**Acceptance:** grep of the old class names returns empty. Full test sweep green for the kept suite.

---

## LA5 — Live smoke + docs + close the epic

**Files:**
- Edit: `docs/plans/2026-04-18-migrate-to-laravel-ai.md` — add "Done — results" footer with final line counts + live smoke transcript.
- Edit: `AGENTS.md` — one-paragraph note under Tech Stack pointing future LLM work at `laravel/ai`.

**Steps:**
1. `docker restart backend-worker-1 backend-app-1`.
2. `docker exec backend-app-1 composer dump-autoload -o`.
3. Run `docker exec backend-app-1 php artisan test --filter="TelegramAgentTest|TelegramWebhookAgentRoutingTest|TelegramAgentEndToEndTest|ListWorkflowsToolTest|RunWorkflowToolTest|GetRunStatusToolTest|CancelRunToolTest|ReplyToolTest|SlashCommandRouterTest|SystemPromptTest|WorkflowCatalogTest|WorkflowCatalogSeederTest"`. Must be green.
4. Write a disposable smoke script at `backend/storage/app/la_smoke.php` that invokes `TelegramAgent::handle()` with a fake update for the seeded `story-writer-gated` workflow against live Fireworks. Confirm:
   - ExecutionRun created.
   - `RunWorkflowJob` dispatched.
   - `PendingInteraction` written once storyWriter's gate fires.
   - At least one Telegram `sendMessage` fired.
   Delete the smoke script before closing the bead.
5. Commit `docs(agent-bridge): record laravel/ai migration results (LA5)`.

**Acceptance:**
- Full live smoke passes ≤ 15 s end-to-end.
- `git diff --stat main~<N>..main -- 'backend/app/Services/Anthropic' 'backend/app/Services/Fireworks'` shows deletions only.
- `bd close <epic-id>`.

---

## Dependency order

```
LA1 (tools) ─┐
             ├─► LA2 (agent) ─► LA3 (webhook) ─► LA4 (delete old stack) ─► LA5 (smoke + docs)
             └─── (independent; can proceed in parallel with LA2's read-only planning)
```

LA1 and LA2 can run truly parallel if LA2's agent implementer is willing to read LA1's freshly-written tool contract from the branch.
