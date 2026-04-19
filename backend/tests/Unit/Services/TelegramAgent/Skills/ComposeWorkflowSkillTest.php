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
        // CW4 expanded rule uses "KHÔNG BAO GIỜ" phrasing in the invariant rules block.
        $this->assertStringContainsString('KHÔNG BAO GIỜ gọi `PersistWorkflowTool`', $text);
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

    public function test_prompt_contains_all_three_tool_names(): void
    {
        $text = (new ComposeWorkflowSkill())->promptFragment();
        $this->assertStringContainsString('ComposeWorkflowTool', $text);
        $this->assertStringContainsString('RefinePlanTool', $text);
        $this->assertStringContainsString('PersistWorkflowTool', $text);
    }

    public function test_prompt_contains_all_approval_vocabulary(): void
    {
        $text = (new ComposeWorkflowSkill())->promptFragment();
        $words = ['ok', 'oki', 'oke', 'đồng ý', 'được', 'chốt', 'tiếp', 'làm đi', 'go', 'yes'];
        foreach ($words as $word) {
            $this->assertStringContainsString($word, $text, "Approval word '{$word}' not found in prompt fragment.");
        }
    }

    public function test_prompt_contains_all_refinement_vocabulary(): void
    {
        $text = (new ComposeWorkflowSkill())->promptFragment();
        $words = ['chỉnh', 'đổi', 'thay', 'khác', 'sửa', 'thêm', 'bớt', 'lại', 'retry', 'update'];
        foreach ($words as $word) {
            $this->assertStringContainsString($word, $text, "Refinement word '{$word}' not found in prompt fragment.");
        }
    }

    public function test_prompt_contains_all_rejection_vocabulary(): void
    {
        $text = (new ComposeWorkflowSkill())->promptFragment();
        $words = ['hủy', 'thôi', 'không', 'dừng', 'bỏ', 'cancel', 'no'];
        foreach ($words as $word) {
            $this->assertStringContainsString($word, $text, "Rejection word '{$word}' not found in prompt fragment.");
        }
    }

    public function test_prompt_contains_concrete_slug_examples(): void
    {
        $text = (new ComposeWorkflowSkill())->promptFragment();
        $slugs = ['health-tvc-9x16', 'milktea-short-video', 'b2b-pitch-deck', 'iphone-15-unbox'];
        foreach ($slugs as $slug) {
            $this->assertStringContainsString($slug, $text, "Slug example '{$slug}' not found in prompt fragment.");
        }
    }

    public function test_prompt_contains_refinement_cap_warning(): void
    {
        $text = (new ComposeWorkflowSkill())->promptFragment();
        // Assert that "5" appears near "chỉnh" context — the cap limit reference.
        $this->assertMatchesRegularExpression('/5.*chỉnh|chỉnh.*5/u', $text);
    }

    public function test_prompt_contains_never_persist_without_approval_rule(): void
    {
        $text = (new ComposeWorkflowSkill())->promptFragment();
        $this->assertStringContainsString('KHÔNG BAO GIỜ gọi `PersistWorkflowTool`', $text);
    }
}
