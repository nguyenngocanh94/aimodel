# AGENTS.md — Laravel Backend

> Read this file before working on any backend code.

## Tech Stack

- **PHP 8.4** + **Laravel 11**
- **PostgreSQL** via Eloquent ORM
- **Redis** for caching and conversation memory
- **Queue** (Laravel Horizon / `workflow-runs` queue) for async workflow execution
- **LLM layer:** `laravel/ai` (first-party). Providers configured in `config/ai.php`; default selected by `AI_DEFAULT_PROVIDER` env (falls back to `fireworks`). `TelegramAgent` is a `Laravel\Ai\Contracts\Agent` with `Promptable` + `RemembersConversations`; conversation memory is Redis-backed via `RedisConversationStore`. Tools implement `Laravel\Ai\Contracts\Tool`. Do NOT reintroduce custom LLM clients.
- **Driver note:** `laravel/ai`'s built-in `openai` driver targets the OpenAI Responses API (`/responses`), which is incompatible with Fireworks and other OpenAI-compatible Chat Completions providers. Use the `groq` driver with a custom `url` for any provider that exposes `/chat/completions`.

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

## References

- Migration plan: `docs/plans/2026-04-18-migrate-to-laravel-ai.md`
- laravel/ai docs: `vendor/laravel/ai/`
