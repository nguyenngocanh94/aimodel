<?php

declare(strict_types=1);

namespace App\Domain\Planner;

/**
 * A benchmark fixture encoding a creative brief and the workflow
 * characteristics a correct planner output should exhibit.
 *
 * Consumers:
 * - aimodel-645.5 (creative-drift & ad-likeness evaluator) — reads
 *   {@see $expectedCharacteristics} + {@see $antiPatterns} as pass/fail signals
 *   and {@see $expectedVibeMode} as the scoring axis.
 * - aimodel-645.8 (planner integration tests) — reads
 *   {@see $expectedNodes}, {@see $forbiddenNodes}, and
 *   {@see $expectedKnobValues} to assert the composed workflow shape.
 *
 * All arrays are documented rather than typed beyond `array` because the
 * planner and drift-eval schemas are still evolving (645.5/645.8 will pin them).
 */
final readonly class BenchmarkFixture
{
    public const KNOWN_VIBE_MODES = [
        'funny_storytelling',
        'clean_education',
        'aesthetic_mood',
        'raw_authentic',
    ];

    /**
     * @param string                $id                    Stable slug for this fixture.
     * @param string                $brief                 Raw user brief (Vietnamese OK).
     * @param string                $product               Product/subject name.
     * @param string                $expectedVibeMode      One of {@see KNOWN_VIBE_MODES}.
     * @param list<string>          $expectedNodes         Node `type` strings the planner should select.
     * @param list<string>          $forbiddenNodes        Node `type` strings + rationale the planner must NOT select.
     * @param array<string, mixed>  $expectedKnobValues    Dotted `node.knob` => value the planner should resolve.
     * @param array<string, mixed>  $expectedCharacteristics High-level pass/fail signals for drift-eval.
     * @param list<string>          $antiPatterns          Explicit failure modes this fixture guards against.
     * @param string                $sourceNotes           Why this fixture matters; real creative pattern it represents.
     */
    public function __construct(
        public string $id,
        public string $brief,
        public string $product,
        public string $expectedVibeMode,
        public array $expectedNodes,
        public array $forbiddenNodes,
        public array $expectedKnobValues,
        public array $expectedCharacteristics,
        public array $antiPatterns,
        public string $sourceNotes,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            brief: $data['brief'],
            product: $data['product'],
            expectedVibeMode: $data['expectedVibeMode'],
            expectedNodes: $data['expectedNodes'],
            forbiddenNodes: $data['forbiddenNodes'],
            expectedKnobValues: $data['expectedKnobValues'],
            expectedCharacteristics: $data['expectedCharacteristics'],
            antiPatterns: $data['antiPatterns'],
            sourceNotes: $data['sourceNotes'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'brief' => $this->brief,
            'product' => $this->product,
            'expectedVibeMode' => $this->expectedVibeMode,
            'expectedNodes' => $this->expectedNodes,
            'forbiddenNodes' => $this->forbiddenNodes,
            'expectedKnobValues' => $this->expectedKnobValues,
            'expectedCharacteristics' => $this->expectedCharacteristics,
            'antiPatterns' => $this->antiPatterns,
            'sourceNotes' => $this->sourceNotes,
        ];
    }
}
