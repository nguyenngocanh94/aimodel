<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\BehaviorSkills;

use App\Services\TelegramAgent\BehaviorSkills\VietnameseToneBehaviorSkill;
use PHPUnit\Framework\TestCase;

final class VietnameseToneBehaviorSkillTest extends TestCase
{
    public function test_name_returns_expected_slug(): void
    {
        $skill = new VietnameseToneBehaviorSkill();
        $this->assertSame('vietnamese-tone', $skill->name());
    }

    public function test_prompt_fragment_contains_key_phrase(): void
    {
        $skill = new VietnameseToneBehaviorSkill();
        $text  = $skill->promptFragment();

        $this->assertStringContainsString('tiếng Việt', $text);
        $this->assertStringContainsString('Trợ lý', $text);
    }

    public function test_applies_to_returns_true_by_default(): void
    {
        $skill = new VietnameseToneBehaviorSkill();
        $this->assertTrue($skill->appliesTo([]));
    }
}
