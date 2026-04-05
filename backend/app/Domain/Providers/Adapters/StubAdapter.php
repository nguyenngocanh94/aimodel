<?php

declare(strict_types=1);

namespace App\Domain\Providers\Adapters;

use App\Domain\Capability;
use App\Domain\Providers\ProviderContract;
use Illuminate\Support\Facades\Log;

class StubAdapter implements ProviderContract
{
    public function execute(Capability $capability, array $input, array $config): mixed
    {
        $startTime = hrtime(true);
        $seed = $this->deterministicSeed($input);

        $result = match ($capability) {
            Capability::TextGeneration => $this->textGeneration($input, $seed),
            Capability::TextToImage => $this->textToImage($seed),
            Capability::TextToSpeech => $this->textToSpeech($seed),
            Capability::StructuredTransform => $this->structuredTransform($input, $seed),
            Capability::MediaComposition => $this->mediaComposition($seed),
        };

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        Log::channel('providers')->info('Provider call completed', [
            'provider' => 'stub',
            'capability' => $capability->value,
            'duration_ms' => $durationMs,
        ]);

        return $result;
    }

    private function deterministicSeed(array $input): string
    {
        return hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
    }

    private function textGeneration(array $input, string $seed): array
    {
        $idx = hexdec(substr($seed, 0, 4)) % 3;

        $templates = [
            [
                'title' => 'The Journey Begins',
                'hook' => 'What if you could transform your ideas into reality?',
                'beats' => [
                    'Introduce the central concept',
                    'Show the transformation process',
                    'Reveal the stunning result',
                ],
                'narration' => 'In a world of endless possibilities, one tool stands above the rest.',
                'cta' => 'Start creating today.',
            ],
            [
                'title' => 'Behind the Scenes',
                'hook' => 'Ever wondered how the magic happens?',
                'beats' => [
                    'Set the stage with context',
                    'Walk through each step',
                    'Celebrate the outcome',
                ],
                'narration' => 'Every great creation starts with a single spark of inspiration.',
                'cta' => 'Join the creative revolution.',
            ],
            [
                'title' => 'A New Perspective',
                'hook' => 'See the world through a different lens.',
                'beats' => [
                    'Challenge conventional thinking',
                    'Present the alternative view',
                    'Connect with the audience',
                ],
                'narration' => 'Sometimes the best stories are the ones we never expected to tell.',
                'cta' => 'Discover what is possible.',
            ],
        ];

        return $templates[$idx];
    }

    private function textToImage(string $seed): string
    {
        // Minimal valid PNG: 8-byte signature + IHDR + IDAT + IEND
        $width = 64;
        $height = 64;

        // PNG signature
        $png = "\x89PNG\r\n\x1a\n";

        // IHDR chunk (13 bytes of data)
        $ihdr = pack('Nn', $width, $height) . "\x08\x02\x00\x00\x00"; // 8-bit RGB
        $png .= $this->pngChunk('IHDR', $ihdr);

        // IDAT chunk: fill with deterministic color based on seed
        $r = hexdec(substr($seed, 0, 2));
        $g = hexdec(substr($seed, 2, 2));
        $b = hexdec(substr($seed, 4, 2));

        $rawData = '';
        for ($y = 0; $y < $height; $y++) {
            $rawData .= "\x00"; // filter: none
            for ($x = 0; $x < $width; $x++) {
                $rawData .= chr($r) . chr($g) . chr($b);
            }
        }
        $compressed = gzcompress($rawData);
        $png .= $this->pngChunk('IDAT', $compressed);

        // IEND chunk
        $png .= $this->pngChunk('IEND', '');

        return $png;
    }

    private function pngChunk(string $type, string $data): string
    {
        $chunk = $type . $data;

        return pack('N', strlen($data)) . $chunk . pack('N', crc32($chunk));
    }

    private function textToSpeech(string $seed): string
    {
        // Minimal WAV header for a short silent audio clip
        $sampleRate = 22050;
        $bitsPerSample = 16;
        $channels = 1;
        $duration = 1; // 1 second
        $numSamples = $sampleRate * $duration;
        $dataSize = $numSamples * $channels * ($bitsPerSample / 8);

        $wav = 'RIFF';
        $wav .= pack('V', 36 + $dataSize); // file size - 8
        $wav .= 'WAVE';
        $wav .= 'fmt ';
        $wav .= pack('V', 16); // chunk size
        $wav .= pack('v', 1);  // PCM
        $wav .= pack('v', $channels);
        $wav .= pack('V', $sampleRate);
        $wav .= pack('V', $sampleRate * $channels * $bitsPerSample / 8); // byte rate
        $wav .= pack('v', $channels * $bitsPerSample / 8); // block align
        $wav .= pack('v', $bitsPerSample);
        $wav .= 'data';
        $wav .= pack('V', $dataSize);

        // Deterministic sine-like samples based on seed
        $freq = hexdec(substr($seed, 0, 4)) % 400 + 200; // 200-600 Hz
        for ($i = 0; $i < $numSamples; $i++) {
            $sample = (int) (sin(2 * M_PI * $freq * $i / $sampleRate) * 8000);
            $wav .= pack('v', $sample & 0xFFFF);
        }

        return $wav;
    }

    private function structuredTransform(array $input, string $seed): array
    {
        $idx = hexdec(substr($seed, 0, 4)) % 2;

        return match ($idx) {
            0 => [
                'scenes' => [
                    ['id' => 'scene-1', 'description' => 'Opening shot establishing context', 'duration' => 3.0],
                    ['id' => 'scene-2', 'description' => 'Main action sequence', 'duration' => 5.0],
                    ['id' => 'scene-3', 'description' => 'Closing with call to action', 'duration' => 2.0],
                ],
            ],
            1 => [
                'scenes' => [
                    ['id' => 'scene-1', 'description' => 'Wide angle landscape view', 'duration' => 4.0],
                    ['id' => 'scene-2', 'description' => 'Close-up detail shot', 'duration' => 3.0],
                    ['id' => 'scene-3', 'description' => 'Dynamic transition sequence', 'duration' => 3.0],
                    ['id' => 'scene-4', 'description' => 'Final reveal and branding', 'duration' => 2.0],
                ],
            ],
        };
    }

    private function mediaComposition(string $seed): array
    {
        return [
            'videoPath' => 'stub://composed-video-' . substr($seed, 0, 8) . '.mp4',
            'duration' => 15.0,
            'resolution' => '1920x1080',
            'format' => 'mp4',
            'codec' => 'h264',
        ];
    }
}
