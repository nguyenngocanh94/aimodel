<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Providers;

use App\Domain\Capability;
use App\Domain\Providers\Adapters\LoggingProviderDecorator;
use App\Domain\Providers\ProviderContract;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class LoggingProviderDecoratorTest extends TestCase
{
    /** @var list<array{level: string, message: string, context: array}> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->logged = [];

        Log::listen(function (MessageLogged $event) {
            $this->logged[] = [
                'level' => $event->level,
                'message' => $event->message,
                'context' => $event->context,
            ];
        });

        // Route the 'providers' channel to 'null' so no files are written during tests.
        config(['logging.channels.providers.driver' => 'monolog']);
        config(['logging.channels.providers.handler' => \Monolog\Handler\NullHandler::class]);
    }

    public function test_logs_on_success(): void
    {
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

        $started = $this->findLog('info', 'Provider call started');
        $this->assertNotNull($started, 'Expected "Provider call started" info log');
        $this->assertSame('text_generation', $started['context']['capability']);
        $this->assertSame(['prompt'], $started['context']['input_keys']);

        $succeeded = $this->findLog('info', 'Provider call succeeded');
        $this->assertNotNull($succeeded, 'Expected "Provider call succeeded" info log');
        $this->assertSame('text_generation', $succeeded['context']['capability']);
        $this->assertArrayHasKey('duration_ms', $succeeded['context']);
    }

    public function test_logs_on_failure(): void
    {
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

        $started = $this->findLog('info', 'Provider call started');
        $this->assertNotNull($started, 'Expected "Provider call started" info log');

        $failed = $this->findLog('error', 'Provider call failed');
        $this->assertNotNull($failed, 'Expected "Provider call failed" error log');
        $this->assertSame('Provider timeout', $failed['context']['error']);
        $this->assertArrayHasKey('duration_ms', $failed['context']);

        // Must NOT have a "succeeded" log
        $succeeded = $this->findLog('info', 'Provider call succeeded');
        $this->assertNull($succeeded, 'Should not log success on failure');
    }

    public function test_api_key_is_redacted_in_logs(): void
    {
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

        $started = $this->findLog('info', 'Provider call started');
        $this->assertNotNull($started);

        $this->assertSame('***REDACTED***', $started['context']['config']['apiKey']);
        $this->assertSame('tts-1', $started['context']['config']['model']);
    }

    public function test_inner_receives_unredacted_config(): void
    {
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

    /**
     * Find the first log entry matching the given level and message.
     *
     * @return array{level: string, message: string, context: array}|null
     */
    private function findLog(string $level, string $message): ?array
    {
        foreach ($this->logged as $entry) {
            if ($entry['level'] === $level && $entry['message'] === $message) {
                return $entry;
            }
        }

        return null;
    }
}
