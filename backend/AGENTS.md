# AGENTS.md — Laravel Backend

> Read this file before working on any backend code.

## Tech Stack

- **PHP 8.4** + **Laravel 11**
- **PostgreSQL** via Eloquent ORM
- **Redis** for caching and conversation memory
- **Queue** (Laravel Horizon / `workflow-runs` queue) for async workflow execution
- **LLM layer:** `laravel/ai` (first-party). Providers configured in `config/ai.php`; default selected by `AI_DEFAULT_PROVIDER` env (falls back to `fireworks`). `TelegramAgent` is a `Laravel\Ai\Contracts\Agent` with `Promptable` + `RemembersConversations`; conversation memory is Redis-backed via `RedisConversationStore`. Tools implement `Laravel\Ai\Contracts\Tool`. Do NOT reintroduce custom LLM clients.
- **Driver note:** `laravel/ai`'s built-in `openai` driver targets the OpenAI Responses API (`/responses`), which is incompatible with Fireworks and other OpenAI-compatible Chat Completions providers. Use the `groq` driver with a custom `url` for any provider that exposes `/chat/completions`.
- **Structured output:** schema-bound nodes (planner, StoryWriter, ScriptWriter, SceneSplitter, PromptRefiner, TrendResearcher, ProductAnalyzer, SubtitleFormatter, ImageAssetMapper, TtsVoiceoverPlanner) emit JSON via `InteractsWithLlm::callStructuredText()` + a schema closure. The gateway enforces the schema — no fence-stripping, no `json_decode` ladders.
- **Prompt caching (Anthropic-only):** `App\Domain\Planner\WorkflowPlannerAgent` and `App\Domain\Nodes\CachedStructuredAgent` implement `Laravel\Ai\Contracts\HasProviderOptions` and tag the system prompt with `cache_control: ephemeral` when the active provider is `anthropic`. Minimum ~1024 tokens, 5-minute TTL (refreshed on hit). Non-Anthropic providers get `[]` — plain `system` string path. Fireworks (`groq` driver) does not support caching today.
- **Image/audio/video:** image goes through `InteractsWithImage` → `Ai::provider($name)->image(...)` for `openai|gemini|xai`, otherwise a narrow `FalClient/ReplicateClient/DashScopeClient` under `App\Services\MediaProviders\`. Video uses `InteractsWithVideo`; audio uses `InteractsWithAudio`.

## Key Directories

```
app/
  Domain/           # Pure domain logic (execution engine, run status, human loop)
  Http/Controllers/ # Webhook controllers (Telegram, etc.)
  Jobs/             # Queued jobs (RunWorkflowJob)
  Models/           # Eloquent models (Workflow, ExecutionRun, PendingInteraction, ...)
  Providers/        # Service providers
  Services/
    TelegramAgent/  # TelegramAgent + SlashCommandRouter + 5 laravel/ai Tools
config/
  ai.php            # laravel/ai provider config
database/
  migrations/       # Schema migrations
  seeders/          # WorkflowCatalogSeeder, HumanGateDemoSeeder, DemoWorkflowSeeder
tests/
  Feature/          # Feature/integration tests (Telegram, Webhook routing)
  Unit/             # Unit tests (tools, domain logic, models)
```

## Running Commands

All PHP/Artisan commands run inside Docker:

```bash
docker exec backend-app-1 php artisan <command>
docker exec backend-app-1 php artisan test --filter=<TestClass>
docker exec backend-app-1 composer dump-autoload -o
docker exec backend-app-1 php artisan config:clear
```

## Non-Negotiable Rules

1. Do NOT reintroduce custom LLM client code. All LLM calls go through `laravel/ai`.
2. Do NOT make `TelegramAgent` a singleton — it is per-request (chatId/botToken live on it).
3. Tools must implement `Laravel\Ai\Contracts\Tool` (three methods: `description()`, `schema()`, `handle()`).
4. Conversation memory is keyed on `"{chatId}:{botToken}"` via `RedisConversationStore`.
5. The `workflow-runs` queue must remain separate from the default queue.

### Conversational workflow composition

Users can create new workflows via Telegram chat. Example:

> User: tạo workflow sinh video TVC 9:16 chăm sóc sức khỏe
> Bot: [drafts plan via ComposeWorkflowTool, explains in Vietnamese]
> User: chỉnh: thêm humor nhẹ
> Bot: [re-plans via RefinePlanTool]
> User: ok
> Bot: ✅ Đã lưu workflow health-tvc-9x16.

Three tools chain the flow (under `App\Services\TelegramAgent\Tools\`):
`ComposeWorkflowTool` drafts via `WorkflowPlanner`, `RefinePlanTool` iterates
on user feedback (max 5 per session), `PersistWorkflowTool` commits the plan
as a triggerable `Workflow` row on approval. The skill driving this
(`ComposeWorkflowSkill`) encodes approval/refinement/rejection vocabulary
so the LLM can dispatch correctly. Plan state lives in
`AgentSession::pendingPlan` (Redis, namespace `ai_session:`).

Never persist without explicit approval ("ok", "đồng ý", "chốt", …).
Never run `PersistWorkflowTool` from any context that isn't a direct
response to user approval in the same conversation turn.

## References

- Migration plan: `docs/plans/2026-04-18-migrate-to-laravel-ai.md`
- laravel/ai docs: `vendor/laravel/ai/`
