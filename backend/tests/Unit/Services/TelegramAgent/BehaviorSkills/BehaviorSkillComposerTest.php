<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\BehaviorSkills;

use App\Services\TelegramAgent\BehaviorSkills\AbstractBehaviorSkill;
use App\Services\TelegramAgent\BehaviorSkills\BehaviorSkillComposer;
use PHPUnit\Framework\TestCase;

final class BehaviorSkillComposerTest extends TestCase
{
    public function test_compose_produces_vietnamese_preamble_with_chat_id(): void
    {
        $composer = new BehaviorSkillComposer();
        $out      = $composer->compose([], [], [], 'chat-xyz');

        $this->assertStringContainsString('chat-xyz', $out);
        $this->assertStringContainsString('Trợ lý Workflow', $out);
        $this->assertStringContainsString('Telegram', $out);
    }

    public function test_compose_includes_each_applying_skill_in_declared_order(): void
    {
        $skillA = new class extends AbstractBehaviorSkill
        {
            public function name(): string
            {
                return 'skill-a';
            }

            public function promptFragment(): string
            {
                return 'FRAGMENT_A_FIRST';
            }
        };

        $skillB = new class extends AbstractBehaviorSkill
        {
            public function name(): string
            {
                return 'skill-b';
            }

            public function promptFragment(): string
            {
                return 'FRAGMENT_B_SECOND';
            }
        };

        $composer = new BehaviorSkillComposer();
        $out      = $composer->compose([$skillA, $skillB], [], [], '1');

        $posA = strpos($out, 'FRAGMENT_A_FIRST');
        $posB = strpos($out, 'FRAGMENT_B_SECOND');
        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        $this->assertLessThan($posB, $posA);
    }

    public function test_compose_skips_skills_that_do_not_apply(): void
    {
        $skipped = new class extends AbstractBehaviorSkill
        {
            public function name(): string
            {
                return 'skipped';
            }

            public function promptFragment(): string
            {
                return 'SHOULD_NOT_APPEAR';
            }

            public function appliesTo(array $update): bool
            {
                return false;
            }
        };

        $kept = new class extends AbstractBehaviorSkill
        {
            public function name(): string
            {
                return 'kept';
            }

            public function promptFragment(): string
            {
                return 'SHOULD_APPEAR';
            }
        };

        $composer = new BehaviorSkillComposer();
        $out      = $composer->compose([$skipped, $kept], [], [], '1');

        $this->assertStringNotContainsString('SHOULD_NOT_APPEAR', $out);
        $this->assertStringContainsString('SHOULD_APPEAR', $out);
    }

    public function test_compose_renders_catalog_preview_as_bulleted_list(): void
    {
        $catalog = [
            [
                'slug'           => 'wf-one',
                'name'           => 'One',
                'nl_description' => 'Desc one',
                'param_schema'   => ['a' => ['type' => 'string']],
            ],
            [
                'slug'           => 'wf-two',
                'name'           => 'Two',
                'nl_description' => 'Desc two',
                'param_schema'   => ['b' => ['type' => 'number']],
            ],
        ];

        $composer = new BehaviorSkillComposer();
        $out      = $composer->compose([], [], $catalog, '1');

        $this->assertStringContainsString('- slug: wf-one', $out);
        $this->assertStringContainsString('- slug: wf-two', $out);
        $this->assertStringContainsString('Desc one', $out);
        $this->assertStringContainsString('Desc two', $out);
    }

    public function test_compose_includes_all_registered_tool_names(): void
    {
        $composer = new BehaviorSkillComposer();
        $out      = $composer->compose([], [], [], '1');

        foreach ([
            'ListWorkflowsTool',
            'RunWorkflowTool',
            'GetRunStatusTool',
            'CancelRunTool',
            'ReplyTool',
            'ComposeWorkflowTool',
        ] as $name) {
            $this->assertStringContainsString($name, $out, "Missing tool name: {$name}");
        }
    }

    public function test_abstract_skill_defaults_applies_to_true(): void
    {
        $skill = new class extends AbstractBehaviorSkill
        {
            public function name(): string
            {
                return 'trivial';
            }

            public function promptFragment(): string
            {
                return 'x';
            }
        };

        $this->assertTrue($skill->appliesTo([]));
    }
}
