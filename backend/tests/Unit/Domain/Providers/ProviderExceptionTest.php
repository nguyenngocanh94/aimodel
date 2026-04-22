<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Providers;

use App\Domain\Providers\ProviderException;
use PHPUnit\Framework\TestCase;

class ProviderExceptionTest extends TestCase
{
    public function test_creates_with_all_properties(): void
    {
        $original = new \RuntimeException('API rate limit exceeded');
        $e = new ProviderException(
            message: 'OpenAI request failed',
            provider: 'openai',
            capability: 'text_generation',
            retryable: true,
            previous: $original,
        );

        $this->assertSame('OpenAI request failed', $e->getMessage());
        $this->assertSame('openai', $e->provider);
        $this->assertSame('text_generation', $e->capability);
        $this->assertTrue($e->retryable);
        $this->assertSame($original, $e->getPrevious());
    }

    public function test_to_array_returns_structured_error(): void
    {
        $e = new ProviderException(
            message: 'Timeout',
            provider: 'replicate',
            capability: 'text_to_image',
            retryable: true,
        );

        $arr = $e->toArray();

        $this->assertArrayHasKey('error', $arr);
        $this->assertSame('provider_error', $arr['error']['code']);
        $this->assertSame('Timeout', $arr['error']['message']);
        $this->assertSame('replicate', $arr['error']['provider']);
        $this->assertTrue($arr['error']['retryable']);
    }

    public function test_to_array_includes_original_message(): void
    {
        $original = new \RuntimeException('Connection refused');
        $e = new ProviderException(
            message: 'Provider unavailable',
            provider: 'fal',
            capability: 'media_composition',
            previous: $original,
        );

        $arr = $e->toArray();

        $this->assertSame('Connection refused', $arr['error']['original']);
    }

    public function test_defaults_to_non_retryable(): void
    {
        $e = new ProviderException(
            message: 'Bad request',
            provider: 'anthropic',
            capability: 'text_generation',
        );

        $this->assertFalse($e->retryable);
        $this->assertFalse($e->toArray()['error']['retryable']);
    }
}
