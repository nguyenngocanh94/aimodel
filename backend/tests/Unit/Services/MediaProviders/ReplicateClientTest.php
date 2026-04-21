<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MediaProviders;

use App\Services\MediaProviders\ReplicateClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReplicateClientTest extends TestCase
{
    #[Test]
    public function text_to_image_polls_and_downloads_image(): void
    {
        Http::fake([
            'api.replicate.com/v1/predictions' => Http::response([
                'urls' => ['get' => 'https://api.replicate.com/v1/predictions/pred-1'],
            ]),
            'api.replicate.com/v1/predictions/pred-1' => Http::response([
                'status' => 'succeeded',
                'output' => ['https://cdn.example.com/image.png'],
            ]),
            'cdn.example.com/image.png' => Http::response('image-bytes'),
        ]);

        $client = new ReplicateClient('token');
        $bytes = $client->textToImage('hello');

        $this->assertSame('image-bytes', $bytes);
    }
}
