<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Planner;

use App\Domain\Planner\BenchmarkFixture;
use App\Domain\Planner\BenchmarkFixtureLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BenchmarkFixtureLoaderTest extends TestCase
{
    private BenchmarkFixtureLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new BenchmarkFixtureLoader(
            __DIR__ . '/../../../Fixtures/PlannerBenchmarks',
        );
    }

    #[Test]
    public function all_fixtures_load_without_error(): void
    {
        $fixtures = $this->loader->all();

        $this->assertNotEmpty($fixtures);
        $this->assertContainsOnlyInstancesOf(BenchmarkFixture::class, $fixtures);
    }

    #[Test]
    public function every_fixture_has_required_fields_populated(): void
    {
        foreach ($this->loader->all() as $fixture) {
            $this->assertNotEmpty($fixture->id, 'id empty');
            $this->assertNotEmpty($fixture->brief, "brief empty for {$fixture->id}");
            $this->assertNotEmpty($fixture->product, "product empty for {$fixture->id}");
            $this->assertContains(
                $fixture->expectedVibeMode,
                BenchmarkFixture::KNOWN_VIBE_MODES,
                "unknown vibe mode '{$fixture->expectedVibeMode}' in {$fixture->id}",
            );
            $this->assertNotEmpty($fixture->expectedNodes, "expectedNodes empty for {$fixture->id}");
            $this->assertNotEmpty(
                $fixture->expectedCharacteristics,
                "expectedCharacteristics empty for {$fixture->id}",
            );
            $this->assertNotEmpty($fixture->antiPatterns, "antiPatterns empty for {$fixture->id}");
            $this->assertNotEmpty($fixture->sourceNotes, "sourceNotes empty for {$fixture->id}");
        }
    }

    #[Test]
    public function by_id_returns_the_matching_fixture(): void
    {
        $fixture = $this->loader->byId('cocoon-soft-sell');

        $this->assertNotNull($fixture);
        $this->assertSame('cocoon-soft-sell', $fixture->id);
        $this->assertSame('funny_storytelling', $fixture->expectedVibeMode);
    }

    #[Test]
    public function by_id_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->loader->byId('nonexistent'));
    }

    #[Test]
    public function by_vibe_mode_returns_cocoon_soft_sell_for_funny_storytelling(): void
    {
        $fixtures = $this->loader->byVibeMode('funny_storytelling');

        $this->assertNotEmpty($fixtures);
        $ids = array_map(fn (BenchmarkFixture $f) => $f->id, $fixtures);
        $this->assertContains('cocoon-soft-sell', $ids);
    }

    #[Test]
    public function at_least_one_fixture_exists_per_known_vibe_mode(): void
    {
        foreach (BenchmarkFixture::KNOWN_VIBE_MODES as $mode) {
            $fixtures = $this->loader->byVibeMode($mode);
            $this->assertNotEmpty(
                $fixtures,
                "No fixture covers vibe mode '{$mode}' — drift-eval cannot score this vibe.",
            );
        }
    }

    #[Test]
    public function forbidden_and_expected_nodes_do_not_overlap(): void
    {
        foreach ($this->loader->all() as $fixture) {
            // forbiddenNodes entries include rationale suffixes like "storyWriter (reason)".
            // Normalize by grabbing the leading token.
            $forbiddenTypes = array_map(
                fn (string $entry) => trim(strtok($entry, ' ')),
                $fixture->forbiddenNodes,
            );
            $overlap = array_intersect($fixture->expectedNodes, $forbiddenTypes);
            $this->assertEmpty(
                $overlap,
                "Fixture {$fixture->id} has contradictory expected/forbidden: "
                . implode(', ', $overlap),
            );
        }
    }

    #[Test]
    public function fixture_count_matches_committed_set(): void
    {
        // Canary: if a fixture is added/removed, update this assertion
        // and the design doc (2026-04-19-planner-benchmark-fixtures.md).
        $this->assertCount(4, $this->loader->all());
    }

    #[Test]
    public function to_array_round_trips_through_from_array(): void
    {
        foreach ($this->loader->all() as $fixture) {
            $roundTripped = BenchmarkFixture::fromArray($fixture->toArray());
            $this->assertEquals($fixture, $roundTripped);
        }
    }
}
