<?php

declare(strict_types=1);

namespace App\Services\Ai\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Retry the primary provider on transient rate-limit / overload failures with
 * bounded exponential backoff before re-throwing so that Promptable's built-in
 * `withModelFailover()` can advance to the next provider in the chain.
 *
 * Usage from an agent that uses `Laravel\Ai\Promptable`:
 *
 *     use Laravel\Ai\Contracts\HasMiddleware;
 *
 *     public function middleware(): array
 *     {
 *         return [new RetryPrimary(config('ai.failover.primary_max_retry_seconds'))];
 *     }
 *
 * Semantics:
 *  - Exponential backoff starting at 100ms, doubling each attempt, capped so the
 *    total elapsed sleep <= $maxSeconds. `maxSeconds === 0` means no retry.
 *  - On each retry, a `ai.retry_primary` warning is logged.
 *  - When the budget is exhausted, the last exception is re-thrown unchanged so
 *    the vendor failover loop sees it as `FailoverableException` and advances.
 */
final class RetryPrimary
{
    public function __construct(
        private int $maxSeconds = 10,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next)
    {
        // No retry budget → single attempt, pass-through.
        if ($this->maxSeconds <= 0) {
            return $next($prompt);
        }

        $elapsedMs = 0;
        $delayMs = 100;
        $attempt = 0;
        $deadline = $this->maxSeconds * 1000;

        while (true) {
            $attempt++;
            try {
                return $next($prompt);
            } catch (RateLimitedException|ProviderOverloadedException $e) {
                // Out of budget — bubble up so withModelFailover() moves to the next provider.
                if ($elapsedMs >= $deadline) {
                    Log::warning('ai.retry_primary.exhausted', [
                        'attempts' => $attempt,
                        'elapsed_ms' => $elapsedMs,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                $sleep = min($delayMs, $deadline - $elapsedMs);
                Log::warning('ai.retry_primary', [
                    'attempt' => $attempt,
                    'sleep_ms' => $sleep,
                    'elapsed_ms' => $elapsedMs,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                usleep($sleep * 1000);
                $elapsedMs += $sleep;
                $delayMs = min($delayMs * 2, 5000); // soft cap per-attempt 5s.
            }
        }
    }
}
