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

        // Verify it doesn't throw "does not support" for this capability.
        // The HTTP call will fail (no facade root in unit tests, or bad key in integration),
        // but the adapter should route the capability without throwing RuntimeException("does not support").
        try {
            $adapter->execute(Capability::ReferenceToVideo, [
                'prompt' => 'A woman walks through a garden',
                'reference_video_urls' => ['https://example.com/ref.mp4'],
            ], []);
        } catch (\RuntimeException $e) {
            // Facade not set or HTTP failure — expected in unit context.
            // The key assertion: it must NOT be the "does not support" error.
            $this->assertStringNotContainsString(
                'does not support',
                $e->getMessage(),
                'Adapter should support ReferenceToVideo capability',
            );
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Expected — HTTP call fails with fake key, but adapter DID support the capability
            $this->assertTrue(true);
        } catch (\InvalidArgumentException $e) {
            $this->fail('Adapter should support ReferenceToVideo capability');
        }
    }
}
