<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MediaProviders;

use App\Services\MediaProviders\FalClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FalClientTest extends TestCase
{
    #[Test]
    public function reference_to_video_throws_for_missing_prompt(): void
    {
        $client = new FalClient('fake-key');

        $this->expectException(\InvalidArgumentException::class);
        $client->referenceToVideo('');
    }
}
