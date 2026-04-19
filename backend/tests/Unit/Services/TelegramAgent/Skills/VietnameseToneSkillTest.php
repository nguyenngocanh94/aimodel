<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Skills;

use App\Services\TelegramAgent\Skills\VietnameseToneSkill;
use PHPUnit\Framework\TestCase;

final class VietnameseToneSkillTest extends TestCase
{
    public function test_name_returns_expected_slug(): void
    {
        $skill = new VietnameseToneSkill();
        $this->assertSame('vietnamese-tone', $skill->name());
    }

    public function test_prompt_fragment_contains_key_phrase(): void
    {
        $skill = new VietnameseToneSkill();
        $text  = $skill->promptFragment();

        $this->assertStringContainsString('tiếng Việt', $text);
        $this->assertStringContainsString('Trợ lý', $text);
    }

    public function test_applies_to_returns_true_by_default(): void
    {
        $skill = new VietnameseToneSkill();
        $this->assertTrue($skill->appliesTo([]));
    }
}
