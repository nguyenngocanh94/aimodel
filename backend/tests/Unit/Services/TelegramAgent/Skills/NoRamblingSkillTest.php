<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Skills;

use App\Services\TelegramAgent\Skills\NoRamblingSkill;
use PHPUnit\Framework\TestCase;

final class NoRamblingSkillTest extends TestCase
{
    public function test_name_returns_expected_slug(): void
    {
        $skill = new NoRamblingSkill();
        $this->assertSame('no-rambling', $skill->name());
    }

    public function test_prompt_fragment_contains_key_phrase(): void
    {
        $skill = new NoRamblingSkill();
        $text  = $skill->promptFragment();

        $this->assertStringContainsString('từ chối', $text);
    }

    public function test_applies_to_returns_true_by_default(): void
    {
        $skill = new NoRamblingSkill();
        $this->assertTrue($skill->appliesTo([]));
    }
}
