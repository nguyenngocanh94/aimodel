<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Providers;

use App\Domain\Capability;
use App\Domain\Providers\Adapters\LoggingProviderDecorator;
use App\Domain\Providers\ProviderContract;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class LoggingProviderDecoratorTest extends TestCase
{
    public function test_logs_on_success(): void
    {
        Log::fake();

        $inner = new class implements ProviderContract {
            public function execute(Capability $capability, array $input, array $config): mixed
            {
                return ['text' => 'generated content'];
            }
        };

        $decorator = new LoggingProviderDecorator($inner);
        $result = $decorator->execute(
            Capability::TextGeneration,
            ['prompt' => 'hello'],
            ['model' => 'gpt-4o'],
        );

        $this->assertSame(['text' => 'generated content'], $result);

        Log::channel('providers')->assertLogged('info', function ($message, $context) {
            return $message === 'Provider call started'
                && $context['capability'] === 'text_generation'
                && $context['input_keys'] === ['prompt'];
        });

        Log::channel('providers')->assertLogged('info', function ($message, $context) {
            return $message === 'Provider call succeeded'
                && $context['capability'] === 'text_generation'
                && isset($context['duration_ms']);
        });
    }

    public function test_logs_on_failure(): void
    {
        Log::fake();

        $inner = new class implements ProviderContract {
            public function execute(Capability $capability, array $input, array $config): mixed
            {
                throw new \RuntimeException('Provider timeout');
            }
        };

        $decorator = new LoggingProviderDecorator($inner);

        try {
            $decorator->execute(
                Capability::TextToImage,
                ['prompt' => 'a sunset'],
                [],
            );
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame('Provider timeout', $e->getMessage());
        }

        Log::channel('providers')->assertLogged('info', function ($message) {
            return $message === 'Provider call started';
        });

        Log::channel('providers')->assertLogged('error', function ($message, $context) {
            return $message === 'Provider call failed'
                && $context['error'] === 'Provider timeout'
                && isset($context['duration_ms']);
        });
    }

    public function test_api_key_is_redacted_in_logs(): void
    {
        Log::fake();

        $inner = new class implements ProviderContract {
            public function execute(Capability $capability, array $input, array $config): mixed
            {
                return 'ok';
            }
        };

        $decorator = new LoggingProviderDecorator($inner);
        $decorator->execute(
            Capability::TextToSpeech,
            ['text' => 'hello'],
            ['apiKey' => 'sk-super-secret-key-12345', 'model' => 'tts-1'],
        );

        Log::channel('providers')->assertLogged('info', function ($message, $context) {
            if ($message !== 'Provider call started') {
                return false;
            }

            // apiKey must be redacted
            $this->assertSame('***REDACTED***', $context['config']['apiKey']);
            // other config keys must be preserved
            $this->assertSame('tts-1', $context['config']['model']);

            return true;
        });
    }

    public function test_inner_receives_unredacted_config(): void
    {
        Log::fake();

        $captured = [];
        $inner = new class($captured) implements ProviderContract {
            public function __construct(private array &$captured) {}

            public function execute(Capability $capability, array $input, array $config): mixed
            {
                $this->captured = $config;

                return 'ok';
            }
        };

        $decorator = new LoggingProviderDecorator($inner);
        $decorator->execute(
            Capability::TextGeneration,
            ['prompt' => 'test'],
            ['apiKey' => 'sk-real-key', 'model' => 'gpt-4o'],
        );

        $this->assertSame('sk-real-key', $captured['apiKey']);
    }
}
