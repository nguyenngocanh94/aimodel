<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\Nodes\GuideKnob;
use App\Domain\Nodes\GuidePort;
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\VibeImpact;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NodeGuideTest extends TestCase
{
    private NodeGuide $guide;

    protected function setUp(): void
    {
        $this->guide = new NodeGuide(
            nodeId: 'storyWriter',
            purpose: 'Write human-centered story arcs that pay off the hook promise.',
            position: 'after hook selection gate, before casting',
            vibeImpact: VibeImpact::Critical,
            humanGate: true,
            knobs: [
                new GuideKnob(
                    name: 'story_tension_curve',
                    type: 'enum',
                    options: ['slow_build', 'fast_hit', 'rollercoaster'],
                    default: 'fast_hit',
                    effect: 'Controls how tension builds — gradual ramp vs early peak vs multiple peaks.',
                    vibeMapping: [
                        'funny_storytelling' => 'fast_hit',
                        'clean_education' => 'slow_build',
                        'aesthetic_mood' => 'slow_build',
                        'raw_authentic' => 'slow_build',
                    ],
                ),
            ],
            readsFrom: ['humanGate', 'intentOutcomeSelector', 'truthConstraintGate', 'formatLibraryMatcher'],
            writesTo: ['casting', 'shotCompiler'],
            ports: [
                GuidePort::input('selected_hook', 'json', true),
                GuidePort::input('intent_pack', 'json', true),
                GuidePort::input('grounding', 'json', true),
                GuidePort::input('vibe_state', 'json', false),
                GuidePort::output('story_pack', 'json'),
            ],
            whenToInclude: 'when vibe_mode is funny_storytelling or raw_authentic',
            whenToSkip: 'when vibe_mode is clean_education or aesthetic_mood',
        );
    }

    #[Test]
    public function all_properties_are_accessible(): void
    {
        $this->assertSame('storyWriter', $this->guide->nodeId);
        $this->assertSame(VibeImpact::Critical, $this->guide->vibeImpact);
        $this->assertTrue($this->guide->humanGate);
        $this->assertCount(1, $this->guide->knobs);
        $this->assertCount(4, $this->guide->readsFrom);
        $this->assertCount(2, $this->guide->writesTo);
        $this->assertCount(5, $this->guide->ports);
    }

    #[Test]
    public function to_array_produces_planner_consumable_structure(): void
    {
        $arr = $this->guide->toArray();

        $this->assertSame('storyWriter', $arr['node_id']);
        $this->assertSame('critical', $arr['vibe_impact']);
        $this->assertTrue($arr['human_gate']);
        $this->assertArrayHasKey('knobs', $arr);
        $this->assertArrayHasKey('story_tension_curve', $arr['knobs']);
        $this->assertArrayHasKey('connects_to', $arr);
        $this->assertSame(['humanGate', 'intentOutcomeSelector', 'truthConstraintGate', 'formatLibraryMatcher'], $arr['connects_to']['reads_from']);
        $this->assertSame(['casting', 'shotCompiler'], $arr['connects_to']['writes_to']);
        $this->assertArrayHasKey('ports', $arr['connects_to']);
    }

    #[Test]
    public function knob_to_array_includes_vibe_mapping(): void
    {
        $knob = $this->guide->knobs[0];
        $arr = $knob->toArray();

        $this->assertSame('story_tension_curve', $arr['name']);
        $this->assertSame('enum', $arr['type']);
        $this->assertSame(['slow_build', 'fast_hit', 'rollercoaster'], $arr['options']);
        $this->assertSame('fast_hit', $arr['default']);
        $this->assertArrayHasKey('vibe_mapping', $arr);
        $this->assertSame('fast_hit', $arr['vibe_mapping']['funny_storytelling']);
    }

    #[Test]
    public function port_to_array_includes_direction_and_required(): void
    {
        $inputPort = $this->guide->ports[0];
        $arr = $inputPort->toArray();

        $this->assertSame('selected_hook', $arr['key']);
        $this->assertSame('input', $arr['direction']);
        $this->assertSame('json', $arr['type']);
        $this->assertTrue($arr['required']);

        $outputPort = $this->guide->ports[4];
        $arr = $outputPort->toArray();

        $this->assertSame('story_pack', $arr['key']);
        $this->assertSame('output', $arr['direction']);
        $this->assertFalse($arr['required']);
    }

    #[Test]
    public function to_yaml_produces_compact_planner_card(): void
    {
        $yaml = $this->guide->toYaml();

        $this->assertStringContainsString('node_id: storyWriter', $yaml);
        $this->assertStringContainsString('vibe_impact: critical', $yaml);
        $this->assertStringContainsString('human_gate: true', $yaml);
        $this->assertStringContainsString('story_tension_curve', $yaml);
    }

    #[Test]
    public function guide_without_optional_fields_still_serializes(): void
    {
        $minimal = new NodeGuide(
            nodeId: 'briefIngest',
            purpose: 'Parse and normalize the product brief into structured data.',
            position: 'first node in pipeline',
            vibeImpact: VibeImpact::Neutral,
            humanGate: false,
            knobs: [],
            readsFrom: [],
            writesTo: ['intentOutcomeSelector', 'truthConstraintGate'],
            ports: [
                GuidePort::input('raw_brief', 'text', true),
                GuidePort::output('brief', 'json'),
            ],
            whenToInclude: 'always',
            whenToSkip: 'never',
        );

        $arr = $minimal->toArray();
        $this->assertSame('briefIngest', $arr['node_id']);
        $this->assertSame([], $arr['knobs']);
        $this->assertSame('always', $arr['when_to_include']);
    }
}
