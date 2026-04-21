# Close the remaining `laravel/ai` adoption gaps

> **For Claude:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Goal.** Finish the `laravel/ai` adoption the Telegram-agent + text-gen-template migrations started. Three remaining gaps: (A) image / audio / video call sites still bypass `laravel/ai` through `App\Domain\Providers\*` adapters + raw `Http::` calls, (B) structured-output nodes still prompt-engineer JSON and strip fences on the way back, and (C) repeated system prompts (WorkflowPlanner, node instructions) are not cached against Anthropic. After this epic, every AI round-trip in the codebase goes through one of `Laravel\Ai\AnonymousAgent`, `Laravel\Ai\StructuredAnonymousAgent`, or a typed `Provider::image()` / `Provider::audio()` / `Provider::transcription()` call — no hand-rolled HTTP, no fence-stripping, no duplicated credential plumbing.

**Foundation already landed.**
- **LA1–LA5 (epic `aimodel-gdd`, closed 2026-04-18):** `TelegramAgent` + 5 tools on `Laravel\Ai\Contracts\Agent`/`Tool`, `RemembersConversations` for session, Fireworks via the `groq` driver with custom `url`. Custom tool-use clients deleted (−1 207 LOC net).
- **LG1 (task `aimodel-w3q2`, closed 2026-04-19):** `App\Domain\Nodes\Concerns\InteractsWithLlm` trait + `callTextGeneration()` convenience + `resolveLlmProvider()` / `resolveLlmModel()` resolvers with legacy-flat-key shim.
- **LG2–LG5 (tasks `aimodel-o46j` → `aimodel-6r0k`, in flight):** six text-gen templates (`storyWriter`, `scriptWriter`, `sceneSplitter`, `promptRefiner`, `trendResearcher`, `productAnalyzer`) are being migrated off `ProviderRouter` onto the trait. **This epic depends on LG2–LG5 closing first for text-gen nodes** — Gap B's structured-output migration edits the same six files, so the two cannot race. Image / audio / video work (LC1, LC3) is independent and can proceed in parallel.

**Architecture after this epic.**
- `App\Domain\Providers\ProviderRouter` and all six adapters (`OpenAi`, `Anthropic`, `Replicate`, `Fal`, `DashScope`, `Stub`, plus `LoggingProviderDecorator`) are deleted. Non-text nodes either use `laravel/ai`'s native gateways (`OpenAiProvider::image()`, `GeminiProvider::image()`, `OpenAiProvider::audio()`, `OpenAiProvider::transcription()`) via a per-capability `InteractsWithImage` / `InteractsWithAudio` / `InteractsWithVideo` trait, or call a narrowly-scoped non-`laravel/ai` client (`FalClient`, `DashScopeClient`, `ReplicateClient`) for capabilities laravel/ai does not ship — all three move from `App\Domain\Providers\Adapters` to `App\Services\MediaProviders\*`, keep the same HTTP logic, but are no longer wired through a router.
- Every LLM node that emits JSON implements `Laravel\Ai\Contracts\HasStructuredOutput` (via a shared `ProducesStructuredJson` trait on `InteractsWithLlm`) and uses `StructuredAnonymousAgent` for the round trip. `preg_replace('/^```(?:json|JSON)?/')`, the balanced-brace extractor in `WorkflowPlanner::parsePlan`, and the lenient `json_decode` ladders are all deleted.
- Anthropic prompt caching enabled on the two hot system prompts: `WorkflowPlannerPrompt::build()` and per-node `buildSystemPrompt()` for storyWriter / scriptWriter / sceneSplitter. Delivered by implementing `Laravel\Ai\Contracts\HasProviderOptions` on the planner agent + a thin helper on `InteractsWithLlm`.

**Tech Stack:** PHP 8.4, Laravel 11, `laravel/ai ^0.6.0` (installed at `backend/vendor/laravel/ai`), PHPUnit 11. Anthropic `cache_control` via raw provider options; Fireworks (`groq` driver) does not support caching today — callers log-and-skip on non-Anthropic providers.

**Non-goals.**
- Rewriting `Laravel\Ai\Gateway\*` to add cache-control as a first-class concept. We use `HasProviderOptions` which Anthropic's `BuildsTextRequests` already honours via `array_merge($body, $providerOptions)` at `backend/vendor/laravel/ai/src/Gateway/Anthropic/Concerns/BuildsTextRequests.php:65`.
- Adding streaming to any node or to the planner (separate epic).
- Migrating `Capability::MediaComposition` / `Capability::ReferenceToVideo` into `laravel/ai` — the package has no video gateway. We keep narrow HTTP clients.
- Replacing the `Capability` enum itself (other consumers still rely on the string values).

**Blocking / relationship to open beads:**
- Depends on: `aimodel-o46j` (LG2), `aimodel-xglm` (LG3), `aimodel-5so4` (LG4), `aimodel-6r0k` (LG5) — **for LC2 only** (structured output on the six text-gen templates).
- LC1 (image/audio/video) and LC3 (Anthropic caching on planner) are independent and may start immediately.
- The epic closes when all LC tasks are green and `grep -rn "App\\\\Domain\\\\Providers" backend/app backend/tests` returns zero hits outside files being deleted.

---

## LC1 — Replace image/audio/video adapters with typed media clients (Gap A)

**Files:**
- Create: `backend/app/Services/MediaProviders/FalClient.php` (from `backend/app/Domain/Providers/Adapters/FalAdapter.php`, lines 28–104: three capabilities — `referenceToVideo`, `textToImage`, `mediaComposition`).
- Create: `backend/app/Services/MediaProviders/ReplicateClient.php` (from `backend/app/Domain/Providers/Adapters/ReplicateAdapter.php`, lines 27–89 — `textToImage`, `textGeneration` via `/predictions` + polling).
- Create: `backend/app/Services/MediaProviders/DashScopeClient.php` (from `backend/app/Domain/Providers/Adapters/DashScopeAdapter.php`, lines 39–342 — `referenceToVideo`, `textToImage`; drop `textGeneration` entirely, see step 4 below).
- Create: `backend/app/Domain/Nodes/Concerns/InteractsWithImage.php` (mirrors `InteractsWithLlm` shape).
- Create: `backend/app/Domain/Nodes/Concerns/InteractsWithVideo.php`.
- Create: `backend/app/Domain/Nodes/Concerns/InteractsWithAudio.php`.
- Edit: `backend/app/Domain/Nodes/Templates/ImageGeneratorTemplate.php` (currently uses `ProviderRouter` at line 79, 123).
- Edit: `backend/app/Domain/Nodes/Templates/WanR2VTemplate.php` (line 105).
- Edit: `backend/app/Domain/Nodes/Templates/TtsVoiceoverPlannerTemplate.php` (line 55 — uses `Capability::TextGeneration` for a planning call; use `InteractsWithLlm::callStructuredText()` from LC2 instead).
- Edit: `backend/app/Domain/Nodes/Templates/VideoComposerTemplate.php` (line 57 — `MediaComposition`; routes through Fal).
- Edit: `backend/app/Domain/Nodes/Templates/SubtitleFormatterTemplate.php` (line 55 — `StructuredTransform`, a faux LLM capability; migrate to `InteractsWithLlm`).
- Edit: `backend/app/Domain/Nodes/Templates/ImageAssetMapperTemplate.php` (line 55 — same, `StructuredTransform`).
- Edit: `backend/app/Domain/Nodes/NodeExecutionContext.php` — remove the `provider()` method (lines 39–42) and the `ProviderRouter $providerRouter` ctor arg (line 24). This is the sole remaining consumer of `ProviderRouter`.
- Edit: `backend/app/Providers/AppServiceProvider.php` (line 23) — drop the `ProviderRouter` singleton binding.
- Edit: `backend/app/Domain/Execution/RunExecutor.php` — remove the `ProviderRouter $providerRouter` dependency (line 28) and stop passing it to `NodeExecutionContext::__construct`.
- Delete (after green): `backend/app/Domain/Providers/ProviderRouter.php`, `ProviderContract.php`, `ProviderException.php`, and `backend/app/Domain/Providers/Adapters/{OpenAi,Anthropic,Replicate,Fal,DashScope,Stub,LoggingProviderDecorator}Adapter.php` (9 files total).
- Delete: `backend/tests/Unit/Domain/Providers/ProviderRouterTest.php`, `LoggingProviderDecoratorTest.php`, `ProviderContractTest.php`, and adapter tests (`FalAdapterTest.php`, `DashScopeAdapterTest.php`, `StubAdapterTest.php`). `OpenAiAdapter` / `AnthropicAdapter` / `ReplicateAdapter` have no tests today.
- Test: `backend/tests/Unit/Services/MediaProviders/FalClientTest.php`, `ReplicateClientTest.php`, `DashScopeClientTest.php` (copy assertions from the deleted adapter tests; they already use `Http::fake`).
- Test: add one case in each rewritten template test asserting the trait-dispatched call shape.

**Read first:**
- `backend/vendor/laravel/ai/src/Providers/Concerns/GeneratesImages.php` — the `image()` method signature (`prompt`, `attachments`, `size`, `quality`, `model`, `timeout`).
- `backend/vendor/laravel/ai/src/Providers/OpenAiProvider.php:22` — confirms OpenAI implements `ImageProvider`, `AudioProvider`, `TranscriptionProvider`.
- `backend/vendor/laravel/ai/src/Providers/GeminiProvider.php:22`, `XaiProvider.php:13` — the other two `ImageProvider`s.
- `backend/vendor/laravel/ai/src/Ai.php` — the facade entry: `Ai::provider('openai')->image($prompt, ...)`. Confirm the static accessor.
- `backend/app/Domain/Nodes/Concerns/InteractsWithLlm.php` — pattern to clone (`llmConfigRules`, `llmDefaultConfig`, `resolve*`, `callTextGeneration`).
- `backend/config/ai.php:17` — `default_for_images` is `gemini`; `default_for_audio` / `default_for_transcription` are `openai`. These are the fallbacks the new traits consult.

**Steps:**

1. **Port the three narrow HTTP clients.** Move `FalAdapter`, `ReplicateAdapter`, `DashScopeAdapter` into `App\Services\MediaProviders\*Client`. Flatten the `execute(Capability, $input, $config)` switch into capability-named public methods: `FalClient::textToImage(string $prompt, array $options)`, `FalClient::referenceToVideo(...)`, etc. Constructor takes `string $apiKey, ?string $model`. Drop the `ProviderContract` implementation. Keep the existing `Http::fake`-friendly call shape.

2. **Build three `InteractsWith*` traits.** Each mirrors `InteractsWithLlm` one-for-one:

   - `InteractsWithImage`: `imageConfigRules()` → `['image.provider' => ..., 'image.model' => ..., 'image.size' => ...]`; `imageDefaultConfig()`; `resolveImageProvider(array $config): string` with precedence `image.provider → config('ai.default_for_images')`; `callImageGeneration(NodeExecutionContext $ctx, string $prompt, array $options = []): string` — returns raw bytes. Inside: if the resolved provider is one `laravel/ai` supports (`openai`, `gemini`, `xai`), call `Ai::provider($name)->image($prompt, ...)` and download `->url`/`->b64Json`; otherwise look up a `FalClient` / `ReplicateClient` / `DashScopeClient` from the container and invoke `textToImage`.
   - `InteractsWithVideo`: analogous, for `referenceToVideo` / `mediaComposition`. There is no laravel/ai video gateway, so every resolved provider routes to a container-bound client (`fal`, `dashscope`, `replicate`). Throw `\RuntimeException("unknown video provider: {$name}")` for unknown values.
   - `InteractsWithAudio`: `callTextToSpeech(...)` → `Ai::provider('openai')->audio($text, voice: ...)`; `callTranscription(...)` if any node needs it. If laravel/ai's audio surface doesn't map cleanly to a single-file call, keep a narrow `OpenAiAudioClient` — re-inspect `backend/vendor/laravel/ai/src/Providers/Concerns/GeneratesAudio.php` before choosing.

3. **Rewrite the six non-text templates.** Replace every `$ctx->provider(Capability::X)->execute(Capability::X, $input, $config)` call:
   - `ImageGeneratorTemplate.php:79,123` → `$this->callImageGeneration($ctx, $promptText)`.
   - `WanR2VTemplate.php:105` → `$this->callReferenceToVideo($ctx, $prompt, referenceUrls: $referenceUrls)`.
   - `VideoComposerTemplate.php:57` → `$this->callMediaComposition($ctx, $frames, audio: $audio)`.
   - `SubtitleFormatterTemplate.php:55` → `$this->callStructuredText($ctx, ...)` (LC2's new method). `StructuredTransform` was always a faux LLM capability in stub-land.
   - `ImageAssetMapperTemplate.php:55` → same as above.
   - `TtsVoiceoverPlannerTemplate.php:55` → **also** `callStructuredText`; it was miscategorised as TTS.

   Delete the top-level `'provider' => ['sometimes', 'string']` rule and the `'provider' => 'stub'` default from each template.

4. **Excise `ProviderRouter` from the executor plumbing.** Edit `NodeExecutionContext` to drop `$providerRouter`; its only consumer (the `provider()` method) moves to each trait. Edit `RunExecutor::__construct` to drop the `ProviderRouter` argument. Edit `AppServiceProvider::register` to drop the `singleton(ProviderRouter::class, ...)` binding. Bind the three media clients instead: `$this->app->bind(FalClient::class, fn () => new FalClient((string) env('FAL_KEY'), null));` etc. — credentials from env, never from node config.

5. **DashScope text-generation was the outlier.** `DashScopeAdapter::textGeneration()` (lines 55–92) handled vision via OpenAI-compatible `image_url` content parts. That call site was `ProductAnalyzerTemplate.php:152` which LG2 has already migrated off `ProviderRouter` onto `InteractsWithLlm`. The new code path sends `imageUrls` through the planner prompt as text; if ProductAnalyzer still needs true vision (check after LG2 lands), extend `InteractsWithLlm::callTextGeneration()` to accept `array $imageUrls = []` and forward via `$attachments` on `AnonymousAgent::prompt($prompt, $attachments, ...)` — `laravel/ai` routes attachments to vision-capable providers automatically. Do not re-introduce DashScope client for text; keep it image/video only.

6. **Delete the old stack.** After all tests green: remove the 9 files listed above from `backend/app/Domain/Providers/`. `grep -rn "App\\Domain\\Providers" backend/app backend/tests backend/config` must return zero hits.

**Acceptance:**
- `docker exec backend-app-1 php artisan test --filter="ImageGeneratorTemplateTest|WanR2VTemplateTest|VideoComposerTemplateTest|SubtitleFormatterTemplateTest|ImageAssetMapperTemplateTest|TtsVoiceoverPlannerTemplateTest|FalClientTest|ReplicateClientTest|DashScopeClientTest|RunExecutorTest"` green.
- `grep -rn "ProviderRouter\|ProviderContract\b\|Domain\\\\Providers" backend/app backend/tests` returns zero hits.
- Full feature sweep (`FullRunTest|EndToEndPipelineTest|HumanLoopCycleTest`) still green — these rely on the stub path; ensure the new traits expose a `_faked` short-circuit or use `Http::fake()` in the fixtures.
- Live smoke (optional, skip if no image budget): run `story-writer-gated` end-to-end including the Image step; a real Fireworks text call + a real Gemini image call both fire.

**Finish protocol:**
```
git commit -m "feat(ai): migrate image/audio/video off ProviderRouter to laravel/ai + narrow clients (LC1)"
bd close aimodel-<LC1-id>
```

---

## LC2 — Structured output via `HasStructuredOutput` (Gap B)

**Files:**
- Edit: `backend/app/Domain/Nodes/Concerns/InteractsWithLlm.php` — add `callStructuredText()` helper (signature below) alongside the existing `callTextGeneration()`. No backward-incompat changes to the current method.
- Edit: `backend/app/Domain/Nodes/Templates/StoryWriterTemplate.php` — delete `parseStoryArc()` (lines 355–383) and the fence-strip code (lines 358–372); `execute()` now returns the structured array directly.
- Edit: `backend/app/Domain/Nodes/Templates/ScriptWriterTemplate.php` — delete the parse/fallback ladder (see line 281 and surrounding).
- Edit: `backend/app/Domain/Nodes/Templates/SceneSplitterTemplate.php` — delete `parseScenes()` (lines 188–203).
- Edit: `backend/app/Domain/Nodes/Templates/PromptRefinerTemplate.php` — delete fence-strip ladder (lines 388–408).
- Edit: `backend/app/Domain/Nodes/Templates/TrendResearcherTemplate.php` — delete parse ladder (around line 209).
- Edit: `backend/app/Domain/Nodes/Templates/ProductAnalyzerTemplate.php` — delete `parseAnalysis()` (lines 198–220).
- Edit: `backend/app/Domain/Planner/WorkflowPlanner.php` — replace `parsePlan()` (lines 169–195) and `extractJsonObject()` (lines 201–234) with a `StructuredAnonymousAgent` call; `WorkflowPlan::fromArray()` then hydrates from the already-decoded structured response.
- Edit: `backend/app/Domain/Planner/WorkflowPlannerPrompt.php` — drop the "no markdown" / "no fences" boilerplate from `negativeExamples()` (lines 314–333) and `outputGuard()` (lines 345–350). The schema makes those instructions dead weight.
- Edit: `backend/app/Services/TelegramAgent/Tools/RefinePlanTool.php` — delete `extractJson()` (lines 181–210) and use `StructuredAnonymousAgent` with the same `WorkflowPlan` schema.
- Edit: `backend/app/Services/TelegramAgent/Tools/RefinePlanPrompt.php` — drop the "no fence" rules at lines 74 and 151–153.
- Edit template tests under `backend/tests/Unit/Domain/Nodes/Templates/*` — stop asserting against raw JSON strings; use `Http::fake` or `Ai::fake` to return structured data directly.
- Test: `backend/tests/Unit/Domain/Nodes/Concerns/InteractsWithLlmTest.php` — add two cases for `callStructuredText`.

**Read first:**
- `backend/vendor/laravel/ai/src/Contracts/HasStructuredOutput.php` — `schema(JsonSchema $schema): array<string, Type>`.
- `backend/vendor/laravel/ai/src/StructuredAnonymousAgent.php` — the closure-based inline agent. Constructor: `(string $instructions, iterable $messages, iterable $tools, Closure $schema)`. The closure receives a `JsonSchema` and returns an array of typed properties.
- `backend/vendor/laravel/ai/src/Providers/Concerns/GeneratesText.php:59` — confirms the gateway auto-passes the schema to the provider and returns `StructuredAgentResponse`.
- `backend/vendor/laravel/ai/src/Responses/StructuredAgentResponse.php` + `Responses/StructuredTextResponse.php:14` — `$response->structured` is the already-decoded array. Also `ArrayAccess` via `ProvidesStructuredResponse`.
- `backend/vendor/laravel/ai/src/ObjectSchema.php` — note that object schemas auto-apply `additionalProperties: false` recursively.
- `backend/vendor/laravel/ai/src/Schema.php` — `name()` / `toSchema()` contract if a Schema object is preferred over a closure.
- Grep result baseline: `grep -rn "preg_replace.*\`\`\`\|strip.*fence\|json_decode.*result" backend/app` — must go to zero after this task except in `TelegramWebhookController` (decoding Telegram's own body) and `InteractsWithHuman` (parsing human proposal edits — out of scope).

**Steps:**

1. **Add `callStructuredText()` to `InteractsWithLlm`.** Signature:
   ```php
   /** @return array<string, mixed> */
   protected function callStructuredText(
       NodeExecutionContext $ctx,
       string $systemPrompt,
       string $prompt,
       Closure $schema,   // fn (JsonSchema $s) => ['field' => $s->string(), ...]
       ?int $maxTokens = null,
   ): array
   ```
   Implementation: build a `StructuredAnonymousAgent(instructions: $systemPrompt, messages: [], tools: [], schema: $schema)`, call `prompt($prompt, provider: …, model: …)` with the same provider/model resolution as `callTextGeneration()`, return `$response->structured`. Fallback: if `structured` is empty or the provider did not honour the schema, return `[]` and log a warning tagged `InteractsWithLlm: structured output missing`. **No fence-stripping, no `json_decode`.**

2. **Per-template migration.** For each of the 6 text-gen templates, convert `buildSystemPrompt()` into *structure-free* system prompt (drop the "Return valid JSON with keys..." paragraphs — the schema speaks for itself), then define a `schemaClosure(): Closure` that mirrors the old prose. Example for `StoryWriterTemplate`:
   ```php
   private function storySchema(): Closure
   {
       return fn (JsonSchema $s) => [
           'title'   => $s->string(),
           'theme'   => $s->string(),
           'formula' => $s->string(),
           'hook'    => $s->string(),
           'shots'   => $s->array()->items($s->object()->properties([
               'shotNumber'      => $s->integer(),
               'timestamp'       => $s->string(),
               'description'     => $s->string(),
               'dialogue'        => $s->string(),
               'emotion'         => $s->string(),
               'setting'         => $s->string(),
               'cameraDirection' => $s->string(),
           ])),
           'cast' => $s->object()->properties([
               'lead'       => $s->array()->items($s->string()),
               'supporting' => $s->array()->items($s->string()),
           ]),
           'toneDirection'  => $s->string(),
           'soundDirection' => $s->string(),
           'productMoment'  => $s->string(),
       ];
   }
   ```
   (Verify the exact `JsonSchema` API surface — `$s->string()`, `$s->array()->items(...)`, `$s->object()->properties(...)` — against `backend/vendor/laravel/ai/src/Gateway/OpenAi/Concerns/MapsTools.php:41` which constructs one with `new JsonSchemaTypeFactory`, and adjust if the method names differ.)

   Then `execute()` shrinks to:
   ```php
   $storyArc = $this->callStructuredText(
       $ctx,
       $this->buildSystemPrompt($config),
       $this->buildUserPrompt(...),
       $this->storySchema(),
   );
   return ['storyArc' => PortPayload::success(value: $storyArc, ...)];
   ```

3. **Migrate `WorkflowPlanner`.** Replace `invokeLlm()` (lines 142–163) and `parsePlan()` (169–195) with one `StructuredAnonymousAgent` round trip. The schema is already documented by `WorkflowPlan::fromArray()` (`backend/app/Domain/Planner/WorkflowPlan.php:96`) — read that hydrator and build a mirror closure. Pass `$response->structured` to `WorkflowPlan::fromArray()` directly; if that throws, treat as parse failure the same way today does. Delete `extractJsonObject()` entirely.

4. **Migrate `RefinePlanTool`.** Same treatment — delete `extractJson()`, use `StructuredAnonymousAgent` with the `WorkflowPlan` schema, pass decoded array to `WorkflowPlan::fromArray()`. The `'error' => 'parse_failed'` branch becomes unreachable once the schema is enforced; keep it as a belt-and-braces JSON-encoded error surface in case the provider returns empty.

5. **Scrub prompts.** Remove the "DO NOT wrap output in markdown code fences" lines from `WorkflowPlannerPrompt::negativeExamples()` + `outputGuard()` + `RefinePlanPrompt`. They become noise once the gateway enforces the schema.

6. **Test updates.** Every template test that builds a fake LLM response must emit a structured array, not a JSON string. The easiest path: `Ai::fake([StoryWriterTemplate::class => ['title' => 'X', 'shots' => [...], ...]])`. If `laravel/ai`'s fake surface doesn't accept arrays for structured responses, fall back to `Http::fake` with a real provider response — check `backend/vendor/laravel/ai/src/Gateway/FakeTextGateway.php` first.

**Acceptance:**
- `docker exec backend-app-1 php artisan test --filter="StoryWriterTemplateTest|ScriptWriterTemplateTest|SceneSplitterTemplateTest|PromptRefinerTemplateTest|TrendResearcherTemplateTest|ProductAnalyzerTemplateTest|WorkflowPlannerTest|RefinePlanToolTest|InteractsWithLlmTest"` green.
- Fence-strip grep is empty: `grep -rn "preg_replace.*\\\`\\\`\\\`" backend/app` → 0 hits.
- JSON-on-LLM-output grep is empty: `grep -rn "json_decode.*\\\$result\\|json_decode.*raw\\|extractJsonObject" backend/app` → 0 hits (the `TelegramWebhookController` `json_decode($rawBody)` is fine — that's HTTP body parsing, not LLM output).
- Live smoke: run a planner with an intentionally noisy prompt ("explain briefly then return plan"); previously the fence-strip ladder was required, now the gateway enforces strict JSON. Confirm a valid `WorkflowPlan` hydrates first try.

**Finish protocol:**
```
git commit -m "refactor(ai): use HasStructuredOutput for all schema-bound nodes; delete fence strippers (LC2)"
bd close aimodel-<LC2-id>
```

---

## LC3 — Anthropic prompt caching on repeated system prompts (Gap D)

**Files:**
- Edit: `backend/app/Domain/Nodes/Concerns/InteractsWithLlm.php` — add a cache-hint helper (see step 1).
- Edit: `backend/app/Domain/Planner/WorkflowPlanner.php` — the `AnonymousAgent` constructed at line 144 becomes a small named agent class implementing `HasProviderOptions` so the planner system prompt is cached across retries and across calls from `RefinePlanTool`.
- Create: `backend/app/Domain/Planner/WorkflowPlannerAgent.php` — named agent replacing the inline `AnonymousAgent` in `WorkflowPlanner::invokeLlm()`. Implements `Agent, Conversational, HasTools, HasStructuredOutput, HasProviderOptions`. Provides `instructions()` + `providerOptions(Lab|string $provider): array`.
- Edit: `backend/app/Domain/Nodes/Templates/StoryWriterTemplate.php`, `ScriptWriterTemplate.php`, `SceneSplitterTemplate.php` — after LC2 migration, wrap the system prompt in a cache-control marker via `providerOptions()` (see step 2).
- Edit: `backend/AGENTS.md` — append a "Prompt caching" subsection under Tech Stack noting Anthropic-only, 1024-token minimum, 5-minute TTL. Provide a one-line "how to cache a system prompt" example.
- Test: `backend/tests/Unit/Domain/Planner/WorkflowPlannerAgentTest.php` — assert `providerOptions('anthropic')` returns an array containing `system` with at least one block tagged `cache_control`.

**Read first:**
- `backend/vendor/laravel/ai/src/Contracts/HasProviderOptions.php` — `providerOptions(Lab|string $provider): array`.
- `backend/vendor/laravel/ai/src/Gateway/Anthropic/Concerns/BuildsTextRequests.php` — specifically:
  - Line 31: `$body['system'] = $instructions;` sets system as a plain string.
  - Line 36: `$providerOptions = $options?->providerOptions(Lab::Anthropic) ?? [];`.
  - Line 65: `return array_merge($body, $providerOptions);` — provider options **override** the built body. That is the hook: setting `['system' => [...blocks with cache_control...]]` in `providerOptions` overrides the plain-string system.
- `backend/vendor/laravel/ai/src/Gateway/TextGenerationOptions.php:29-32` — `providerOptions()` delegates to the agent when the agent implements `HasProviderOptions`.
- `backend/vendor/laravel/ai/src/Enums/Lab.php` — verify `Lab::Anthropic` exists and match its value.
- Anthropic caching reference (external): system prompts are cached by setting each cached block to `{"type": "text", "text": "...", "cache_control": {"type": "ephemeral"}}`. Minimum ~1024 tokens; TTL 5 minutes (refreshed on hit); free writes, 10% reads.
- Not in `laravel/ai`: any first-class caching abstraction. `grep -rn "cache_control" backend/vendor/laravel/ai` → 0 hits at planning time. We deliver through raw provider options.

**Steps:**

1. **Add a `cachedSystemPrompt()` helper to `InteractsWithLlm`.** Signature:
   ```php
   /** @return array<string, mixed>|null  null when provider does not support caching */
   protected function cachedSystemPrompt(string $provider, string $systemPrompt): ?array
   {
       if ($provider !== 'anthropic') {
           return null; // fireworks/groq/openai — caching not available or not supported here
       }
       return [
           'system' => [[
               'type' => 'text',
               'text' => $systemPrompt,
               'cache_control' => ['type' => 'ephemeral'],
           ]],
       ];
   }
   ```
   Templates that opt into caching implement `HasProviderOptions` on the template-level agent (not the template itself — templates don't `prompt()`; the `AnonymousAgent` they build does). Adjust the pattern in step 2.

2. **Replace the inline `AnonymousAgent` in hot templates.** Current shape:
   ```php
   $agent = new AnonymousAgent($systemPrompt, [], []);
   $response = $agent->prompt($prompt, provider: ..., model: ...);
   ```
   The `AnonymousAgent` class is final-ish but extendable; the cleaner approach is a per-template named agent class (e.g. `App\Domain\Nodes\Templates\StoryWriterAgent`) extending `AnonymousAgent` that also implements `HasProviderOptions`:
   ```php
   public function providerOptions(Lab|string $provider): array
   {
       return ($provider === 'anthropic' || $provider === Lab::Anthropic)
           ? ['system' => [[
               'type' => 'text',
               'text' => $this->instructions,
               'cache_control' => ['type' => 'ephemeral'],
           ]]]
           : [];
   }
   ```
   Swap the constructor call in the template from `new AnonymousAgent(...)` to `new StoryWriterAgent(...)` — same args. Do this only for templates whose system prompt exceeds ~1024 tokens and is called repeatedly: `StoryWriter`, `ScriptWriter`, `SceneSplitter`, `WorkflowPlanner`. Skip `PromptRefiner` / `TrendResearcher` / `ProductAnalyzer` — their system prompts are below the caching threshold (verify with `strlen($this->buildSystemPrompt($config)) / 4 < 1024` sanity check in the test).

3. **WorkflowPlannerAgent.** This is the highest-value cache target — the planner system prompt (built by `WorkflowPlannerPrompt::build`) is ~2–3k tokens of node catalog and is re-sent on every plan attempt and every `RefinePlanTool` call. Move the `AnonymousAgent` instantiation out of `WorkflowPlanner::invokeLlm()` into a dedicated `WorkflowPlannerAgent` class with `HasProviderOptions` + `HasStructuredOutput` (the latter from LC2). `RefinePlanTool` also uses this same agent class instead of its own inline `AnonymousAgent`.

4. **Metrics check.** After deployment, run two plan attempts back-to-back against Anthropic (set `AI_DEFAULT_PROVIDER=anthropic` in a disposable test script). Inspect the response `usage` (`backend/vendor/laravel/ai/src/Responses/Data/Usage.php`) for `cache_creation_input_tokens` + `cache_read_input_tokens`. First call should show `cache_creation_*`, second call `cache_read_*` > 0. Log both values under `ai.cache` log channel.

5. **Fallback behaviour.** When the active provider isn't Anthropic, `providerOptions()` returns `[]` — the plain `system` string stays in the body. No code change in callers.

**Acceptance:**
- `WorkflowPlannerAgentTest` asserts `providerOptions('anthropic')` contains `system.0.cache_control.type === 'ephemeral'` and `providerOptions('fireworks') === []`.
- Template agent tests for StoryWriter/ScriptWriter/SceneSplitter similar.
- Live smoke against Anthropic (requires `ANTHROPIC_API_KEY`): second planner call shows `cache_read_input_tokens > 0` in `response->usage`.
- No regression on the default `fireworks` path — full existing suite green.

**Finish protocol:**
```
git commit -m "feat(ai): enable Anthropic prompt caching on planner + hot node system prompts (LC3)"
bd close aimodel-<LC3-id>
```

---

## LC4 — Live smoke, deletion sweep, and epic close

**Files:**
- Edit: `docs/plans/2026-04-19-laravel-ai-completeness.md` — append a "Done — results (2026-04-19)" footer with commit hashes, LOC delta, and a grep transcript proving the old stack is gone.
- Edit: `backend/AGENTS.md` — expand the "LLM provider convention" subsection added by LG4 with the image / audio / video / structured-output / cache sub-bullets from this epic. One sentence each.
- Edit: `backend/.env.example` — add `FAL_KEY=`, `REPLICATE_API_TOKEN=`, `DASHSCOPE_API_KEY=`, and comments noting `ai.default_for_images`/`_audio`/`_transcription`.
- Delete: any disposable smoke scripts created under `backend/storage/app/`.

**Steps:**

1. `docker restart backend-app-1 backend-worker-1 && docker exec backend-app-1 composer dump-autoload -o && docker exec backend-app-1 php artisan config:clear`.
2. Re-seed: `docker exec backend-app-1 php artisan db:seed --class=DemoWorkflowSeeder --force && docker exec backend-app-1 php artisan db:seed --class=HumanGateDemoSeeder --force && docker exec backend-app-1 php artisan db:seed --class=WorkflowCatalogSeeder --force`.
3. Full suite: `docker exec backend-app-1 php artisan test`. All green.
4. Deletion sweep (must each return zero):
   - `grep -rn "App\\\\Domain\\\\Providers" backend/app backend/tests backend/config`
   - `grep -rn "ProviderRouter\|ProviderContract" backend/app backend/tests`
   - `grep -rn "preg_replace.*\\\`\\\`\\\`\|extractJsonObject" backend/app`
   - `grep -rn "'provider' => 'stub'\|'apiKey' =>" backend/app backend/database`
5. Live smoke script `backend/storage/app/lc_smoke.php`: run `story-writer-gated` (requires image step) end-to-end with real Fireworks (text) + Gemini (image) + Fal (R2V video; skip if `FAL_KEY` unset). Assert:
   - `ExecutionRun` created, all node outputs non-empty.
   - `storyArc` is a structured array with `shots` populated (no fence-strip needed).
   - If `ANTHROPIC_API_KEY` set and `AI_DEFAULT_PROVIDER=anthropic`: second run shows `cache_read_input_tokens > 0`.
   Delete the script.
6. `bd close` each LC task, then the epic bead.

**Acceptance:**
- Full suite green (target: ≥ existing count, no regressions).
- All 4 deletion greps empty.
- Live smoke completes in < 30 s for text path, < 2 min if image/video included.
- Footer in this plan records commit hashes + test count.

**Finish protocol:**
```
git commit -m "docs(ai): record laravel-ai completeness migration results (LC4)"
bd close aimodel-<LC4-id>
bd close aimodel-<epic-id>
```

---

## Dependency order

```
                    ┌──► LC2 (structured output; requires LG2-5 closed) ──┐
                    │                                                     │
LG2–LG5 (open) ─────┤                                                     ├─► LC4 (smoke + close)
                    │                                                     │
                    └──► LC1 (image/audio/video; independent) ────────────┤
                                                                          │
                         LC3 (Anthropic caching; independent) ────────────┘
```

LC1 and LC3 may start before LG closes. LC2 must wait. LC4 must be last.

## Estimated impact

- **LC1:** ~−900 LOC (6 adapters + router + decorator + 4 tests deleted; 3 clients + 3 traits + 6 template edits + 3 client tests added).
- **LC2:** ~−400 LOC (fence-strip ladders + parse fallbacks + brace extractor + planner JSON plumbing deleted; schema closures + `callStructuredText` helper added).
- **LC3:** ~+200 LOC (`WorkflowPlannerAgent` + 3 template agents + cache helper + 4 tests).
- **LC4:** docs + env + smoke cleanup only.

Net: roughly −1 100 LOC, with the cache-control path as the only real new abstraction.
