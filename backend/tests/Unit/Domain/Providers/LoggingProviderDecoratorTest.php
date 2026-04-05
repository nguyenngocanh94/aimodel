<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Providers;

use App\Domain\Capability;
use App\Domain\Providers\Adapters\LoggingProviderDecorator;
use App\Domain\Providers\ProviderContract;
use Tests\TestCase;

class LoggingProviderDecoratorTest extends TestCase
{
    public function test_delegates_to_inner_and_returns_result(): void
    {
        $inner = new class implements ProviderContract {
            public function execute(Capability $capability, array $input, array $config): mixed
            {
                return ['text' => 'generated content'];
            }
        };

        $decorator = new LoggingProviderDecorator($inner, 'test');
        $result = $decorator->execute(Capability::TextGeneration, ['prompt' => 'test'], []);

        $this->assertSame(['text' => 'generated content'], $result);
    }

    public function test_rethrows_exceptions_from_inner(): void
    {
        $inner = new class implements ProviderContract {
            public function execute(Capability $capability, array $input, array $config): mixed
            {
                throw new \RuntimeException('Provider timeout');
            }
        };

        $decorator = new LoggingProviderDecorator($inner, 'failing');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provider timeout');

        $decorator->execute(Capability::TextToImage, ['prompt' => 'test'], []);
    }

    public function test_passes_input_and_config_to_inner(): void
    {
        $captured = [];
        $inner = new class($captured) implements ProviderContract {
            public function __construct(private array &$captured) {}

            public function execute(Capability $capability, array $input, array $config): mixed
            {
                $this->captured = [
                    'capability' => $capability,
                    'input' => $input,
                    'config' => $config,
                ];
                return 'ok';
            }
        };

        $decorator = new LoggingProviderDecorator($inner, 'test');
        $decorator->execute(
            Capability::TextToSpeech,
            ['text' => 'hello'],
            ['voice' => 'alloy', 'apiKey' => 'sk-secret'],
        );

        $this->assertSame(Capability::TextToSpeech, $captured['capability']);
        $this->assertSame(['text' => 'hello'], $captured['input']);
        $this->assertSame('sk-secret', $captured['config']['apiKey']); // Inner gets unredacted
    }

    public function test_redact_config_hides_api_key(): void
    {
        // Test via reflection since redactConfig is private
        $inner = new class implements ProviderContract {
            public function execute(Capability $capability, array $input, array $config): mixed
            {
                return 'ok';
            }
        };

        $decorator = new LoggingProviderDecorator($inner, 'test');

        $reflection = new \ReflectionMethod($decorator, 'redactConfig');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($decorator, [
            'apiKey' => 'sk-secret-12345',
            'model' => 'gpt-4o',
            'token' => 'bearer-token',
        ]);

        $this->assertSame('***REDACTED***', $result['apiKey']);
        $this->assertSame('***REDACTED***', $result['token']);
        $this->assertSame('gpt-4o', $result['model']);
    }
}
