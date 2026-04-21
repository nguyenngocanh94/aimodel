<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MediaProviders;

use App\Services\MediaProviders\DashScopeClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DashScopeClientTest extends TestCase
{
    #[Test]
    public function reference_to_video_throws_for_missing_prompt(): void
    {
        $client = new DashScopeClient('fake-key');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ReferenceToVideo requires a prompt');
        $client->referenceToVideo('', ['reference_urls' => ['https://example.com/ref.mp4']]);
    }

    #[Test]
    public function maps_aspect_ratio_to_pixel_size(): void
    {
        $client = new DashScopeClient('fake-key');

        $this->assertSame('720*1280', $client->mapAspectRatio('9:16'));
        $this->assertSame('1280*720', $client->mapAspectRatio('16:9'));
        $this->assertSame('720*720', $client->mapAspectRatio('1:1'));
    }
}
