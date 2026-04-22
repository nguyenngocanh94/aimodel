<?php

declare(strict_types=1);

namespace App\Services\MediaProviders;

use Illuminate\Support\Facades\Http;

final readonly class DashScopeClient
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
    private const IMAGE_GENERATION_PATH = '/api/v1/services/aigc/image-generation/generation';
    private const TASK_PATH = '/api/v1/tasks';

    public function __construct(
        private string $apiKey,
        private ?string $model = null,
        private string $region = 'intl',
    ) {}

    public function getBaseUrl(): string
    {
        return self::REGION_ENDPOINT_MAP[$this->region]
            ?? throw new \InvalidArgumentException("Unknown DashScope region: {$this->region}");
    }

    public function mapAspectRatio(string $aspectRatio): string
    {
        return self::ASPECT_RATIO_MAP[$aspectRatio]
            ?? throw new \InvalidArgumentException("Unsupported aspect ratio: {$aspectRatio}");
    }

    /** @return array<string, mixed> */
    public function referenceToVideo(string $prompt, array $options = []): array
    {
        if ($prompt === '') {
            throw new \InvalidArgumentException('ReferenceToVideo requires a prompt');
        }

        $referenceUrls = (array) ($options['reference_urls'] ?? []);
        if ($referenceUrls === []) {
            throw new \InvalidArgumentException('ReferenceToVideo requires at least one reference URL');
        }

        $model = $this->model ?? ($options['model'] ?? 'wan2.6-r2v-flash');
        $payload = $this->buildR2vPayload($model, $prompt, $referenceUrls, $options);
        $taskId = $this->submitTask($payload);
        $result = $this->pollTask($taskId, $options);

        return [
            'video' => [
                'url' => $result['output']['video_url'] ?? '',
                'duration' => (float) ($result['output']['usage']['duration'] ?? 0),
            ],
            'seed' => $options['seed'] ?? null,
            'task_id' => $taskId,
        ];
    }

    /** @return array<string, mixed> */
    public function textToImage(string $prompt, array $options = []): array
    {
        $model = $this->model ?? ($options['model'] ?? 'wan2.6-t2i');

        $payload = [
            'model' => $model,
            'input' => [
                'messages' => [[
                    'role' => 'user',
                    'content' => [['text' => $prompt]],
                ]],
            ],
            'parameters' => [
                'size' => $options['size'] ?? '1024*1024',
                'n' => (int) ($options['n'] ?? 1),
                'prompt_extend' => $options['promptExtend'] ?? true,
                'watermark' => $options['watermark'] ?? false,
            ],
        ];

        if (!empty($options['negative_prompt'])) {
            $payload['parameters']['negative_prompt'] = $options['negative_prompt'];
        }

        if (isset($options['seed'])) {
            $payload['parameters']['seed'] = (int) $options['seed'];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'X-DashScope-Async' => 'enable',
        ])->timeout(30)->post($this->getBaseUrl() . self::IMAGE_GENERATION_PATH, $payload);

        $response->throw();

        $taskId = (string) $response->json('output.task_id');
        if ($taskId === '') {
            throw new \RuntimeException('DashScope image submit did not return a task_id: ' . $response->body());
        }

        $result = $this->pollTask($taskId, $options);

        return [
            'url' => $result['output']['choices'][0]['message']['content'][0]['image'] ?? '',
            'request_id' => $result['request_id'] ?? '',
            'task_id' => $taskId,
        ];
    }

    /** @return array<string, mixed> */
    public function buildR2vPayload(string $model, string $prompt, array $referenceUrls, array $options): array
    {
        $payload = [
            'model' => $model,
            'input' => [
                'prompt' => $prompt,
                'reference_urls' => $referenceUrls,
            ],
            'parameters' => [
                'size' => $this->mapAspectRatio((string) ($options['aspectRatio'] ?? '16:9')),
                'duration' => (int) ($options['duration'] ?? 5),
                'shot_type' => !empty($options['multiShots']) ? 'multi' : 'single',
                'audio' => $options['audio'] ?? true,
                'watermark' => $options['watermark'] ?? false,
            ],
        ];

        if (!empty($options['negative_prompt'])) {
            $payload['input']['negative_prompt'] = $options['negative_prompt'];
        }

        if (isset($options['seed'])) {
            $payload['parameters']['seed'] = (int) $options['seed'];
        }

        return $payload;
    }

    private function submitTask(array $payload): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'X-DashScope-Async' => 'enable',
        ])->timeout(30)->post($this->getBaseUrl() . self::VIDEO_SYNTHESIS_PATH, $payload);

        $response->throw();

        $taskId = (string) $response->json('output.task_id');
        if ($taskId === '') {
            throw new \RuntimeException('DashScope submit did not return a task_id');
        }

        return $taskId;
    }

    /** @return array<string, mixed> */
    private function pollTask(string $taskId, array $options): array
    {
        $pollInterval = (int) ($options['pollInterval'] ?? 5);
        $timeout = (int) ($options['timeout'] ?? 600);
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->timeout(30)->get($this->getBaseUrl() . self::TASK_PATH . "/{$taskId}");

            $response->throw();
            $data = (array) $response->json();
            $status = (string) ($data['output']['task_status'] ?? '');

            if ($status === 'SUCCEEDED') {
                return $data;
            }

            if (in_array($status, ['FAILED', 'CANCELED', 'UNKNOWN'], true)) {
                throw new \RuntimeException("DashScope task {$taskId} ended with status: {$status}");
            }

            sleep($pollInterval);
        }

        throw new \RuntimeException("DashScope task {$taskId} timed out after {$timeout} seconds");
    }
}
