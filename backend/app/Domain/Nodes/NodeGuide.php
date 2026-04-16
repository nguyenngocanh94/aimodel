<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

use Symfony\Component\Yaml\Yaml;

readonly class NodeGuide
{
    /**
     * @param string $nodeId Matches the template's $type property
     * @param string $purpose 1-2 sentences: what this node does
     * @param string $position Where it sits in the pipeline chain
     * @param VibeImpact $vibeImpact How much this node affects the video's creative feel
     * @param bool $humanGate Whether this node has/needs a human decision gate
     * @param list<GuideKnob> $knobs Config parameters the planner can set
     * @param list<string> $readsFrom Upstream node IDs this reads from
     * @param list<string> $writesTo Downstream node IDs this writes to
     * @param list<GuidePort> $ports Input and output port definitions
     * @param string $whenToInclude Inclusion rule: "always" or condition
     * @param string $whenToSkip Skip rule: "never" or condition
     */
    public function __construct(
        public string $nodeId,
        public string $purpose,
        public string $position,
        public VibeImpact $vibeImpact,
        public bool $humanGate,
        public array $knobs,
        public array $readsFrom,
        public array $writesTo,
        public array $ports,
        public string $whenToInclude,
        public string $whenToSkip,
    ) {}

    public function toArray(): array
    {
        $knobs = [];
        foreach ($this->knobs as $knob) {
            $knobs[$knob->name] = $knob->toArray();
        }

        return [
            'node_id' => $this->nodeId,
            'purpose' => $this->purpose,
            'position' => $this->position,
            'vibe_impact' => $this->vibeImpact->value,
            'human_gate' => $this->humanGate,
            'knobs' => $knobs,
            'connects_to' => [
                'reads_from' => $this->readsFrom,
                'writes_to' => $this->writesTo,
                'ports' => array_map(fn (GuidePort $p) => $p->toArray(), $this->ports),
            ],
            'when_to_include' => $this->whenToInclude,
            'when_to_skip' => $this->whenToSkip,
        ];
    }

    public function toYaml(): string
    {
        return Yaml::dump(
            $this->toArray(),
            inline: 4,
            indent: 2,
            flags: Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
        );
    }
}
