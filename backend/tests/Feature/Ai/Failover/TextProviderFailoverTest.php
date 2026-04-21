<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\Failover;

use App\Domain\Nodes\Concerns\InteractsWithLlm;
use App\Domain\Nodes\NodeExecutionContext;
use App\Services\ArtifactStoreContract;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Responses\TextResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LP-H3: verify that text-gen routed through `callTextGeneration()` uses the
 * ordered provider chain from config('ai.failover.text'), so that when the
 * primary (fireworks) throws a rate-limit, the request falls over to the
 * secondary (anthropic) and returns its response.
 */
final class TextProviderFailoverTest extends TestCase
{
    #[Test]
    public function failover_chain_falls_over_when_primary_rate_limits(): void
    {
        config()->set('ai.failover.text', ['fireworks', 'anthropic']);
        config()->set('ai.failover.primary_max_retry_seconds', 0); // fail over immediately

        // Fake gateway: primary throws RateLimitedException, failover returns success.
        AnonymousAgent::fake(function (string $prompt, $attachments, $provider, string $model) {
            if ($provider->name() === 'fireworks') {
                throw RateLimitedException::forProvider('fireworks');
            }

            // Secondary succeeds.
            return 'recovered-on-anthropic';
        });

        $helper = new class {
            use InteractsWithLlm;

            public function run(NodeExecutionContext $ctx): string
            {
                return $this->callTextGeneration($ctx, 'system', 'hello');
            }
        };

        $ctx = new NodeExecutionContext(
            nodeId: 'n',
            config: [], // no explicit provider → use global chain
            inputs: [],
            runId: 'run-failover',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $text = $helper->run($ctx);
        $this->assertSame('recovered-on-anthropic', $text);
    }

    #[Test]
    public function per_node_provider_chain_override_takes_precedence(): void
    {
        config()->set('ai.failover.text', ['fireworks', 'anthropic']);
        config()->set('ai.failover.primary_max_retry_seconds', 0);

        // Primary 'openai' (per-node override) rate-limits; 'openrouter' succeeds.
        AnonymousAgent::fake(function (string $prompt, $attachments, $provider, string $model) {
            if ($provider->name() === 'openai') {
                throw RateLimitedException::forProvider('openai');
            }
            return 'from-openrouter-fallback';
        });

        $helper = new class {
            use InteractsWithLlm;

            public function run(NodeExecutionContext $ctx): string
            {
                return $this->callTextGeneration($ctx, 'system', 'hi');
            }
        };

        $ctx = new NodeExecutionContext(
            nodeId: 'n',
            config: ['providerChain' => ['openai', 'openrouter']],
            inputs: [],
            runId: 'run-override',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $this->assertSame('from-openrouter-fallback', $helper->run($ctx));
    }

    #[Test]
    public function resolve_text_provider_arg_favors_override_over_chain(): void
    {
        config()->set('ai.failover.text', ['fireworks', 'anthropic']);

        $helper = new class {
            use InteractsWithLlm {
                resolveTextProviderArg as public;
            }
        };

        $this->assertSame(
            ['openai', 'openrouter'],
            $helper->resolveTextProviderArg(['providerChain' => ['openai', 'openrouter']]),
        );

        $this->assertSame(
            ['fireworks', 'anthropic'],
            $helper->resolveTextProviderArg([]),
        );

        // Explicit single-provider pin via llm.provider — no failover for this node.
        $this->assertSame(
            'openai',
            $helper->resolveTextProviderArg(['llm' => ['provider' => 'openai']]),
        );
    }
}
