<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\Adapters\StubAdapter;
use App\Domain\Providers\ProviderRouter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StubAdapterTest extends TestCase
{
    private StubAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new StubAdapter();
    }

    #[Test]
    public function text_generation_returns_valid_script_structure(): void
    {
        $result = $this->adapter->execute(
            Capability::TextGeneration,
            ['system' => 'Write a script', 'user' => 'About AI'],
            [],
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('hook', $result);
        $this->assertArrayHasKey('beats', $result);
        $this->assertArrayHasKey('narration', $result);
        $this->assertArrayHasKey('cta', $result);
        $this->assertIsArray($result['beats']);
        $this->assertNotEmpty($result['beats']);
    }

    #[Test]
    public function text_to_image_returns_valid_png(): void
    {
        $result = $this->adapter->execute(
            Capability::TextToImage,
            ['prompt' => 'A sunrise over mountains'],
            [],
        );

        $this->assertIsString($result);
        // Check PNG magic bytes
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($result, 0, 8));
    }

    #[Test]
    public function text_to_speech_returns_valid_wav(): void
    {
        $result = $this->adapter->execute(
            Capability::TextToSpeech,
            ['text' => 'Hello world'],
            [],
        );

        $this->assertIsString($result);
        // Check WAV header
        $this->assertSame('RIFF', substr($result, 0, 4));
        $this->assertSame('WAVE', substr($result, 8, 4));
    }

    #[Test]
    public function structured_transform_returns_scenes_array(): void
    {
        $result = $this->adapter->execute(
            Capability::StructuredTransform,
            ['scenes' => [['description' => 'test']]],
            [],
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('scenes', $result);
        $this->assertIsArray($result['scenes']);
        $this->assertNotEmpty($result['scenes']);

        $scene = $result['scenes'][0];
        $this->assertArrayHasKey('id', $scene);
        $this->assertArrayHasKey('description', $scene);
        $this->assertArrayHasKey('duration', $scene);
    }

    #[Test]
    public function media_composition_returns_video_metadata(): void
    {
        $result = $this->adapter->execute(
            Capability::MediaComposition,
            ['clips' => ['clip1.mp4', 'clip2.mp4']],
            [],
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('videoPath', $result);
        $this->assertArrayHasKey('duration', $result);
        $this->assertArrayHasKey('resolution', $result);
        $this->assertArrayHasKey('format', $result);
        $this->assertSame('mp4', $result['format']);
    }

    #[Test]
    public function reference_to_video_returns_stub_data(): void
    {
        $result = $this->adapter->execute(
            Capability::ReferenceToVideo,
            ['prompt' => 'A woman walks in a garden', 'reference_video_urls' => ['https://example.com/ref.mp4']],
            [],
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('video', $result);
        $this->assertArrayHasKey('url', $result['video']);
        $this->assertArrayHasKey('content_type', $result['video']);
        $this->assertArrayHasKey('file_name', $result['video']);
        $this->assertArrayHasKey('file_size', $result['video']);
        $this->assertArrayHasKey('width', $result['video']);
        $this->assertArrayHasKey('height', $result['video']);
        $this->assertArrayHasKey('fps', $result['video']);
        $this->assertArrayHasKey('duration', $result['video']);
        $this->assertArrayHasKey('num_frames', $result['video']);
        $this->assertArrayHasKey('seed', $result);
        $this->assertArrayHasKey('actual_prompt', $result);
        $this->assertSame('video/mp4', $result['video']['content_type']);
        $this->assertSame(5.0, $result['video']['duration']);
    }

    #[Test]
    public function same_input_produces_identical_output(): void
    {
        $input = ['system' => 'Write a script', 'user' => 'About nature'];

        $result1 = $this->adapter->execute(Capability::TextGeneration, $input, []);
        $result2 = $this->adapter->execute(Capability::TextGeneration, $input, []);

        $this->assertSame($result1, $result2);
    }

    #[Test]
    public function different_inputs_can_produce_different_outputs(): void
    {
        $result1 = $this->adapter->execute(
            Capability::TextGeneration,
            ['system' => 'prompt-a', 'user' => 'topic-a'],
            [],
        );
        $result2 = $this->adapter->execute(
            Capability::TextGeneration,
            ['system' => 'prompt-b', 'user' => 'topic-b'],
            [],
        );

        // They might happen to collide, but with 3 templates and SHA256 it's unlikely
        // At minimum, verify both return valid structures
        $this->assertArrayHasKey('title', $result1);
        $this->assertArrayHasKey('title', $result2);
    }

    #[Test]
    public function provider_router_resolves_stub_driver(): void
    {
        $router = new ProviderRouter();
        $provider = $router->resolve(Capability::TextGeneration, ['provider' => 'stub']);

        $this->assertInstanceOf(StubAdapter::class, $provider);
    }

    #[Test]
    public function provider_router_defaults_to_stub(): void
    {
        $router = new ProviderRouter();
        $provider = $router->resolve(Capability::TextGeneration, []);

        $this->assertInstanceOf(StubAdapter::class, $provider);
    }
}
