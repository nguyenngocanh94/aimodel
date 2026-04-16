# Node Guide Contract Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Define the machine-readable guide schema that makes any node usable by the AI workflow planner — purpose, I/O, config knobs with vibe mappings, connection rules, and inclusion constraints.

**Architecture:** Add a `NodeGuide` value object and abstract `plannerGuide()` method to the existing `NodeTemplate` base class. Each node template returns a typed guide conforming to the contract. The `NodeTemplateRegistry` gains a `guides()` method that collects all guides for planner context injection (~300 tokens per node). One proof-of-concept guide (story-writer) validates the contract end-to-end.

**Tech Stack:** PHP 8.4 (enums, readonly classes, property hooks), PHPUnit 11, existing NodeTemplate infrastructure

---

### Task 1: Create the NodeGuide value objects

**Files:**
- Create: `backend/app/Domain/Nodes/NodeGuide.php`
- Create: `backend/app/Domain/Nodes/GuideKnob.php`
- Create: `backend/app/Domain/Nodes/GuidePort.php`
- Create: `backend/app/Domain/Nodes/VibeImpact.php`
- Test: `backend/tests/Unit/Domain/Nodes/NodeGuideTest.php`

**Step 1: Write the failing test**

```php
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
```

**Step 2: Run test to verify it fails**

Run: `docker exec -it aimodel-backend php artisan test --filter=NodeGuideTest`
Expected: FAIL — classes don't exist yet.

**Step 3: Write the value objects**

`backend/app/Domain/Nodes/VibeImpact.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

enum VibeImpact: string
{
    case Critical = 'critical';
    case Neutral = 'neutral';
}
```

`backend/app/Domain/Nodes/GuidePort.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

readonly class GuidePort
{
    public function __construct(
        public string $key,
        public string $direction,
        public string $type,
        public bool $required,
    ) {}

    public static function input(string $key, string $type, bool $required = true): self
    {
        return new self($key, 'input', $type, $required);
    }

    public static function output(string $key, string $type): self
    {
        return new self($key, 'output', $type, false);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'direction' => $this->direction,
            'type' => $this->type,
            'required' => $this->required,
        ];
    }
}
```

`backend/app/Domain/Nodes/GuideKnob.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

readonly class GuideKnob
{
    /**
     * @param string $name Knob identifier matching the config key
     * @param string $type Data type: enum, int, float, bool, string[]
     * @param list<string|int|float|bool>|null $options Valid values for enum types
     * @param string|int|float|bool $default Sensible default value
     * @param string $effect 1-sentence creative outcome description
     * @param array<string, string|int|float|bool> $vibeMapping Quick lookup: vibe_mode => recommended value
     */
    public function __construct(
        public string $name,
        public string $type,
        public ?array $options,
        public string|int|float|bool $default,
        public string $effect,
        public array $vibeMapping = [],
    ) {}

    public function toArray(): array
    {
        $arr = [
            'name' => $this->name,
            'type' => $this->type,
            'default' => $this->default,
            'effect' => $this->effect,
        ];

        if ($this->options !== null) {
            $arr['options'] = $this->options;
        }

        if ($this->vibeMapping !== []) {
            $arr['vibe_mapping'] = $this->vibeMapping;
        }

        return $arr;
    }
}
```

`backend/app/Domain/Nodes/NodeGuide.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

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
        return \Symfony\Component\Yaml\Yaml::dump(
            $this->toArray(),
            inline: 4,
            indent: 2,
            flags: \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK,
        );
    }
}
```

**Step 4: Run test to verify it passes**

Run: `docker exec -it aimodel-backend php artisan test --filter=NodeGuideTest`
Expected: All 6 tests PASS.

**Step 5: Commit**

```bash
git add backend/app/Domain/Nodes/NodeGuide.php \
       backend/app/Domain/Nodes/GuideKnob.php \
       backend/app/Domain/Nodes/GuidePort.php \
       backend/app/Domain/Nodes/VibeImpact.php \
       backend/tests/Unit/Domain/Nodes/NodeGuideTest.php
git commit -m "feat(645.1): add NodeGuide value objects for planner-readable node contract"
```

---

### Task 2: Add abstract `plannerGuide()` to NodeTemplate

**Files:**
- Modify: `backend/app/Domain/Nodes/NodeTemplate.php`
- Modify: `backend/tests/Unit/Domain/Nodes/NodeTemplateTest.php` (update StubTemplate)
- Modify: ALL existing templates in `backend/app/Domain/Nodes/Templates/` (add minimal stub guides)

**Step 1: Write the failing test**

Add to `backend/tests/Unit/Domain/Nodes/NodeTemplateTest.php` — update the `StubTemplate` to implement the new method, then test it:

```php
// Add imports at the top:
use App\Domain\Nodes\GuideKnob;
use App\Domain\Nodes\GuidePort;
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\VibeImpact;

// Add to StubTemplate class:
public function plannerGuide(): NodeGuide
{
    return new NodeGuide(
        nodeId: $this->type,
        purpose: 'Test stub node for unit tests.',
        position: 'standalone',
        vibeImpact: VibeImpact::Neutral,
        humanGate: false,
        knobs: [],
        readsFrom: [],
        writesTo: [],
        ports: [
            GuidePort::input('textIn', 'text', true),
            GuidePort::output('textOut', 'text'),
        ],
        whenToInclude: 'never — test only',
        whenToSkip: 'always — test only',
    );
}

// Add new test method to NodeTemplateTest:
public function test_planner_guide_returns_node_guide(): void
{
    $guide = $this->template->plannerGuide();

    $this->assertInstanceOf(NodeGuide::class, $guide);
    $this->assertSame('stubNode', $guide->nodeId);
    $this->assertSame($this->template->type, $guide->nodeId);
}
```

**Step 2: Run test to verify it fails**

Run: `docker exec -it aimodel-backend php artisan test --filter=NodeTemplateTest`
Expected: FAIL — `plannerGuide()` not declared on abstract class.

**Step 3: Add the abstract method to NodeTemplate**

In `backend/app/Domain/Nodes/NodeTemplate.php`, add the import and abstract method:

```php
use App\Domain\Nodes\NodeGuide;

// Add after the execute() abstract method:
/**
 * The planner-readable guide card for this node.
 * Used by the AI workflow planner to select nodes and set config knobs.
 * Target: ~300 tokens per guide when serialized to YAML.
 */
abstract public function plannerGuide(): NodeGuide;
```

Then add a **minimal stub `plannerGuide()`** to every existing template that returns a bare-bones guide. These stubs will be properly filled in by bead 645.2 (normalize config knobs) and 645.7 (write docs). For now, each stub returns:

```php
public function plannerGuide(): NodeGuide
{
    return new NodeGuide(
        nodeId: $this->type,
        purpose: $this->description,
        position: 'TODO — assign in 645.2',
        vibeImpact: VibeImpact::Neutral,
        humanGate: false,
        knobs: [],
        readsFrom: [],
        writesTo: [],
        ports: array_merge(
            array_map(
                fn (PortDefinition $p) => GuidePort::input($p->key, $p->dataType->value, $p->required),
                $this->ports()->inputs,
            ),
            array_map(
                fn (PortDefinition $p) => GuidePort::output($p->key, $p->dataType->value),
                $this->ports()->outputs,
            ),
        ),
        whenToInclude: 'TODO — assign in 645.2',
        whenToSkip: 'TODO — assign in 645.2',
    );
}
```

**Important:** To avoid touching 18+ files manually, add this as a **default implementation** on the base `NodeTemplate` class instead of making it abstract. Then individual templates override it with their real guide. This way existing templates compile immediately and we can incrementally fill in guides.

Revised approach for `NodeTemplate.php`:

```php
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\GuidePort;
use App\Domain\Nodes\VibeImpact;

/**
 * The planner-readable guide card for this node.
 * Override in each template to provide real planner data.
 * Default returns a skeleton derived from ports + metadata.
 */
public function plannerGuide(): NodeGuide
{
    return new NodeGuide(
        nodeId: $this->type,
        purpose: $this->description,
        position: 'unassigned',
        vibeImpact: VibeImpact::Neutral,
        humanGate: false,
        knobs: [],
        readsFrom: [],
        writesTo: [],
        ports: array_merge(
            array_map(
                fn (\App\Domain\PortDefinition $p) => GuidePort::input($p->key, $p->dataType->value, $p->required),
                $this->ports()->inputs,
            ),
            array_map(
                fn (\App\Domain\PortDefinition $p) => GuidePort::output($p->key, $p->dataType->value),
                $this->ports()->outputs,
            ),
        ),
        whenToInclude: 'unassigned',
        whenToSkip: 'unassigned',
    );
}
```

**Step 4: Run tests to verify everything passes**

Run: `docker exec -it aimodel-backend php artisan test --filter=NodeTemplateTest`
Expected: All tests PASS (including the new `test_planner_guide_returns_node_guide`).

Run: `docker exec -it aimodel-backend php artisan test`
Expected: Full suite PASS — no existing template broke because base class provides default.

**Step 5: Commit**

```bash
git add backend/app/Domain/Nodes/NodeTemplate.php \
       backend/tests/Unit/Domain/Nodes/NodeTemplateTest.php
git commit -m "feat(645.1): add plannerGuide() with default skeleton to NodeTemplate"
```

---

### Task 3: Add `guides()` method to NodeTemplateRegistry

**Files:**
- Modify: `backend/app/Domain/Nodes/NodeTemplateRegistry.php`
- Modify: `backend/tests/Unit/Domain/Nodes/NodeTemplateRegistryTest.php`

**Step 1: Write the failing test**

Add to `NodeTemplateRegistryTest.php`:

```php
use App\Domain\Nodes\NodeGuide;

public function test_guides_returns_all_node_guides_keyed_by_type(): void
{
    // Assuming registry has templates registered in setUp()
    $guides = $this->registry->guides();

    $this->assertIsArray($guides);
    foreach ($guides as $type => $guide) {
        $this->assertInstanceOf(NodeGuide::class, $guide);
        $this->assertSame($type, $guide->nodeId);
    }
}

public function test_guides_yaml_returns_concatenated_yaml(): void
{
    $yaml = $this->registry->guidesYaml();

    $this->assertIsString($yaml);
    // Should contain the separator between node cards
    $this->assertStringContainsString('---', $yaml);
}
```

**Step 2: Run test to verify it fails**

Run: `docker exec -it aimodel-backend php artisan test --filter=NodeTemplateRegistryTest`
Expected: FAIL — `guides()` method not found.

**Step 3: Implement on the registry**

Add to `NodeTemplateRegistry.php`:

```php
/**
 * Collect planner guides from all registered templates.
 *
 * @return array<string, NodeGuide>
 */
public function guides(): array
{
    return array_map(
        fn (NodeTemplate $t) => $t->plannerGuide(),
        $this->templates,
    );
}

/**
 * All guides as concatenated YAML — ready for planner context injection.
 * Each guide is separated by "---" (YAML document separator).
 */
public function guidesYaml(): string
{
    $sections = array_map(
        fn (NodeGuide $g) => $g->toYaml(),
        $this->guides(),
    );

    return implode("\n---\n\n", $sections);
}
```

**Step 4: Run test to verify it passes**

Run: `docker exec -it aimodel-backend php artisan test --filter=NodeTemplateRegistryTest`
Expected: PASS.

**Step 5: Commit**

```bash
git add backend/app/Domain/Nodes/NodeTemplateRegistry.php \
       backend/tests/Unit/Domain/Nodes/NodeTemplateRegistryTest.php
git commit -m "feat(645.1): add guides() and guidesYaml() to NodeTemplateRegistry"
```

---

### Task 4: Implement proof-of-concept guide for StoryWriterTemplate

**Files:**
- Modify: `backend/app/Domain/Nodes/Templates/StoryWriterTemplate.php`
- Modify: `backend/tests/Unit/Domain/Nodes/Templates/StoryWriterTemplateTest.php`

This proves the contract works end-to-end with a real node. The guide data comes from `docs/plans/2026-04-15-story-writer-framework.md`.

**Step 1: Write the failing test**

Add to `StoryWriterTemplateTest.php`:

```php
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\VibeImpact;

#[Test]
public function planner_guide_has_correct_identity(): void
{
    $guide = $this->template->plannerGuide();

    $this->assertInstanceOf(NodeGuide::class, $guide);
    $this->assertSame('storyWriter', $guide->nodeId);
    $this->assertSame(VibeImpact::Critical, $guide->vibeImpact);
    $this->assertTrue($guide->humanGate);
}

#[Test]
public function planner_guide_has_all_seven_knobs(): void
{
    $guide = $this->template->plannerGuide();

    $knobNames = array_map(fn ($k) => $k->name, $guide->knobs);

    $this->assertContains('story_tension_curve', $knobNames);
    $this->assertContains('product_appearance_moment', $knobNames);
    $this->assertContains('humor_density', $knobNames);
    $this->assertContains('story_versions_for_human', $knobNames);
    $this->assertContains('max_moments', $knobNames);
    $this->assertContains('target_duration_sec', $knobNames);
    $this->assertContains('ending_type_preference', $knobNames);
}

#[Test]
public function planner_guide_knobs_have_vibe_mappings(): void
{
    $guide = $this->template->plannerGuide();

    $tensionKnob = null;
    foreach ($guide->knobs as $k) {
        if ($k->name === 'story_tension_curve') {
            $tensionKnob = $k;
            break;
        }
    }

    $this->assertNotNull($tensionKnob);
    $this->assertSame('enum', $tensionKnob->type);
    $this->assertContains('slow_build', $tensionKnob->options);
    $this->assertContains('fast_hit', $tensionKnob->options);
    $this->assertArrayHasKey('funny_storytelling', $tensionKnob->vibeMapping);
    $this->assertSame('fast_hit', $tensionKnob->vibeMapping['funny_storytelling']);
}

#[Test]
public function planner_guide_has_correct_connections(): void
{
    $guide = $this->template->plannerGuide();

    $this->assertContains('humanGate', $guide->readsFrom);
    $this->assertContains('intentOutcomeSelector', $guide->readsFrom);
    $this->assertContains('truthConstraintGate', $guide->readsFrom);
    $this->assertContains('formatLibraryMatcher', $guide->readsFrom);
    $this->assertContains('casting', $guide->writesTo);
    $this->assertContains('shotCompiler', $guide->writesTo);
}

#[Test]
public function planner_guide_serializes_under_300_tokens(): void
{
    $guide = $this->template->plannerGuide();
    $yaml = $guide->toYaml();

    // Rough token estimate: ~4 chars per token for YAML
    $estimatedTokens = (int) ceil(strlen($yaml) / 4);
    $this->assertLessThan(500, $estimatedTokens, "Guide YAML should be under ~500 tokens (got ~{$estimatedTokens})");
}

#[Test]
public function planner_guide_when_to_include_specifies_vibe_modes(): void
{
    $guide = $this->template->plannerGuide();

    $this->assertStringContainsString('funny_storytelling', $guide->whenToInclude);
    $this->assertStringContainsString('raw_authentic', $guide->whenToInclude);
}
```

**Step 2: Run test to verify it fails**

Run: `docker exec -it aimodel-backend php artisan test --filter=StoryWriterTemplateTest`
Expected: FAIL — story-writer still returns the default skeleton guide (no knobs, neutral impact, etc.).

**Step 3: Implement the full guide override**

In `StoryWriterTemplate.php`, add the override. Reference: `docs/plans/2026-04-15-story-writer-framework.md` sections 4, 8, 9, 10.

```php
use App\Domain\Nodes\GuideKnob;
use App\Domain\Nodes\GuidePort;
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\VibeImpact;

public function plannerGuide(): NodeGuide
{
    return new NodeGuide(
        nodeId: $this->type,
        purpose: 'Write a short story that pays off the hook promise, stays within vibe, and contains the product naturally. Outputs human-readable script + structured moments.',
        position: 'after hook selection gate, before casting',
        vibeImpact: VibeImpact::Critical,
        humanGate: true,
        knobs: [
            new GuideKnob(
                name: 'story_tension_curve',
                type: 'enum',
                options: ['slow_build', 'fast_hit', 'rollercoaster'],
                default: 'fast_hit',
                effect: 'Controls how tension builds — gradual ramp, early peak, or multiple peaks.',
                vibeMapping: [
                    'funny_storytelling' => 'fast_hit',
                    'clean_education' => 'slow_build',
                    'aesthetic_mood' => 'slow_build',
                    'raw_authentic' => 'slow_build',
                ],
            ),
            new GuideKnob(
                name: 'product_appearance_moment',
                type: 'enum',
                options: ['early', 'middle', 'twist', 'end'],
                default: 'twist',
                effect: 'When product enters the story. Later = less ad-like.',
                vibeMapping: [
                    'funny_storytelling' => 'twist',
                    'clean_education' => 'early',
                    'aesthetic_mood' => 'middle',
                    'raw_authentic' => 'middle',
                ],
            ),
            new GuideKnob(
                name: 'humor_density',
                type: 'enum',
                options: ['none', 'punchline_only', 'throughout'],
                default: 'throughout',
                effect: 'How much humor is woven into the story.',
                vibeMapping: [
                    'funny_storytelling' => 'throughout',
                    'clean_education' => 'none',
                    'aesthetic_mood' => 'none',
                    'raw_authentic' => 'none',
                ],
            ),
            new GuideKnob(
                name: 'story_versions_for_human',
                type: 'int',
                options: null,
                default: 2,
                effect: 'Number of story versions generated for human selection.',
            ),
            new GuideKnob(
                name: 'max_moments',
                type: 'int',
                options: null,
                default: 6,
                effect: 'Maximum story moments. TikTok under 30s: use 4-5.',
            ),
            new GuideKnob(
                name: 'target_duration_sec',
                type: 'int',
                options: null,
                default: 35,
                effect: 'Total video target duration distributed across moments.',
            ),
            new GuideKnob(
                name: 'ending_type_preference',
                type: 'enum',
                options: ['twist_reveal', 'emotional_beat', 'soft_loop', 'call_to_action'],
                default: 'twist_reveal',
                effect: 'How the story ends — surprise, emotion, loop, or CTA.',
                vibeMapping: [
                    'funny_storytelling' => 'twist_reveal',
                    'clean_education' => 'call_to_action',
                    'aesthetic_mood' => 'soft_loop',
                    'raw_authentic' => 'emotional_beat',
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
        whenToSkip: 'when vibe_mode is clean_education or aesthetic_mood — use beat-planner or mood-sequencer instead',
    );
}
```

**Step 4: Run tests to verify they pass**

Run: `docker exec -it aimodel-backend php artisan test --filter=StoryWriterTemplateTest`
Expected: All tests PASS.

Run: `docker exec -it aimodel-backend php artisan test`
Expected: Full suite PASS.

**Step 5: Commit**

```bash
git add backend/app/Domain/Nodes/Templates/StoryWriterTemplate.php \
       backend/tests/Unit/Domain/Nodes/Templates/StoryWriterTemplateTest.php
git commit -m "feat(645.1): implement full planner guide for StoryWriterTemplate"
```

---

### Task 5: Add `node:guides` Artisan command for dumping guides

**Files:**
- Create: `backend/app/Console/Commands/DumpNodeGuidesCommand.php`
- Test: `backend/tests/Unit/Console/DumpNodeGuidesCommandTest.php` (or quick manual test)

This command dumps all node guides as YAML — useful for planner context injection and developer inspection.

**Step 1: Write the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Nodes\NodeTemplateRegistry;
use Illuminate\Console\Command;

class DumpNodeGuidesCommand extends Command
{
    protected $signature = 'node:guides
        {--type= : Dump guide for a single node type}
        {--format=yaml : Output format: yaml or json}';

    protected $description = 'Dump planner-readable node guides';

    public function handle(NodeTemplateRegistry $registry): int
    {
        $type = $this->option('type');
        $format = $this->option('format');

        if ($type) {
            $template = $registry->get($type);
            if (!$template) {
                $this->error("Node type '{$type}' not found.");
                return self::FAILURE;
            }
            $guide = $template->plannerGuide();
            $this->output->write(
                $format === 'json'
                    ? json_encode($guide->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : $guide->toYaml()
            );
            return self::SUCCESS;
        }

        if ($format === 'json') {
            $all = array_map(fn ($g) => $g->toArray(), $registry->guides());
            $this->output->write(json_encode(array_values($all), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->output->write($registry->guidesYaml());
        }

        return self::SUCCESS;
    }
}
```

**Step 2: Smoke test**

Run: `docker exec -it aimodel-backend php artisan node:guides --type=storyWriter`
Expected: Prints the story-writer guide as YAML with all 7 knobs, vibe mappings, connections.

Run: `docker exec -it aimodel-backend php artisan node:guides --format=json --type=storyWriter`
Expected: Same guide as JSON.

Run: `docker exec -it aimodel-backend php artisan node:guides`
Expected: All registered node guides as YAML, separated by `---`.

**Step 3: Commit**

```bash
git add backend/app/Console/Commands/DumpNodeGuidesCommand.php
git commit -m "feat(645.1): add node:guides artisan command for planner context dumping"
```

---

### Task 6: Add contract conformance test for all registered templates

**Files:**
- Create: `backend/tests/Unit/Domain/Nodes/AllGuidesConformanceTest.php`

This test ensures every registered node produces a valid guide — catches regressions when new nodes are added without guides.

**Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Nodes\VibeImpact;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AllGuidesConformanceTest extends TestCase
{
    private static function registry(): NodeTemplateRegistry
    {
        $registry = new NodeTemplateRegistry();
        // Register all templates — same as the service provider does
        foreach (glob(__DIR__ . '/../../../../app/Domain/Nodes/Templates/*Template.php') as $file) {
            $className = 'App\\Domain\\Nodes\\Templates\\' . basename($file, '.php');
            if (class_exists($className) && !((new \ReflectionClass($className))->isAbstract())) {
                $registry->register(new $className());
            }
        }
        return $registry;
    }

    public static function templateProvider(): iterable
    {
        foreach (self::registry()->all() as $type => $template) {
            yield $type => [$template];
        }
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function guide_node_id_matches_template_type($template): void
    {
        $guide = $template->plannerGuide();
        $this->assertSame($template->type, $guide->nodeId);
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function guide_purpose_is_not_empty($template): void
    {
        $guide = $template->plannerGuide();
        $this->assertNotEmpty($guide->purpose);
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function guide_serializes_to_array($template): void
    {
        $guide = $template->plannerGuide();
        $arr = $guide->toArray();

        $this->assertArrayHasKey('node_id', $arr);
        $this->assertArrayHasKey('purpose', $arr);
        $this->assertArrayHasKey('vibe_impact', $arr);
        $this->assertArrayHasKey('human_gate', $arr);
        $this->assertArrayHasKey('knobs', $arr);
        $this->assertArrayHasKey('connects_to', $arr);
        $this->assertArrayHasKey('when_to_include', $arr);
        $this->assertArrayHasKey('when_to_skip', $arr);
        $this->assertContains($arr['vibe_impact'], ['critical', 'neutral']);
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function guide_serializes_to_yaml($template): void
    {
        $guide = $template->plannerGuide();
        $yaml = $guide->toYaml();

        $this->assertIsString($yaml);
        $this->assertStringContainsString($guide->nodeId, $yaml);
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function guide_knobs_have_required_fields($template): void
    {
        $guide = $template->plannerGuide();

        foreach ($guide->knobs as $knob) {
            $arr = $knob->toArray();
            $this->assertArrayHasKey('name', $arr, "Knob missing name in {$guide->nodeId}");
            $this->assertArrayHasKey('type', $arr, "Knob missing type in {$guide->nodeId}");
            $this->assertArrayHasKey('default', $arr, "Knob missing default in {$guide->nodeId}");
            $this->assertArrayHasKey('effect', $arr, "Knob missing effect in {$guide->nodeId}");
        }
    }
}
```

**Step 2: Run tests**

Run: `docker exec -it aimodel-backend php artisan test --filter=AllGuidesConformanceTest`
Expected: All tests PASS for every registered template.

**Step 3: Commit**

```bash
git add backend/tests/Unit/Domain/Nodes/AllGuidesConformanceTest.php
git commit -m "test(645.1): add conformance test ensuring all templates produce valid guides"
```

---

## Summary

| Task | What | Key output |
|------|------|-----------|
| 1 | NodeGuide value objects | `NodeGuide`, `GuideKnob`, `GuidePort`, `VibeImpact` |
| 2 | Add `plannerGuide()` to NodeTemplate | Default skeleton on base class, no existing templates break |
| 3 | Registry `guides()` + `guidesYaml()` | Collect all guides for planner context injection |
| 4 | Story-writer proof-of-concept | Full guide with 7 knobs, vibe mappings, connections |
| 5 | `node:guides` artisan command | CLI for dumping guides as YAML/JSON |
| 6 | Conformance test | Every registered template produces a valid guide |

**What this unblocks:**
- `645.2` — Can now implement real guides for all nodes (override `plannerGuide()`)
- `645.3` — Can now define the workflow plan schema that consumes these guides
- `645.7` — Can now write docs based on the concrete contract
