<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use App\Domain\Execution\PayloadHasher;
use App\Domain\Execution\RunCache;
use App\Models\RunCacheEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunCacheTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // PayloadHasher — determinism
    // ---------------------------------------------------------------

    public function test_hash_config_is_deterministic(): void
    {
        $config = ['model' => 'gpt-4', 'temperature' => 0.7, 'maxTokens' => 512];

        $hash1 = PayloadHasher::hashConfig($config);
        $hash2 = PayloadHasher::hashConfig($config);

        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1)); // SHA-256 hex length
    }

    public function test_hash_config_ignores_key_order(): void
    {
        $a = ['model' => 'gpt-4', 'temperature' => 0.7];
        $b = ['temperature' => 0.7, 'model' => 'gpt-4'];

        $this->assertSame(PayloadHasher::hashConfig($a), PayloadHasher::hashConfig($b));
    }

    public function test_different_configs_produce_different_hashes(): void
    {
        $a = ['model' => 'gpt-4', 'temperature' => 0.7];
        $b = ['model' => 'gpt-4', 'temperature' => 0.9];

        $this->assertNotSame(PayloadHasher::hashConfig($a), PayloadHasher::hashConfig($b));
    }

    // ---------------------------------------------------------------
    // PayloadHasher — volatile field stripping
    // ---------------------------------------------------------------

    public function test_hash_inputs_strips_volatile_fields(): void
    {
        $base = [
            'input1' => [
                'value' => 'hello world',
                'schemaType' => 'text',
                'status' => 'success',
            ],
        ];

        $withVolatile = [
            'input1' => [
                'value' => 'hello world',
                'schemaType' => 'text',
                'status' => 'success',
                'producedAt' => '2026-04-05T10:00:00+00:00',
                'sourceNodeId' => 'node-abc',
                'sourcePortKey' => 'output',
            ],
        ];

        $this->assertSame(
            PayloadHasher::hashInputs($base),
            PayloadHasher::hashInputs($withVolatile),
        );
    }

    public function test_hash_inputs_different_values_produce_different_hashes(): void
    {
        $a = [
            'input1' => [
                'value' => 'hello',
                'schemaType' => 'text',
                'status' => 'success',
            ],
        ];

        $b = [
            'input1' => [
                'value' => 'goodbye',
                'schemaType' => 'text',
                'status' => 'success',
            ],
        ];

        $this->assertNotSame(PayloadHasher::hashInputs($a), PayloadHasher::hashInputs($b));
    }

    // ---------------------------------------------------------------
    // RunCache — buildKey
    // ---------------------------------------------------------------

    public function test_build_key_is_deterministic(): void
    {
        $cache = new RunCache();

        $key1 = $cache->buildKey('prompt-refiner', '1.0.0', 1, ['model' => 'gpt-4'], []);
        $key2 = $cache->buildKey('prompt-refiner', '1.0.0', 1, ['model' => 'gpt-4'], []);

        $this->assertSame($key1, $key2);
        $this->assertSame(64, strlen($key1));
    }

    public function test_build_key_differs_for_different_schema_version(): void
    {
        $cache = new RunCache();

        $key1 = $cache->buildKey('prompt-refiner', '1.0.0', 1, ['model' => 'gpt-4'], []);
        $key2 = $cache->buildKey('prompt-refiner', '1.0.0', 2, ['model' => 'gpt-4'], []);

        $this->assertNotSame($key1, $key2);
    }

    // ---------------------------------------------------------------
    // RunCache — get / put
    // ---------------------------------------------------------------

    public function test_cache_miss_returns_null(): void
    {
        $cache = new RunCache();

        $this->assertNull($cache->get('nonexistent-key'));
    }

    public function test_put_then_get_returns_output_payloads(): void
    {
        $cache = new RunCache();

        $key = $cache->buildKey('prompt-refiner', '1.0.0', 1, ['model' => 'gpt-4'], []);

        $outputPayloads = [
            'refined' => [
                'value' => 'A polished prompt',
                'schemaType' => 'text',
                'status' => 'success',
            ],
        ];

        $cache->put($key, 'prompt-refiner', '1.0.0', $outputPayloads);

        $result = $cache->get($key);

        $this->assertNotNull($result);
        $this->assertEquals($outputPayloads, $result);
    }

    public function test_get_updates_last_accessed_at(): void
    {
        $cache = new RunCache();

        $key = 'test-access-key';

        $cache->put($key, 'prompt-refiner', '1.0.0', ['out' => 'data']);

        $before = RunCacheEntry::where('cache_key', $key)->first()->last_accessed_at;

        // Move time forward to ensure a visible difference.
        $this->travel(5)->minutes();

        $cache->get($key);

        $after = RunCacheEntry::where('cache_key', $key)->first()->last_accessed_at;

        $this->assertTrue($after->greaterThan($before));
    }

    public function test_put_upserts_existing_entry(): void
    {
        $cache = new RunCache();

        $key = 'upsert-key';

        $cache->put($key, 'prompt-refiner', '1.0.0', ['out' => 'v1']);
        $cache->put($key, 'prompt-refiner', '1.0.0', ['out' => 'v2']);

        $this->assertCount(1, RunCacheEntry::where('cache_key', $key)->get());
        $this->assertSame(['out' => 'v2'], $cache->get($key));
    }
}
