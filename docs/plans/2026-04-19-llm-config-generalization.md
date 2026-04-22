# Generalize LLM calling via `InteractsWithLlm` trait

> **For Claude:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Goal:** Stop duplicating `{provider, apiKey, model}` on every LLM-calling node template. Centralize LLM credential + routing config in `config/ai.php` (which already reads from `.env` via the `laravel/ai` setup). Per-node config shrinks to an optional `llm.provider` / `llm.model` nested block — both default to empty, resolving to `config('ai.default')` and the provider's default model. **No API keys in node configs, ever.**

**Why now:** the `laravel/ai` migration already centralized provider config. Node configs still carry stale `apiKey: ''` fields from the pre-laravel/ai era, duplicating what `config/ai.php` owns. This epic finishes the centralization so adding a new LLM-capable node is one trait + zero credentials.

**Scope boundary:** this epic covers **text-generation** nodes only (`storyWriter`, `scriptWriter`, `sceneSplitter`, `promptRefiner`, `trendResearcher`, `productAnalyzer`). Image/audio/video providers (`imageGenerator`, `wanR2V`, `ttsVoiceoverPlanner`, etc.) stay on `ProviderRouter` — `laravel/ai`'s non-text provider shape isn't settled enough to migrate. That's a follow-up epic.

**Architecture:**
- `App\Domain\Nodes\Concerns\InteractsWithLlm` trait (parallels `InteractsWithHuman`).
- Exposes `llmConfigRules()` + `llmDefaultConfig()` helpers templates merge into their own config surface.
- Resolvers `resolveLlmProvider(array $config)` and `resolveLlmModel(array $config, string $provider)` with **flat-config shim** for backward compat with seeded workflows that still have top-level `provider` / `model`.
- Convenience method `callTextGeneration($ctx, $systemPrompt, $prompt, $maxTokens = null): string` that uses `Laravel\Ai\AnonymousAgent` under the hood.
- A deprecation warning (logged once per run) fires when the flat keys are used, flagging the migration path.

**Tech Stack:** PHP 8.4, Laravel 11, `laravel/ai` v0.6.0 (installed), PHPUnit 11. Frontend inspector gains the `llm.*` fields automatically via the manifest (NM1–NM3 already deliver schema-driven rendering).

**Non-goals:** rewriting `ProviderRouter`, migrating image/audio/video providers, adding per-node cost tracking or rate limiting, supporting tool-use from nodes (that's the Assistant's domain).

---

## LG1 — `InteractsWithLlm` trait + resolvers + convenience method

**Files:**
- Create: `backend/app/Domain/Nodes/Concerns/InteractsWithLlm.php`
- Test: `backend/tests/Unit/Domain/Nodes/Concerns/InteractsWithLlmTest.php`

**Trait surface:**

```php
namespace App\Domain\Nodes\Concerns;

use App\Domain\Nodes\NodeExecutionContext;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;

trait InteractsWithLlm
{
    /** @return array<string, array<int,string>> */
    public function llmConfigRules(): array
    {
        return [
            'llm'          => ['sometimes', 'array'],
            'llm.provider' => ['sometimes', 'string'],
            'llm.model'    => ['sometimes', 'string'],
        ];
    }

    /** @return array<string, mixed> */
    public function llmDefaultConfig(): array
    {
        return ['llm' => ['provider' => '', 'model' => '']];
    }

    /**
     * Resolve the provider name the node should use.
     * Precedence: llm.provider → (legacy) provider → config('ai.default').
     * Logs a deprecation warning when the legacy flat key is consulted.
     */
    protected function resolveLlmProvider(array $config): string
    {
        $nested = $config['llm']['provider'] ?? '';
        if ($nested !== '') return $nested;

        $legacy = $config['provider'] ?? '';
        if ($legacy !== '') {
            $this->warnOnceOnLegacyLlmConfig('provider');
            return $legacy;
        }

        return (string) config('ai.default', 'fireworks');
    }

    /**
     * Resolve the model for a given provider.
     * Precedence: llm.model → (legacy) model → config('ai.providers.{provider}.default_model')
     * → '' (meaning: let the provider's driver pick).
     */
    protected function resolveLlmModel(array $config, string $provider): string
    {
        $nested = $config['llm']['model'] ?? '';
        if ($nested !== '') return $nested;

        $legacy = $config['model'] ?? '';
        if ($legacy !== '') {
            $this->warnOnceOnLegacyLlmConfig('model');
            return $legacy;
        }

        return (string) config("ai.providers.{$provider}.default_model", '');
    }

    /**
     * Call laravel/ai for a text-generation round trip.
     * Templates should call this from execute() instead of ProviderRouter.
     */
    protected function callTextGeneration(
        NodeExecutionContext $ctx,
        string $systemPrompt,
        string $prompt,
        ?int $maxTokens = null,
    ): string {
        $provider = $this->resolveLlmProvider($ctx->config);
        $model    = $this->resolveLlmModel($ctx->config, $provider) ?: null;

        $agent = new AnonymousAgent($systemPrompt, [], []);
        $response = $agent->prompt(
            $prompt,
            provider: $provider,
            model: $model,
        );

        return (string) $response->text;
    }

    /** Guarded once-per-run deprecation logger. */
    private function warnOnceOnLegacyLlmConfig(string $field): void
    {
        static $warned = [];
        $key = static::class . ':' . $field;
        if (isset($warned[$key])) return;
        $warned[$key] = true;

        Log::warning('InteractsWithLlm: node config uses deprecated flat key', [
            'template' => static::class,
            'field'    => $field,
            'migrate_to' => "llm.{$field}",
        ]);
    }
}
```

**Tests** (`InteractsWithLlmTest.php`) — use a `StubTemplate` exercising the trait:

1. `llm_config_rules_declares_three_keys` — rules array has `llm`, `llm.provider`, `llm.model` all `sometimes`.
2. `llm_default_config_is_nested_with_empty_strings`.
3. `resolveLlmProvider_uses_nested_key_first`.
4. `resolveLlmProvider_falls_back_to_flat_legacy_key_and_warns_once` — mock Log, assert exactly one warning per field across two calls.
5. `resolveLlmProvider_falls_back_to_config_ai_default_when_unset`.
6. `resolveLlmModel_uses_nested_key_first`.
7. `resolveLlmModel_falls_back_to_flat_legacy_key_and_warns`.
8. `resolveLlmModel_falls_back_to_config_default_model`.
9. `resolveLlmModel_returns_empty_string_if_no_default` — non-throwing.
10. `callTextGeneration_calls_anonymous_agent_with_resolved_provider_and_model` — use `Laravel\Ai\Facades\Ai::fake()` (or direct `TelegramAgent::fake`-equivalent for `AnonymousAgent`) to stub the round trip and assert the resolved `provider`/`model` were passed through. If `laravel/ai` lacks a provider-level fake, `Http::fake(['api.fireworks.ai/*' => Http::response(['choices'=>[['message'=>['content'=>'ok'],'finish_reason'=>'stop']]], 200)])` is a fine substitute — we verified that shape works during the LA5 live smoke.

**Acceptance:**
- `docker exec backend-app-1 php artisan test --filter=InteractsWithLlmTest` green.
- No template changes yet (LG2 owns that).

---

## LG2 — Migrate 6 text-gen templates to the trait

**Files:**
- Edit: `backend/app/Domain/Nodes/Templates/StoryWriterTemplate.php`
- Edit: `backend/app/Domain/Nodes/Templates/ScriptWriterTemplate.php`
- Edit: `backend/app/Domain/Nodes/Templates/SceneSplitterTemplate.php`
- Edit: `backend/app/Domain/Nodes/Templates/PromptRefinerTemplate.php`
- Edit: `backend/app/Domain/Nodes/Templates/TrendResearcherTemplate.php`
- Edit: `backend/app/Domain/Nodes/Templates/ProductAnalyzerTemplate.php`
- Edit each corresponding test file under `backend/tests/Unit/Domain/Nodes/Templates/`

**Per-template changes** (same pattern for all six):

**configRules():** remove `'provider'`, `'apiKey'`, `'model'` top-level entries. Merge `$this->llmConfigRules()` into the array_merge call. If the template also uses `InteractsWithHuman`, chain both merges (`StoryWriterTemplate` already does for humanGate).

Before:
```php
return array_merge([
    'provider' => ['required', 'string'],
    'apiKey' => ['sometimes', 'string'],
    'model' => ['sometimes', 'string'],
    'targetDurationSeconds' => [...],
    // ...
], $this->humanGateConfigRules());
```

After:
```php
return array_merge([
    'targetDurationSeconds' => [...],
    // ...
], $this->llmConfigRules(), $this->humanGateConfigRules());
```

**defaultConfig():** drop `'provider' => 'stub'`, `'apiKey' => ''`, `'model' => 'gpt-4o'`. Merge `$this->llmDefaultConfig()`. Same shape.

**execute():** replace
```php
$result = $ctx->provider(Capability::TextGeneration)->execute(
    Capability::TextGeneration,
    ['systemPrompt' => ..., 'prompt' => ...],
    $config,
);
```
with
```php
$result = $this->callTextGeneration(
    $ctx,
    $this->buildSystemPrompt($config),
    $this->buildUserPrompt(...),
);
```
Return value is already a string (no JSON-parse change needed downstream; individual templates handle their own parsing).

**Per-template test updates:**
- Rewrite tests that explicitly set `'provider' => 'stub'` at the top level to either:
  - **Keep the flat form** in one test per template to exercise the legacy shim (asserts output is produced + a log warning fires).
  - **Use the nested form** (`'llm' => ['provider' => 'fireworks']`) in all other tests.
- For stub-backed deterministic output: instead of `'provider' => 'stub'` (which used `StubAdapter`), now stub `Laravel\Ai` via `Http::fake(['api.fireworks.ai/*' => Http::response([...fake choices...], 200)])` or an Ai-level fake if the package exposes one. Keep test assertions on the parsed output shape; what matters is "given this input, we return a valid Story/Scene/Script JSON shape", not which provider generated it.
- **Do NOT break backward compat in this task.** The legacy shim keeps flat-config tests passing.

**Feature-test impact:** `FullRunTest`, `RunWorkflowJobTest`, `RunExecutorTest`, `EndToEndPipelineTest` all invoke these templates through the executor. Run them after the migration; expect any hardcoded `'provider' => 'stub'` inside their document fixtures to still work via the shim. Update them on a case-by-case basis as nested-form-only in a follow-up.

**Acceptance:**
- `docker exec backend-app-1 php artisan test --filter="StoryWriterTemplateTest|ScriptWriterTemplateTest|SceneSplitterTemplateTest|PromptRefinerTemplateTest|TrendResearcherTemplateTest|ProductAnalyzerTemplateTest"` green.
- No regressions: `--filter="FullRunTest|RunExecutorTest|RunWorkflowJobTest|EndToEndPipelineTest|HumanLoopCycleTest"` still green.
- `grep -rn "'provider' =>" backend/app/Domain/Nodes/Templates` returns **zero** hits.
- `grep -rn "'apiKey' =>" backend/app/Domain/Nodes/Templates` returns **zero** hits.

---

## LG3 — Update seeders, fixtures, and manifest emission

**Files:**
- Edit: `backend/database/seeders/HumanGateDemoSeeder.php`
- Edit: `backend/database/seeders/DemoWorkflowSeeder.php`
- Any other seeder that sets node config with `provider/apiKey/model` — grep first.
- Review: `backend/app/Domain/Nodes/NodeManifestBuilder.php` — confirm the manifest emits the `llm` nested object correctly via NM1's transpiler (no code change expected, only verification + possibly a dedicated assertion in `NodeManifestControllerTest`).

**Seeder changes:**

For every node config block in seeders, replace:
```php
'config' => [
    'provider' => 'stub',
    'apiKey' => '',
    'model' => 'gpt-4o',
    // ...
],
```
with:
```php
'config' => [
    'llm' => ['provider' => '', 'model' => ''],   // '' = use config('ai.default')
    // ...
],
```

Or outright drop the `llm` block (both empty strings = same as absent) and rely on the template default. Explicit-empty is clearer for readers.

**Verify manifest:** `docker exec backend-app-1 curl -s http://localhost/api/nodes/manifest | jq '.nodes.storyWriter.configSchema.properties.llm'` should now show:
```json
{
  "type": "object",
  "properties": {
    "provider": {"type": "string", "default": ""},
    "model": {"type": "string", "default": ""}
  },
  "required": [],
  "additionalProperties": false
}
```
If not, debug NM1's dot-notation transpiler — but it already handles `humanGate.*` the same way, so this should just work.

**Optional enhancement:** add a `description` field on `llm.provider` / `llm.model` rules so the inspector form renders helper text. Requires extending `configRules()` to emit descriptions — if Laravel's validator rules array doesn't carry descriptions cleanly, add a separate `configFieldDescriptions(): array<string, string>` method on `NodeTemplate` that the manifest builder merges in. Defer to LG5 if non-trivial.

**Acceptance:**
- `docker exec backend-app-1 php artisan db:seed --class=HumanGateDemoSeeder --force` completes without errors.
- `--filter="NodeManifestControllerTest"` still green.
- Frontend inspector (manual smoke): open StoryWriter node; "LLM" fieldset renders with `provider` + `model` text inputs.
- `grep -rn "'provider' =>" backend/database/seeders` returns zero hits.

---

## LG4 — Deprecate flat LLM config; document the convention

**Files:**
- Edit: `backend/app/Domain/Nodes/Concerns/InteractsWithLlm.php` — the deprecation log is already in place from LG1; this task adds a **schema-level** deprecation so the manifest flags legacy shape.
- Edit: `backend/AGENTS.md` — append a "LLM provider convention" subsection.
- Edit: `backend/.env.example` — add comment block explaining that `ANTHROPIC_API_KEY` / `FIREWORKS_API_KEY` / etc. are the SINGLE source of LLM credentials and node configs must never carry keys.
- Create (optional): `backend/app/Domain/Nodes/Concerns/InteractsWithLlm.md` — a short standalone doc for node authors.

**AGENTS.md addition** (under Tech Stack or a new "LLM usage" section):

> **LLM calls from node templates.** All text-generation templates must `use App\Domain\Nodes\Concerns\InteractsWithLlm`. Credentials, base URLs, and default models live **only** in `config/ai.php` (env-backed). Node configs may optionally set `llm.provider` and `llm.model` to override defaults per workflow; any other LLM-related key (`apiKey`, `provider`, `model` at the top level) is deprecated and will be removed. Call `$this->callTextGeneration(...)` from `execute()` rather than going through `ProviderRouter`.

**.env.example block:**

```
# ──────────────────────────────────────────────────────────────
# LLM credentials — the single source. Never duplicate into node
# configs. Providers listed in config/ai.php read these values.
# ──────────────────────────────────────────────────────────────
AI_DEFAULT_PROVIDER=fireworks
FIREWORKS_API_KEY=
FIREWORKS_URL=https://api.fireworks.ai/inference/v1
FIREWORKS_MODEL=accounts/fireworks/models/minimax-m2p7
ANTHROPIC_API_KEY=
OPENAI_API_KEY=
```

**Acceptance:**
- `AGENTS.md` diff shows the new section.
- `.env.example` diff shows the block.
- Running `grep -rn "apiKey\|'provider' =>" backend/app backend/database` after this task returns zero hits in app code + seeders (tests may still exercise the shim; that's fine).

---

## LG5 — Live smoke + epic close

**Files:**
- Edit: `docs/plans/2026-04-19-llm-config-generalization.md` — add "Done — results" footer with commit hashes.
- No code changes beyond cleanup of any smoke script you create.

**Steps:**
1. `docker restart backend-worker-1 backend-app-1`.
2. `docker exec backend-app-1 composer dump-autoload -o`.
3. `docker exec backend-app-1 php artisan config:clear`.
4. Re-seed: `docker exec backend-app-1 php artisan db:seed --class=HumanGateDemoSeeder --force`.
5. Full suite sweep:
   ```
   docker exec backend-app-1 php artisan test --filter="InteractsWithLlmTest|StoryWriterTemplateTest|ScriptWriterTemplateTest|SceneSplitterTemplateTest|PromptRefinerTemplateTest|TrendResearcherTemplateTest|ProductAnalyzerTemplateTest|NodeManifestControllerTest|FullRunTest|RunExecutorTest|RunWorkflowJobTest|EndToEndPipelineTest|HumanLoopCycleTest|TelegramAgentTest|TelegramAgentEndToEndTest"
   ```
6. **Live smoke** (preferred): disposable script that triggers the `story-writer-gated` workflow via `TelegramAgent` (same harness used in LA5) and asserts (a) a Fireworks HTTP call went out, (b) no `ProviderRouter` path was hit for text gen, (c) an `ExecutionRun` was created. Delete the script afterward.
7. Confirm in logs that **no** `InteractsWithLlm: node config uses deprecated flat key` warning fires with freshly seeded data. If it does, LG3 missed a seeder.
8. Commit `docs(llm): record config generalization results` with the footer update.
9. `bd close` this bead + the epic.

**Acceptance:**
- All tests green.
- Live smoke against real Fireworks completes in < 15s with a valid `storyArc` produced.
- Deprecation log is silent for newly seeded workflows.
- `AGENTS.md` + `.env.example` reflect the new convention.

**Follow-ups to note in the footer:**
- Image/audio/video providers still use `ProviderRouter` + flat `provider/model` keys. Worth a separate epic (`InteractsWithImageGeneration`, `InteractsWithVideoGeneration`, etc.) once `laravel/ai`'s non-text provider shape is settled OR we pick a separate image-provider library.
- Consider removing the flat-config shim in 2 weeks after no prod warnings fire — track as a P3 bead.

---

## Dependency order

```
LG1 (trait) ──► LG2 (migrate 6 templates) ──► LG3 (seeders + manifest) ──► LG4 (docs + deprecation) ──► LG5 (smoke + close)
```

Strictly sequential. LG2 is the largest by file count (6 templates + tests) but mechanical; LG3 and LG4 are short.

## Relationship to other epics

- **Depends on NM1–NM3 (Node Manifest Alignment):** already landed. The manifest already emits dot-notation nested objects (we use `humanGate.*` exactly the same way). No changes to the transpiler needed.
- **Depends on LA1–LA5 (`laravel/ai` migration):** already landed. `AnonymousAgent::prompt(provider:, model:)` is the API `callTextGeneration()` wraps.
- **Does NOT block aimodel-gnkh (Assistant skills):** they can progress in parallel.
- **Sets up a follow-up epic** for image/audio/video provider centralization.
