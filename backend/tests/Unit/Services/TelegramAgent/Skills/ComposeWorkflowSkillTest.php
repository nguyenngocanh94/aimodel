<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Skills;

use App\Services\TelegramAgent\Skills\ComposeWorkflowSkill;
use PHPUnit\Framework\TestCase;

final class ComposeWorkflowSkillTest extends TestCase
{
    public function test_name_is_compose_workflow(): void
    {
        $skill = new ComposeWorkflowSkill();
        $this->assertSame('compose-workflow', $skill->name());
    }

    public function test_prompt_fragment_contains_tao_workflow_trigger(): void
    {
        $text = (new ComposeWorkflowSkill())->promptFragment();
        $this->assertStringContainsString('tạo workflow', $text);
        $this->assertStringContainsString('ComposeWorkflowTool', $text);
    }

    public function test_prompt_fragment_contains_never_persist_without_approval_rule(): void
    {
        $text = (new ComposeWorkflowSkill())->promptFragment();
        $this->assertStringContainsString('PersistWorkflowTool', $text);
        $this->assertStringContainsString('TUYỆT ĐỐI KHÔNG', $text);
    }

    public function test_prompt_fragment_contains_one_shot_transcript_example(): void
    {
        $text = (new ComposeWorkflowSkill())->promptFragment();
        $this->assertStringContainsString('User:', $text);
        $this->assertStringContainsString('Assistant:', $text);
        // Full chain markers: compose → reply → persist → reply.
        $this->assertStringContainsString('tool_use: ComposeWorkflowTool', $text);
        $this->assertStringContainsString('tool_use: PersistWorkflowTool', $text);
    }
}
