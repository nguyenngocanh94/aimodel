<?php

declare(strict_types=1);

namespace App\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\ProviderContract;
use App\Domain\Providers\ProviderException;
use Illuminate\Support\Facades\Http;

class DashScopeAdapter implements ProviderContract
{
    private const ASPECT_RATIO_MAP = [
        '9:16' => '720*1280',
        '16:9' => '1280*720',
        '1:1' => '720*720',
    ];

    private const REGION_ENDPOINT_MAP = [
        'intl' => 'https://dashscope-intl.aliyuncs.com',
        'us' => 'https://dashscope-us.aliyuncs.com',
        'cn' => 'https://dashscope.aliyuncs.com',
    ];

    private const VIDEO_SYNTHESIS_PATH = '/api/v1/services/aigc/video-generation/video-synthesis';
    private const TASK_PATH = '/api/v1/tasks';

    private const DEFAULT_POLL_INTERVAL = 5;
    private const DEFAULT_TIMEOUT = 600; // 10 minutes in seconds

    public function __construct(
        private string $apiKey,
        private ?string $model = null,
        private string $region = 'intl',
    ) {}

    public function execute(Capability $capability, array $input, array $config): mixed
    {
        return match ($capability) {
            Capability::ReferenceToVideo => $this->referenceToVideo($input, $config),
            Capability::TextToImage => $this->textToImage($input, $config),
            Capability::TextToSpeech => $this->textToSpeech($input, $config),
            Capability::MediaComposition => $this->mediaComposition($input, $config),
            default => throw new \RuntimeException("DashScope adapter does not support: {$capability->value}"),
        };
    }

    public function getBaseUrl(): string
    {
        return self::REGION_ENDPOINT_MAP[$this->region]
            ?? throw new \InvalidArgumentException("Unknown DashScope region: {$this->region}");
    }

    /**
     * @return array{video: array{url: string, duration: float}, seed: int|null, task_id: string}
     */
    private function referenceToVideo(array $input, array $config): array
    {
        $prompt = $input['prompt'] ?? null;
        if (!$prompt) {
            throw new \InvalidArgumentException('ReferenceToVideo requires a prompt');
        }

        $referenceUrls = $input['reference_urls'] ?? [];
        if (empty($referenceUrls)) {
            throw new \InvalidArgumentException('ReferenceToVideo requires at least one reference URL');
        }

        $model = $this->model ?? $config['model'] ?? 'wan2.6-r2v-flash';

        $payload = $this->buildR2vPayload($model, $prompt, $referenceUrls, $input, $config);

        $taskId = $this->submitTask($payload);
        $result = $this->pollTask($taskId, $config);

        return [
            'video' => [
                'url' => $result['output']['video_url'] ?? '',
                'duration' => (float) ($result['output']['usage']['duration'] ?? 0),
            ],
            'seed' => $config['seed'] ?? null,
            'task_id' => $taskId,
        ];
    }

    public function buildR2vPayload(
        string $model,
        string $prompt,
        array $referenceUrls,
        array $input,
        array $config,
    ): array {
        $payload = [
            'model' => $model,
            'input' => [
                'prompt' => $prompt,
                'reference_urls' => $referenceUrls,
            ],
            'parameters' => [
                'size' => $this->mapAspectRatio($config['aspectRatio'] ?? '16:9'),
                'duration' => (int) ($config['duration'] ?? 5),
                'shot_type' => !empty($config['multiShots']) ? 'multi' : 'single',
                'audio' => $config['audio'] ?? true,
                'watermark' => $config['watermark'] ?? false,
            ],
        ];

        if (!empty($input['negative_prompt'])) {
            $payload['input']['negative_prompt'] = $input['negative_prompt'];
        }

        if (isset($config['seed'])) {
            $payload['parameters']['seed'] = (int) $config['seed'];
        }

        return $payload;
    }

    public function mapAspectRatio(string $aspectRatio): string
    {
        return self::ASPECT_RATIO_MAP[$aspectRatio]
            ?? throw new \InvalidArgumentException("Unsupported aspect ratio: {$aspectRatio}");
    }

    private function submitTask(array $payload): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'X-DashScope-Async' => 'enable',
        ])->timeout(30)->post(
            $this->getBaseUrl() . self::VIDEO_SYNTHESIS_PATH,
            $payload,
        );

        if ($response->failed()) {
            throw new ProviderException(
                "DashScope submit failed: {$response->body()}",
                provider: 'dashscope',
                capability: Capability::ReferenceToVideo->value,
                retryable: $response->status() >= 500,
            );
        }

        $taskId = $response->json('output.task_id');
        if (!$taskId) {
            throw new ProviderException(
                'DashScope submit did not return a task_id',
                provider: 'dashscope',
                capability: Capability::ReferenceToVideo->value,
            );
        }

        return $taskId;
    }

    private function pollTask(string $taskId, array $config): array
    {
        $pollInterval = (int) ($config['pollInterval'] ?? self::DEFAULT_POLL_INTERVAL);
        $timeout = (int) ($config['timeout'] ?? self::DEFAULT_TIMEOUT);
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->timeout(30)->get(
                $this->getBaseUrl() . self::TASK_PATH . "/{$taskId}",
            );

            if ($response->failed()) {
                throw new ProviderException(
                    "DashScope poll failed: {$response->body()}",
                    provider: 'dashscope',
                    capability: Capability::ReferenceToVideo->value,
                    retryable: true,
                );
            }

            $status = $response->json('output.task_status');

            if ($status === 'SUCCEEDED') {
                return $response->json();
            }

            if (in_array($status, ['FAILED', 'CANCELED', 'UNKNOWN'], true)) {
                throw new ProviderException(
                    "DashScope task {$taskId} ended with status: {$status}",
                    provider: 'dashscope',
                    capability: Capability::ReferenceToVideo->value,
                    retryable: $status === 'FAILED',
                );
            }

            // PENDING or RUNNING — wait and retry
            sleep($pollInterval);
        }

        throw new ProviderException(
            "DashScope task {$taskId} timed out after {$timeout} seconds",
            provider: 'dashscope',
            capability: Capability::ReferenceToVideo->value,
            retryable: true,
        );
    }

    /**
     * Placeholder — TextToImage via DashScope (not yet implemented).
     */
    private function textToImage(array $input, array $config): mixed
    {
        throw new \RuntimeException('DashScope TextToImage is not yet implemented');
    }

    /**
     * Placeholder — TextToSpeech via DashScope (not yet implemented).
     */
    private function textToSpeech(array $input, array $config): mixed
    {
        throw new \RuntimeException('DashScope TextToSpeech is not yet implemented');
    }

    /**
     * Placeholder — MediaComposition (T2V) via DashScope (not yet implemented).
     */
    private function mediaComposition(array $input, array $config): mixed
    {
        throw new \RuntimeException('DashScope MediaComposition is not yet implemented');
    }
}
