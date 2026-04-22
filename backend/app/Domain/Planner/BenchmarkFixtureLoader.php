<?php

declare(strict_types=1);

namespace App\Domain\Planner;

use RuntimeException;

/**
 * Loads {@see BenchmarkFixture} records from PHP files in
 * tests/Fixtures/PlannerBenchmarks. Each fixture file returns a single
 * associative array matching {@see BenchmarkFixture::fromArray()}.
 *
 * The loader is used by:
 * - aimodel-645.5 drift-eval — iterates fixtures, runs the planner, scores
 *   outputs against expectedCharacteristics + antiPatterns.
 * - aimodel-645.8 integration tests — iterates fixtures, asserts expectedNodes
 *   are present / forbiddenNodes absent / knob values resolved correctly.
 */
final class BenchmarkFixtureLoader
{
    private ?array $cache = null;

    public function __construct(private readonly ?string $fixtureDir = null) {}

    private function dir(): string
    {
        return $this->fixtureDir ?? base_path('tests/Fixtures/PlannerBenchmarks');
    }

    /**
     * @return array<string, BenchmarkFixture> Keyed by fixture id.
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $dir = $this->dir();
        if (!is_dir($dir)) {
            throw new RuntimeException("Fixture directory not found: {$dir}");
        }

        $fixtures = [];
        foreach (glob($dir . '/*.php') as $file) {
            $data = require $file;
            if (!is_array($data)) {
                throw new RuntimeException("Fixture file {$file} must return an array, got " . gettype($data));
            }
            $fixture = BenchmarkFixture::fromArray($data);
            if (isset($fixtures[$fixture->id])) {
                throw new RuntimeException("Duplicate fixture id '{$fixture->id}' in {$file}");
            }
            $fixtures[$fixture->id] = $fixture;
        }

        return $this->cache = $fixtures;
    }

    public function byId(string $id): ?BenchmarkFixture
    {
        return $this->all()[$id] ?? null;
    }

    /**
     * @return list<BenchmarkFixture>
     */
    public function byVibeMode(string $mode): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (BenchmarkFixture $f) => $f->expectedVibeMode === $mode,
        ));
    }
}
