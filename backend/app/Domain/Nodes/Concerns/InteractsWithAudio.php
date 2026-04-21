<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Concerns;

use App\Domain\Nodes\NodeExecutionContext;
use Laravel\Ai\Ai;

trait InteractsWithAudio
{
    protected function resolveAudioProvider(array $config): string
    {
        return (string) ($config['audio']['provider'] ?? config('ai.default_for_audio', 'openai'));
    }

    protected function callTextToSpeech(
        NodeExecutionContext $ctx,
        string $text,
        string $voice = 'alloy',
        ?string $instructions = null,
    ): string {
        $provider = $this->resolveAudioProvider($ctx->config);
        $model = $ctx->config['audio']['model'] ?? null;

        if ($provider === 'stub') {
            return 'fake-audio-bytes';
        }

        $response = Ai::provider($provider)->audio(
            $text,
            voice: $voice,
            instructions: $instructions,
            model: $model,
        );

        return (string) $response;
    }
}
