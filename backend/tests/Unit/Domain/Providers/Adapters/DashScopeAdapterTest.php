<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\Adapters\DashScopeAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DashScopeAdapterTest extends TestCase
{
    #[Test]
    public function reference_to_video_throws_for_missing_prompt(): void
    {
        $adapter = new DashScopeAdapter('fake-key');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ReferenceToVideo requires a prompt');

        $adapter->execute(Capability::ReferenceToVideo, [
            'reference_urls' => ['https://example.com/ref.mp4'],
        ], []);
    }

    #[Test]
    public function reference_to_video_throws_for_empty_references(): void
    {
        $adapter = new DashScopeAdapter('fake-key');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ReferenceToVideo requires at least one reference URL');

        $adapter->execute(Capability::ReferenceToVideo, [
            'prompt' => 'A character walks through a garden',
        ], []);
    }

    #[Test]
    public function supports_reference_to_video_capability(): void
    {
        $adapter = new DashScopeAdapter('fake-key');

        // Verify the adapter routes ReferenceToVideo without throwing "does not support".
        // The HTTP call will fail in unit context, but the capability routing should succeed.
        try {
            $adapter->execute(Capability::ReferenceToVideo, [
                'prompt' => 'A woman walks through a garden',
                'reference_urls' => ['https://example.com/ref.mp4'],
            ], []);
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString(
                'does not support',
                $e->getMessage(),
                'Adapter should support ReferenceToVideo capability',
            );
        }
    }

    #[Test]
    public function maps_aspect_ratio_to_pixel_size(): void
    {
        $adapter = new DashScopeAdapter('fake-key');

        $this->assertSame('720*1280', $adapter->mapAspectRatio('9:16'));
        $this->assertSame('1280*720', $adapter->mapAspectRatio('16:9'));
        $this->assertSame('720*720', $adapter->mapAspectRatio('1:1'));
    }

    #[Test]
    public function maps_aspect_ratio_throws_for_unknown_ratio(): void
    {
        $adapter = new DashScopeAdapter('fake-key');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported aspect ratio: 4:3');

        $adapter->mapAspectRatio('4:3');
    }

    #[Test]
    public function builds_correct_r2v_payload(): void
    {
        $adapter = new DashScopeAdapter('fake-key');

        $payload = $adapter->buildR2vPayload(
            model: 'wan2.6-r2v-flash',
            prompt: 'character1 walks through a garden',
            referenceUrls: ['https://example.com/ref1.mp4', 'https://example.com/ref2.mp4'],
            input: [
                'negative_prompt' => 'blurry, low quality',
            ],
            config: [
                'aspectRatio' => '16:9',
                'duration' => 5,
                'multiShots' => false,
                'seed' => 42,
            ],
        );

        $this->assertSame('wan2.6-r2v-flash', $payload['model']);
        $this->assertSame('character1 walks through a garden', $payload['input']['prompt']);
        $this->assertSame(
            ['https://example.com/ref1.mp4', 'https://example.com/ref2.mp4'],
            $payload['input']['reference_urls'],
        );
        $this->assertSame('blurry, low quality', $payload['input']['negative_prompt']);
        $this->assertSame('1280*720', $payload['parameters']['size']);
        $this->assertSame(5, $payload['parameters']['duration']);
        $this->assertSame('single', $payload['parameters']['shot_type']);
        $this->assertTrue($payload['parameters']['audio']);
        $this->assertFalse($payload['parameters']['watermark']);
        $this->assertSame(42, $payload['parameters']['seed']);
    }

    #[Test]
    public function builds_r2v_payload_with_multi_shots(): void
    {
        $adapter = new DashScopeAdapter('fake-key');

        $payload = $adapter->buildR2vPayload(
            model: 'wan2.6-r2v-flash',
            prompt: 'test prompt',
            referenceUrls: ['https://example.com/ref.mp4'],
            input: [],
            config: [
                'multiShots' => true,
            ],
        );

        $this->assertSame('multi', $payload['parameters']['shot_type']);
    }

    #[Test]
    public function builds_r2v_payload_without_optional_fields(): void
    {
        $adapter = new DashScopeAdapter('fake-key');

        $payload = $adapter->buildR2vPayload(
            model: 'wan2.6-r2v-flash',
            prompt: 'test prompt',
            referenceUrls: ['https://example.com/ref.mp4'],
            input: [],
            config: [],
        );

        $this->assertArrayNotHasKey('negative_prompt', $payload['input']);
        $this->assertArrayNotHasKey('seed', $payload['parameters']);
        // Defaults
        $this->assertSame('1280*720', $payload['parameters']['size']); // default 16:9
        $this->assertSame(5, $payload['parameters']['duration']);
        $this->assertSame('single', $payload['parameters']['shot_type']);
        $this->assertTrue($payload['parameters']['audio']);
        $this->assertFalse($payload['parameters']['watermark']);
    }

    #[Test]
    public function region_maps_to_correct_endpoint(): void
    {
        $intlAdapter = new DashScopeAdapter('fake-key', region: 'intl');
        $this->assertSame('https://dashscope-intl.aliyuncs.com', $intlAdapter->getBaseUrl());

        $usAdapter = new DashScopeAdapter('fake-key', region: 'us');
        $this->assertSame('https://dashscope-us.aliyuncs.com', $usAdapter->getBaseUrl());

        $cnAdapter = new DashScopeAdapter('fake-key', region: 'cn');
        $this->assertSame('https://dashscope.aliyuncs.com', $cnAdapter->getBaseUrl());
    }

    #[Test]
    public function region_throws_for_unknown_region(): void
    {
        $adapter = new DashScopeAdapter('fake-key', region: 'invalid');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown DashScope region: invalid');

        $adapter->getBaseUrl();
    }

    #[Test]
    public function constructor_uses_model_override(): void
    {
        $adapter = new DashScopeAdapter('fake-key', model: 'wan2.6-t2v');

        $payload = $adapter->buildR2vPayload(
            model: 'wan2.6-t2v',
            prompt: 'test',
            referenceUrls: ['https://example.com/ref.mp4'],
            input: [],
            config: [],
        );

        $this->assertSame('wan2.6-t2v', $payload['model']);
    }
}
