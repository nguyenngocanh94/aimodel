<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\BehaviorSkills;

use App\Services\TelegramAgent\BehaviorSkills\NoRamblingBehaviorSkill;
use PHPUnit\Framework\TestCase;

final class NoRamblingBehaviorSkillTest extends TestCase
{
    public function test_name_returns_expected_slug(): void
    {
        $skill = new NoRamblingBehaviorSkill();
        $this->assertSame('no-rambling', $skill->name());
    }

    public function test_prompt_fragment_contains_key_phrase(): void
    {
        $skill = new NoRamblingBehaviorSkill();
        $text  = $skill->promptFragment();

        $this->assertStringContainsString('từ chối', $text);
    }

    public function test_applies_to_returns_true_by_default(): void
    {
        $skill = new NoRamblingBehaviorSkill();
        $this->assertTrue($skill->appliesTo([]));
    }
}
