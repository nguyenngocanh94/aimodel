<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Middleware;

use App\Services\Ai\Middleware\RetryPrimary;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RetryPrimaryTest extends TestCase
{
    private function fakePrompt(): AgentPrompt
    {
        // Cheapest way: build a partial mock that bypasses the ctor.
        return $this->getMockBuilder(AgentPrompt::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    #[Test]
    public function returns_immediately_when_next_succeeds(): void
    {
        $middleware = new RetryPrimary(maxSeconds: 1);

        $called = 0;
        $next = function (AgentPrompt $p) use (&$called) {
            $called++;
            return 'ok';
        };

        $this->assertSame('ok', $middleware->handle($this->fakePrompt(), $next));
        $this->assertSame(1, $called);
    }

    #[Test]
    public function retries_on_rate_limit_then_returns_success(): void
    {
        Log::spy();
        $middleware = new RetryPrimary(maxSeconds: 2);

        $attempt = 0;
        $next = function (AgentPrompt $p) use (&$attempt) {
            $attempt++;
            if ($attempt <= 2) {
                throw RateLimitedException::forProvider('fireworks');
            }
            return 'finally-ok';
        };

        $this->assertSame('finally-ok', $middleware->handle($this->fakePrompt(), $next));
        $this->assertSame(3, $attempt);
        Log::shouldHaveReceived('warning')->with('ai.retry_primary', \Mockery::any())->twice();
    }

    #[Test]
    public function retries_on_overloaded_then_returns_success(): void
    {
        Log::spy();
        $middleware = new RetryPrimary(maxSeconds: 2);

        $attempt = 0;
        $next = function (AgentPrompt $p) use (&$attempt) {
            $attempt++;
            if ($attempt === 1) {
                throw ProviderOverloadedException::forProvider('fireworks');
            }
            return 'recovered';
        };

        $this->assertSame('recovered', $middleware->handle($this->fakePrompt(), $next));
        $this->assertSame(2, $attempt);
    }

    #[Test]
    public function rethrows_when_budget_is_zero(): void
    {
        $middleware = new RetryPrimary(maxSeconds: 0);

        $this->expectException(RateLimitedException::class);

        $middleware->handle($this->fakePrompt(), function () {
            throw RateLimitedException::forProvider('fireworks');
        });
    }

    #[Test]
    public function rethrows_after_exhausting_budget(): void
    {
        Log::spy();
        $middleware = new RetryPrimary(maxSeconds: 1); // 1s budget

        $this->expectException(RateLimitedException::class);

        $middleware->handle($this->fakePrompt(), function () {
            throw RateLimitedException::forProvider('fireworks');
        });
    }

    #[Test]
    public function lets_non_failover_exceptions_propagate_without_retry(): void
    {
        $middleware = new RetryPrimary(maxSeconds: 5);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $calls = 0;
        $middleware->handle($this->fakePrompt(), function () use (&$calls) {
            $calls++;
            throw new \RuntimeException('boom');
        });

        // Unreachable, but if we got here assert single call.
        $this->assertSame(1, $calls);
    }
}
