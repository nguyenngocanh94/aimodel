# Wan R2V Integration — First Real Video Generation

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Wire up Wan 2.7 R2V model via Fal API so the workflow engine can generate real videos from reference videos + prompts.

**Architecture:** Add a new `ReferenceToVideo` capability, extend `FalAdapter` to call `fal-ai/wan/v2.7/reference-to-video`, create a `WanR2VTemplate` node template that accepts reference video URLs + prompt and outputs a video artifact. Both backend (Laravel) and frontend (React) need the new node registered.

**Tech Stack:** PHP 8.3 / Laravel 13 (backend), TypeScript / React (frontend), Fal.ai API, PHPUnit, Vitest

---

### Task 1: Add `ReferenceToVideo` capability enum

**Files:**
- Modify: `backend/app/Domain/Capability.php`
- Test: `backend/tests/Unit/Domain/DomainEnumTest.php`

**Step 1: Write the failing test**

Add to `DomainEnumTest.php`:

```php
#[Test]
public function capability_includes_reference_to_video(): void
{
    $cap = Capability::ReferenceToVideo;
    $this->assertSame('reference_to_video', $cap->value);
}
```

**Step 2: Run test to verify it fails**

Run: `docker exec aimodel-backend php artisan test --filter=capability_includes_reference_to_video`
Expected: FAIL — undefined enum case

**Step 3: Write minimal implementation**

In `backend/app/Domain/Capability.php`, add:

```php
case ReferenceToVideo = 'reference_to_video';
```

**Step 4: Run test to verify it passes**

Run: `docker exec aimodel-backend php artisan test --filter=capability_includes_reference_to_video`
Expected: PASS

**Step 5: Commit**

```bash
git add backend/app/Domain/Capability.php backend/tests/Unit/Domain/DomainEnumTest.php
git commit -m "feat: add ReferenceToVideo capability enum"
```

---

### Task 2: Add `VideoUrl` and `VideoUrlList` data types

**Files:**
- Modify: `backend/app/Domain/DataType.php`
- Test: `backend/tests/Unit/Domain/DomainEnumTest.php`

**Step 1: Write the failing test**

```php
#[Test]
public function data_type_includes_video_url_types(): void
{
    $this->assertSame('videoUrl', DataType::VideoUrl->value);
    $this->assertSame('videoUrlList', DataType::VideoUrlList->value);
}
```

**Step 2: Run test to verify it fails**

Run: `docker exec aimodel-backend php artisan test --filter=data_type_includes_video_url_types`
Expected: FAIL

**Step 3: Write minimal implementation**

In `backend/app/Domain/DataType.php`, add:

```php
case VideoUrl = 'videoUrl';
case VideoUrlList = 'videoUrlList';
```

**Step 4: Run test to verify it passes**

Run: `docker exec aimodel-backend php artisan test --filter=data_type_includes_video_url_types`
Expected: PASS

**Step 5: Commit**

```bash
git add backend/app/Domain/DataType.php backend/tests/Unit/Domain/DomainEnumTest.php
git commit -m "feat: add VideoUrl and VideoUrlList data types"
```

---

### Task 3: Extend FalAdapter with R2V support

**Files:**
- Modify: `backend/app/Domain/Providers/Adapters/FalAdapter.php`
- Create: `backend/tests/Unit/Domain/Providers/Adapters/FalAdapterTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\Adapters\FalAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FalAdapterTest extends TestCase
{
    #[Test]
    public function reference_to_video_throws_for_missing_prompt(): void
    {
        $adapter = new FalAdapter('fake-key');

        $this->expectException(\InvalidArgumentException::class);
        $adapter->execute(Capability::ReferenceToVideo, [], []);
    }

    #[Test]
    public function reference_to_video_builds_correct_payload(): void
    {
        // We can't call the real API, but we can test the adapter accepts the capability
        // and validates inputs. The real HTTP call is tested via integration/feature tests.
        $adapter = new FalAdapter('fake-key');

        // Verify it doesn't throw "does not support" for this capability
        try {
            $adapter->execute(Capability::ReferenceToVideo, [
                'prompt' => 'A woman walks through a garden',
                'reference_video_urls' => ['https://example.com/ref.mp4'],
            ], []);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Expected — HTTP call fails with fake key, but adapter DID support the capability
            $this->assertTrue(true);
        } catch (\InvalidArgumentException $e) {
            $this->fail('Adapter should support ReferenceToVideo capability');
        }
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker exec aimodel-backend php artisan test --filter=FalAdapterTest`
Expected: FAIL — FalAdapter doesn't handle ReferenceToVideo

**Step 3: Write implementation**

Replace `FalAdapter.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\ProviderContract;
use Illuminate\Support\Facades\Http;

class FalAdapter implements ProviderContract
{
    public function __construct(
        private string $apiKey,
        private ?string $model = null,
    ) {}

    public function execute(Capability $capability, array $input, array $config): mixed
    {
        return match ($capability) {
            Capability::TextToImage => $this->textToImage($input, $config),
            Capability::MediaComposition => $this->mediaComposition($input, $config),
            Capability::ReferenceToVideo => $this->referenceToVideo($input, $config),
            default => throw new \RuntimeException("Fal adapter does not support: {$capability->value}"),
        };
    }

    private function referenceToVideo(array $input, array $config): array
    {
        $prompt = $input['prompt'] ?? null;
        if (!$prompt) {
            throw new \InvalidArgumentException('ReferenceToVideo requires a prompt');
        }

        $model = $this->model ?? $config['model'] ?? 'fal-ai/wan/v2.7/reference-to-video';

        $payload = [
            'prompt' => $prompt,
            'aspect_ratio' => $config['aspectRatio'] ?? '9:16',
            'resolution' => $config['resolution'] ?? '1080p',
            'duration' => $config['duration'] ?? '5',
            'multi_shots' => $config['multiShots'] ?? false,
        ];

        if (!empty($input['reference_video_urls'])) {
            $payload['reference_video_urls'] = $input['reference_video_urls'];
        }

        if (!empty($input['reference_image_urls'])) {
            $payload['reference_image_urls'] = $input['reference_image_urls'];
        }

        if (!empty($input['negative_prompt'])) {
            $payload['negative_prompt'] = $input['negative_prompt'];
        }

        if (isset($config['seed'])) {
            $payload['seed'] = (int) $config['seed'];
        }

        $response = Http::withHeaders([
            'Authorization' => "Key {$this->apiKey}",
        ])->timeout(300)->post("https://fal.run/{$model}", $payload);

        $response->throw();

        return $response->json();
    }

    private function textToImage(array $input, array $config): string
    {
        $model = $this->model ?? $config['model'] ?? 'fal-ai/flux/dev';
        $response = Http::withHeaders([
            'Authorization' => "Key {$this->apiKey}",
        ])->post("https://fal.run/{$model}", [
            'prompt' => $input['prompt'] ?? '',
            'image_size' => $config['imageSize'] ?? 'landscape_16_9',
            'num_images' => 1,
        ]);

        $response->throw();

        $imageUrl = $response->json('images.0.url', '');

        return Http::get($imageUrl)->body();
    }

    private function mediaComposition(array $input, array $config): string
    {
        $model = $this->model ?? $config['model'] ?? 'fal-ai/video-composer';
        $response = Http::withHeaders([
            'Authorization' => "Key {$this->apiKey}",
        ])->post("https://fal.run/{$model}", [
            'scenes' => $input['scenes'] ?? [],
            'audio' => $input['audio'] ?? null,
            'subtitles' => $input['subtitles'] ?? null,
        ]);

        $response->throw();

        $videoUrl = $response->json('video.url', '');

        return Http::get($videoUrl)->body();
    }
}
```

**Step 4: Run test to verify it passes**

Run: `docker exec aimodel-backend php artisan test --filter=FalAdapterTest`
Expected: PASS

**Step 5: Commit**

```bash
git add backend/app/Domain/Providers/Adapters/FalAdapter.php backend/tests/Unit/Domain/Providers/Adapters/FalAdapterTest.php
git commit -m "feat: extend FalAdapter with Wan R2V support"
```

---

### Task 4: Update ProviderRouter to route ReferenceToVideo

**Files:**
- Modify: `backend/app/Domain/Providers/ProviderRouter.php` (no changes needed — it already routes by driver, not capability)
- Modify: `backend/app/Domain/Providers/Adapters/StubAdapter.php` (add ReferenceToVideo stub)
- Modify: `backend/tests/Unit/Domain/Providers/Adapters/StubAdapterTest.php`

**Step 1: Write the failing test**

Add to `StubAdapterTest.php`:

```php
#[Test]
public function reference_to_video_returns_stub_data(): void
{
    $adapter = new StubAdapter();

    $result = $adapter->execute(
        Capability::ReferenceToVideo,
        ['prompt' => 'A woman walks in a garden', 'reference_video_urls' => ['https://example.com/ref.mp4']],
        [],
    );

    $this->assertIsArray($result);
    $this->assertArrayHasKey('video', $result);
    $this->assertArrayHasKey('url', $result['video']);
    $this->assertArrayHasKey('duration', $result['video']);
}
```

**Step 2: Run test to verify it fails**

Run: `docker exec aimodel-backend php artisan test --filter=reference_to_video_returns_stub_data`
Expected: FAIL — StubAdapter doesn't handle ReferenceToVideo

**Step 3: Write implementation**

In `StubAdapter.php`, add to the `execute` match:

```php
Capability::ReferenceToVideo => $this->referenceToVideo($seed),
```

And add the method:

```php
private function referenceToVideo(string $seed): array
{
    return [
        'video' => [
            'url' => 'stub://r2v-video-' . substr($seed, 0, 8) . '.mp4',
            'content_type' => 'video/mp4',
            'file_name' => 'r2v-video-' . substr($seed, 0, 8) . '.mp4',
            'file_size' => 2_500_000,
            'width' => 1080,
            'height' => 1920,
            'fps' => 24.0,
            'duration' => 5.0,
            'num_frames' => 120,
        ],
        'seed' => hexdec(substr($seed, 0, 8)),
        'actual_prompt' => 'Stub R2V prompt expansion',
    ];
}
```

**Step 4: Run test to verify it passes**

Run: `docker exec aimodel-backend php artisan test --filter=reference_to_video_returns_stub_data`
Expected: PASS

**Step 5: Commit**

```bash
git add backend/app/Domain/Providers/Adapters/StubAdapter.php backend/tests/Unit/Domain/Providers/Adapters/StubAdapterTest.php
git commit -m "feat: add ReferenceToVideo stub for development/testing"
```

---

### Task 5: Create WanR2VTemplate node

**Files:**
- Create: `backend/app/Domain/Nodes/Templates/WanR2VTemplate.php`
- Create: `backend/tests/Unit/Domain/Nodes/Templates/WanR2VTemplateTest.php`
- Modify: `backend/app/Providers/NodeTemplateServiceProvider.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\WanR2VTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\Adapters\StubAdapter;
use App\Domain\Providers\ProviderRouter;
use App\Models\Artifact;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WanR2VTemplateTest extends TestCase
{
    private WanR2VTemplate $template;

    protected function setUp(): void
    {
        $this->template = new WanR2VTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('wanR2V', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame('Wan R2V', $this->template->title);
        $this->assertSame(NodeCategory::Video, $this->template->category);
    }

    #[Test]
    public function ports_define_prompt_and_references_as_input_video_as_output(): void
    {
        $ports = $this->template->ports();

        $inputKeys = array_map(fn ($p) => $p->key, $ports->inputs);
        $this->assertContains('prompt', $inputKeys);
        $this->assertContains('referenceVideos', $inputKeys);

        $outputKeys = array_map(fn ($p) => $p->key, $ports->outputs);
        $this->assertContains('video', $outputKeys);
    }

    #[Test]
    public function default_config_uses_stub_provider(): void
    {
        $config = $this->template->defaultConfig();

        $this->assertSame('stub', $config['provider']);
        $this->assertSame('9:16', $config['aspectRatio']);
        $this->assertSame('1080p', $config['resolution']);
    }

    #[Test]
    public function execute_with_stub_returns_video_asset(): void
    {
        $router = $this->createMock(ProviderRouter::class);
        $router->method('resolve')
            ->with(Capability::ReferenceToVideo, $this->anything())
            ->willReturn(new StubAdapter());

        $store = $this->createMock(ArtifactStoreContract::class);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-r2v',
            config: $this->template->defaultConfig(),
            inputs: [
                'prompt' => PortPayload::success(
                    'A young woman walks through a Saigon street market, golden hour',
                    DataType::Text,
                ),
                'referenceVideos' => PortPayload::success(
                    ['https://example.com/linh-ref.mp4'],
                    DataType::VideoUrlList,
                ),
            ],
            runId: 'run-r2v-1',
            providerRouter: $router,
            artifactStore: $store,
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('video', $result);
        $this->assertTrue($result['video']->isSuccess());
        $this->assertSame(DataType::VideoAsset, $result['video']->schemaType);
        $this->assertArrayHasKey('url', $result['video']->value);
        $this->assertArrayHasKey('duration', $result['video']->value);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker exec aimodel-backend php artisan test --filter=WanR2VTemplateTest`
Expected: FAIL — class not found

**Step 3: Write implementation**

Create `backend/app/Domain/Nodes/Templates/WanR2VTemplate.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;

class WanR2VTemplate extends NodeTemplate
{
    public string $type { get => 'wanR2V'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Wan R2V'; }
    public NodeCategory $category { get => NodeCategory::Video; }
    public string $description { get => 'Generates video from reference videos/images using Wan 2.7 R2V. Preserves character identity across scenes. Supports multi-shot and sound.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('prompt', 'Prompt', DataType::Text, description: 'Multi-shot prompt with character tags and scene directions'),
                PortDefinition::input('referenceVideos', 'Reference Videos', DataType::VideoUrlList, required: false, description: 'Up to 3 character reference video URLs'),
                PortDefinition::input('referenceImages', 'Reference Images', DataType::ImageAssetList, required: false, description: 'Character reference image URLs'),
            ],
            outputs: [
                PortDefinition::output('video', 'Video', DataType::VideoAsset, description: 'Generated video with preserved character identity'),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'provider' => ['required', 'string'],
            'apiKey' => ['sometimes', 'string'],
            'model' => ['sometimes', 'string'],
            'aspectRatio' => ['sometimes', 'string', 'in:16:9,9:16,1:1,4:3,3:4'],
            'resolution' => ['sometimes', 'string', 'in:720p,1080p'],
            'duration' => ['sometimes', 'string', 'in:2,3,4,5,6,7,8,9,10'],
            'multiShots' => ['sometimes', 'boolean'],
            'seed' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'provider' => 'stub',
            'apiKey' => '',
            'model' => 'fal-ai/wan/v2.7/reference-to-video',
            'aspectRatio' => '9:16',
            'resolution' => '1080p',
            'duration' => '5',
            'multiShots' => false,
            'seed' => null,
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $prompt = $ctx->inputValue('prompt') ?? '';
        if (is_array($prompt)) {
            $prompt = $prompt['text'] ?? json_encode($prompt);
        }

        $referenceVideos = $ctx->inputValue('referenceVideos') ?? [];
        $referenceImages = $ctx->inputValue('referenceImages') ?? [];

        $input = ['prompt' => $prompt];

        if (!empty($referenceVideos)) {
            $input['reference_video_urls'] = is_array($referenceVideos) ? $referenceVideos : [$referenceVideos];
        }

        if (!empty($referenceImages)) {
            $urls = [];
            foreach ((array) $referenceImages as $img) {
                if (is_string($img)) {
                    $urls[] = $img;
                } elseif (is_array($img) && isset($img['url'])) {
                    $urls[] = $img['url'];
                }
            }
            if ($urls) {
                $input['reference_image_urls'] = $urls;
            }
        }

        $result = $ctx->provider(Capability::ReferenceToVideo)->execute(
            Capability::ReferenceToVideo,
            $input,
            $ctx->config,
        );

        $videoData = $result['video'] ?? $result;
        $url = is_array($videoData) ? ($videoData['url'] ?? '') : (string) $videoData;
        $duration = is_array($videoData) ? ($videoData['duration'] ?? 5.0) : 5.0;

        return [
            'video' => PortPayload::success(
                value: [
                    'url' => $url,
                    'duration' => $duration,
                    'resolution' => $ctx->config['resolution'] ?? '1080p',
                    'aspectRatio' => $ctx->config['aspectRatio'] ?? '9:16',
                    'seed' => $result['seed'] ?? null,
                ],
                schemaType: DataType::VideoAsset,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'video',
                previewText: "R2V video · {$duration}s",
            ),
        ];
    }
}
```

**Step 4: Register in NodeTemplateServiceProvider**

In `backend/app/Providers/NodeTemplateServiceProvider.php`, add:

```php
use App\Domain\Nodes\Templates\WanR2VTemplate;
```

And in `boot()`:

```php
$registry->register(new WanR2VTemplate());
```

**Step 5: Run test to verify it passes**

Run: `docker exec aimodel-backend php artisan test --filter=WanR2VTemplateTest`
Expected: PASS

**Step 6: Run full test suite**

Run: `docker exec aimodel-backend php artisan test`
Expected: All existing tests still PASS

**Step 7: Commit**

```bash
git add backend/app/Domain/Nodes/Templates/WanR2VTemplate.php backend/tests/Unit/Domain/Nodes/Templates/WanR2VTemplateTest.php backend/app/Providers/NodeTemplateServiceProvider.php
git commit -m "feat: add WanR2V node template for Wan 2.7 reference-to-video"
```

---

### Task 6: Create frontend WanR2V template

**Files:**
- Create: `frontend/src/features/node-registry/templates/wan-r2v.ts`
- Create: `frontend/src/features/node-registry/templates/wan-r2v.test.ts`
- Modify: `frontend/src/features/node-registry/templates/index.ts`
- Modify: `frontend/src/features/node-registry/node-registry.ts`

**Step 1: Write the failing test**

Create `frontend/src/features/node-registry/templates/wan-r2v.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { wanR2VTemplate } from './wan-r2v';

describe('wanR2VTemplate', () => {
  it('has correct type and category', () => {
    expect(wanR2VTemplate.type).toBe('wanR2V');
    expect(wanR2VTemplate.category).toBe('video');
  });

  it('defines prompt and referenceVideos inputs', () => {
    const inputKeys = wanR2VTemplate.inputs.map((p) => p.key);
    expect(inputKeys).toContain('prompt');
    expect(inputKeys).toContain('referenceVideos');
  });

  it('defines video output', () => {
    const outputKeys = wanR2VTemplate.outputs.map((p) => p.key);
    expect(outputKeys).toContain('video');
  });

  it('default config uses stub provider with 9:16 aspect', () => {
    expect(wanR2VTemplate.defaultConfig.provider).toBe('stub');
    expect(wanR2VTemplate.defaultConfig.aspectRatio).toBe('9:16');
    expect(wanR2VTemplate.defaultConfig.resolution).toBe('1080p');
  });

  it('config schema validates valid config', () => {
    const result = wanR2VTemplate.configSchema.safeParse({
      provider: 'fal',
      aspectRatio: '9:16',
      resolution: '1080p',
      duration: '5',
      multiShots: true,
    });
    expect(result.success).toBe(true);
  });

  it('buildPreview returns idle video output', () => {
    const preview = wanR2VTemplate.buildPreview({
      config: wanR2VTemplate.defaultConfig,
      inputs: {},
    });
    expect(preview.video).toBeDefined();
    expect(preview.video.status).toBe('idle');
  });
});
```

**Step 2: Run test to verify it fails**

Run: `cd frontend && npm test -- --run --filter=wan-r2v`
Expected: FAIL — module not found

**Step 3: Write implementation**

Create `frontend/src/features/node-registry/templates/wan-r2v.ts`:

```typescript
import { z } from 'zod';
import type { NodeTemplate } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

interface WanR2VConfig {
  provider: string;
  apiKey: string;
  model: string;
  aspectRatio: string;
  resolution: string;
  duration: string;
  multiShots: boolean;
  seed: number | null;
}

const configSchema = z.object({
  provider: z.string().default('stub'),
  apiKey: z.string().default(''),
  model: z.string().default('fal-ai/wan/v2.7/reference-to-video'),
  aspectRatio: z.enum(['16:9', '9:16', '1:1', '4:3', '3:4']).default('9:16'),
  resolution: z.enum(['720p', '1080p']).default('1080p'),
  duration: z.enum(['2', '3', '4', '5', '6', '7', '8', '9', '10']).default('5'),
  multiShots: z.boolean().default(false),
  seed: z.number().int().min(0).max(2147483647).nullable().default(null),
});

const inputs: readonly PortDefinition[] = [
  { key: 'prompt', label: 'Prompt', direction: 'input', dataType: 'text', required: true, multiple: false },
  { key: 'referenceVideos', label: 'Reference Videos', direction: 'input', dataType: 'videoUrlList', required: false, multiple: false },
  { key: 'referenceImages', label: 'Reference Images', direction: 'input', dataType: 'imageAssetList', required: false, multiple: false },
];

const outputs: readonly PortDefinition[] = [
  { key: 'video', label: 'Video', direction: 'output', dataType: 'videoAsset', required: false, multiple: false },
];

const idlePayload = (dataType: string): PortPayload => ({
  value: null,
  status: 'idle',
  schemaType: dataType,
});

export const wanR2VTemplate: NodeTemplate<WanR2VConfig> = {
  type: 'wanR2V',
  templateVersion: '1.0.0',
  title: 'Wan R2V',
  category: 'video',
  description: 'Generates video from reference videos/images using Wan 2.7 R2V. Preserves character identity across scenes. Supports multi-shot and sound.',
  inputs,
  outputs,
  defaultConfig: {
    provider: 'stub',
    apiKey: '',
    model: 'fal-ai/wan/v2.7/reference-to-video',
    aspectRatio: '9:16',
    resolution: '1080p',
    duration: '5',
    multiShots: false,
    seed: null,
  },
  configSchema,
  fixtures: [
    {
      id: 'wan-r2v-tiktok-demo',
      label: 'TikTok TVC Demo',
      config: {
        provider: 'stub',
        aspectRatio: '9:16',
        resolution: '1080p',
        duration: '5',
        multiShots: true,
      },
    },
  ],
  executable: true,
  mockExecute: async ({ nodeId, config }) => ({
    video: {
      value: {
        url: `stub://r2v-video-${nodeId}.mp4`,
        duration: Number(config.duration),
        resolution: config.resolution,
        aspectRatio: config.aspectRatio,
        seed: 42,
      },
      status: 'success' as const,
      schemaType: 'videoAsset',
      producedAt: new Date().toISOString(),
      sourceNodeId: nodeId,
      sourcePortKey: 'video',
      previewText: `R2V video · ${config.duration}s`,
    },
  }),
  buildPreview: () => ({
    video: idlePayload('videoAsset'),
  }),
};
```

**Step 4: Export from templates/index.ts**

Add to `frontend/src/features/node-registry/templates/index.ts`:

```typescript
export { wanR2VTemplate } from './wan-r2v';
```

**Step 5: Register in node-registry.ts**

Import and register:

```typescript
import { wanR2VTemplate } from './templates';
// In registerAllNodeTemplates():
registry.register(wanR2VTemplate);
```

**Step 6: Run test to verify it passes**

Run: `cd frontend && npm test -- --run --filter=wan-r2v`
Expected: PASS

**Step 7: Run full frontend test suite**

Run: `cd frontend && npm test -- --run`
Expected: All PASS

**Step 8: Commit**

```bash
git add frontend/src/features/node-registry/templates/wan-r2v.ts frontend/src/features/node-registry/templates/wan-r2v.test.ts frontend/src/features/node-registry/templates/index.ts frontend/src/features/node-registry/node-registry.ts
git commit -m "feat: add WanR2V frontend node template"
```

---

### Task 7: Add `videoUrlList` to frontend workflow-types

**Files:**
- Modify: `frontend/src/features/workflows/domain/workflow-types.ts` (or wherever DataType is defined)

Check if `videoUrl` and `videoUrlList` already exist in the frontend type definitions. If not, add them to the DataType union. This ensures the frontend type system accepts the new port types.

Run the frontend typecheck after:

```bash
cd frontend && npm run typecheck
```

**Commit** if changes needed.

---

### Task 8: Smoke test — end to end with stub

**Steps:**

1. Start the backend: `docker exec aimodel-backend php artisan serve`
2. Start the frontend: `cd frontend && npm run dev`
3. Open the UI, create a new workflow
4. Drag a "User Prompt" node → connect to "Wan R2V" node
5. Configure Wan R2V with provider: stub
6. Run the workflow
7. Verify video output appears in the data inspector

This is a manual validation step. If it works with stub, the pipeline is ready for real Fal API keys.

---

## Summary

| Task | What | Files Changed |
|------|------|---------------|
| 1 | Add `ReferenceToVideo` capability | `Capability.php`, `DomainEnumTest.php` |
| 2 | Add `VideoUrl`/`VideoUrlList` data types | `DataType.php`, `DomainEnumTest.php` |
| 3 | Extend FalAdapter with R2V | `FalAdapter.php`, `FalAdapterTest.php` (new) |
| 4 | Add R2V stub for dev/testing | `StubAdapter.php`, `StubAdapterTest.php` |
| 5 | Create WanR2VTemplate backend | `WanR2VTemplate.php` (new), test (new), `ServiceProvider.php` |
| 6 | Create WanR2V frontend template | `wan-r2v.ts` (new), test (new), `index.ts`, `node-registry.ts` |
| 7 | Add videoUrlList to frontend types | `workflow-types.ts` |
| 8 | Smoke test end to end | Manual validation |

**After this plan:** You'll have a working WanR2V node that generates real video when configured with a Fal API key, or returns stub data for development. Ready to wire into the full TVC pipeline.
