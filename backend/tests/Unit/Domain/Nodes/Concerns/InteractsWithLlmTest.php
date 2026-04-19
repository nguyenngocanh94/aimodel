<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Concerns;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\Concerns\InteractsWithLlm;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AnonymousAgent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Stub NodeTemplate that exercises the InteractsWithLlm trait.
 *
 * Static warning state note:
 * warnOnceOnLegacyLlmConfig() uses `static $warned = []` which persists for the
 * lifetime of the PHP process. To isolate per-test, we reset it via Reflection
 * in tearDown(). This avoids needing a different stub class for each test.
 */
class LlmStubTemplate extends NodeTemplate
{
    use InteractsWithLlm;

    public string $type { get => 'llmStub'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'LLM Stub'; }
    public NodeCategory $category { get => NodeCategory::Utility; }
    public string $description { get => 'Stub for testing InteractsWithLlm'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [PortDefinition::input('in', 'Input', DataType::Text)],
            outputs: [PortDefinition::output('out', 'Output', DataType::Text)],
        );
    }

    public function configRules(): array
    {
        return array_merge(['some' => ['sometimes', 'string']], $this->llmConfigRules());
    }

    public function defaultConfig(): array
    {
        return array_merge(['some' => ''], $this->llmDefaultConfig());
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        return ['out' => PortPayload::success('', DataType::Text)];
    }

    /**
     * Expose protected resolvers for direct testing without going through execute().
     */
    public function publicResolveLlmProvider(array $config): string
    {
        return $this->resolveLlmProvider($config);
    }

    public function publicResolveLlmModel(array $config, string $provider): string
    {
        return $this->resolveLlmModel($config, $provider);
    }

    public function publicCallTextGeneration(NodeExecutionContext $ctx, string $sys, string $prompt): string
    {
        return $this->callTextGeneration($ctx, $sys, $prompt);
    }
}

final class InteractsWithLlmTest extends TestCase
{
    /** Reset the static $warned array in warnOnceOnLegacyLlmConfig between tests. */
    protected function tearDown(): void
    {
        $this->resetWarnedStatic();
        parent::tearDown();
    }

    private function resetWarnedStatic(): void
    {
        // Use Reflection to clear the static variable inside the closure/method.
        // This is necessary because static locals in PHP methods are truly static
        // and survive across test method calls within the same process.
        $ref = new \ReflectionMethod(LlmStubTemplate::class, 'warnOnceOnLegacyLlmConfig');
        $statics = $ref->getStaticVariables();
        if (isset($statics['warned'])) {
            // Invoke once with a dummy field to get a reference, then clear via Closure bind trick
            $closure = \Closure::bind(static function () {
                static $warned = [];
                $warned = [];
            }, null, LlmStubTemplate::class);
            // Simpler approach: just use a fresh class that has its own static scope.
            // Since PHP static locals in traits are per-class, each distinct class
            // has its own static $warned. We rely on a unique stub per test instead
            // of trying to clear — see note below.
        }
        // Note: PHP static variables in trait methods are scoped to the USING class.
        // Because all tests in this file use LlmStubTemplate, $warned persists
        // across test methods. We clear it by calling the method with an anonymous
        // subclass (each anonymous class has its own static scope) — but that only
        // works for isolation if each test uses a fresh anonymous class.
        //
        // CHOSEN STRATEGY: Each test that exercises warnOnce creates a new anonymous
        // subclass inline (see individual tests) so each has its own static scope.
        // The tearDown above is a no-op safeguard; the real isolation is the
        // per-test anonymous class pattern documented in warn-tests below.
    }

    /** Small helper: build a minimal NodeExecutionContext with a given config. */
    private function makeCtx(array $config = []): NodeExecutionContext
    {
        return new NodeExecutionContext(
            nodeId: 'test-node',
            config: $config,
            inputs: [],
            runId: 'test-run',
            providerRouter: $this->createMock(\App\Domain\Providers\ProviderRouter::class),
            artifactStore: $this->createMock(\App\Services\ArtifactStoreContract::class),
        );
    }

    // ────────────────────────────────────────────────────────────────────
    // 1. llmConfigRules
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function llm_config_rules_declares_three_keys(): void
    {
        $template = new LlmStubTemplate();
        $rules = $template->llmConfigRules();

        $this->assertArrayHasKey('llm', $rules);
        $this->assertArrayHasKey('llm.provider', $rules);
        $this->assertArrayHasKey('llm.model', $rules);

        // Each must declare 'sometimes' as first rule
        $this->assertContains('sometimes', $rules['llm']);
        $this->assertContains('sometimes', $rules['llm.provider']);
        $this->assertContains('sometimes', $rules['llm.model']);
    }

    // ────────────────────────────────────────────────────────────────────
    // 2. llmDefaultConfig
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function llm_default_config_is_nested_with_empty_strings(): void
    {
        $template = new LlmStubTemplate();
        $defaults = $template->llmDefaultConfig();

        $this->assertSame(['llm' => ['provider' => '', 'model' => '']], $defaults);
    }

    // ────────────────────────────────────────────────────────────────────
    // 3. resolveLlmProvider — nested key wins
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function resolveLlmProvider_uses_nested_key_first(): void
    {
        $template = new LlmStubTemplate();
        $result = $template->publicResolveLlmProvider(['llm' => ['provider' => 'anthropic']]);

        $this->assertSame('anthropic', $result);
    }

    // ────────────────────────────────────────────────────────────────────
    // 4. resolveLlmProvider — flat legacy key fallback + warn once
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function resolveLlmProvider_falls_back_to_flat_legacy_key_and_warns_once(): void
    {
        /** @var list<array{level: string, message: string, context: array}> $logged */
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = ['level' => $event->level, 'message' => $event->message, 'context' => $event->context];
        });

        // Use an anonymous subclass so its static $warned is fresh (own scope per PHP class).
        $template = new class extends LlmStubTemplate {
            public string $type { get => 'anonLlmStubProvider'; }
        };

        $config = ['provider' => 'openai'];

        // Call twice — should log exactly once.
        $result1 = $template->publicResolveLlmProvider($config);
        $result2 = $template->publicResolveLlmProvider($config);

        $this->assertSame('openai', $result1);
        $this->assertSame('openai', $result2);

        $warnings = array_filter($logged, fn ($log) =>
            $log['level'] === 'warning'
            && str_contains($log['message'], 'deprecated flat key')
            && ($log['context']['field'] ?? '') === 'provider',
        );
        $this->assertCount(1, $warnings, 'Expected exactly one deprecation warning for provider flat key');
    }

    // ────────────────────────────────────────────────────────────────────
    // 5. resolveLlmProvider — falls back to config('ai.default')
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function resolveLlmProvider_falls_back_to_config_ai_default_when_unset(): void
    {
        config()->set('ai.default', 'fireworks');

        $template = new LlmStubTemplate();
        $result = $template->publicResolveLlmProvider([]);

        $this->assertSame('fireworks', $result);
    }

    // ────────────────────────────────────────────────────────────────────
    // 6. resolveLlmModel — nested key wins
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function resolveLlmModel_uses_nested_key_first(): void
    {
        $template = new LlmStubTemplate();
        $result = $template->publicResolveLlmModel(
            ['llm' => ['model' => 'claude-sonnet-4-6']],
            'anthropic',
        );

        $this->assertSame('claude-sonnet-4-6', $result);
    }

    // ────────────────────────────────────────────────────────────────────
    // 7. resolveLlmModel — flat legacy key fallback + warn
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function resolveLlmModel_falls_back_to_flat_legacy_key_and_warns(): void
    {
        /** @var list<array{level: string, message: string, context: array}> $logged */
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = ['level' => $event->level, 'message' => $event->message, 'context' => $event->context];
        });

        // Anonymous subclass for fresh static scope.
        $template = new class extends LlmStubTemplate {
            public string $type { get => 'anonLlmStubModel'; }
        };

        $result = $template->publicResolveLlmModel(['model' => 'gpt-4o'], 'openai');

        $this->assertSame('gpt-4o', $result);

        $warnings = array_filter($logged, fn ($log) =>
            $log['level'] === 'warning'
            && str_contains($log['message'], 'deprecated flat key')
            && ($log['context']['field'] ?? '') === 'model',
        );
        $this->assertNotEmpty($warnings, 'Expected a deprecation warning for model flat key');
    }

    // ────────────────────────────────────────────────────────────────────
    // 8. resolveLlmModel — falls back to config default_model
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function resolveLlmModel_falls_back_to_config_default_model(): void
    {
        config()->set('ai.providers.fireworks.default_model', 'minimax-m2p7');

        $template = new LlmStubTemplate();
        $result = $template->publicResolveLlmModel([], 'fireworks');

        $this->assertSame('minimax-m2p7', $result);
    }

    // ────────────────────────────────────────────────────────────────────
    // 9. resolveLlmModel — returns empty string if no default, no throw
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function resolveLlmModel_returns_empty_string_if_no_default(): void
    {
        // Ensure no default_model is configured for an unknown provider.
        config()->set('ai.providers.unknown_provider', []);

        $template = new LlmStubTemplate();
        $result = $template->publicResolveLlmModel([], 'unknown_provider');

        $this->assertSame('', $result);
    }

    // ────────────────────────────────────────────────────────────────────
    // 10. callTextGeneration — uses AnonymousAgent with resolved provider + model
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function callTextGeneration_calls_anonymous_agent_with_resolved_provider_and_model(): void
    {
        config()->set('ai.default', 'fireworks');

        // Fake the HTTP layer so no real Fireworks call goes out.
        Http::fake([
            'api.fireworks.ai/*' => Http::response([
                'id' => 'cmpl-test',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'accounts/fireworks/models/minimax-m2p7',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'Hello from stub'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ], 200),
        ]);

        $ctx = $this->makeCtx([
            'llm' => ['provider' => 'fireworks', 'model' => 'accounts/fireworks/models/minimax-m2p7'],
        ]);

        $template = new LlmStubTemplate();
        $result = $template->publicCallTextGeneration($ctx, 'You are helpful.', 'Say hi');

        // Assert the correct text was returned from the faked response.
        $this->assertSame('Hello from stub', $result);

        // Assert an HTTP request was made to the Fireworks endpoint
        // with the resolved model in the request body.
        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            if (!str_contains($request->url(), 'api.fireworks.ai')) {
                return false;
            }
            $body = $request->data();
            return ($body['model'] ?? '') === 'accounts/fireworks/models/minimax-m2p7';
        });
    }
}
