<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Skills;

use App\Services\TelegramAgent\Skills\ExtractProductBriefSkill;
use PHPUnit\Framework\TestCase;

final class ExtractProductBriefSkillTest extends TestCase
{
    public function test_name_returns_expected_slug(): void
    {
        $skill = new ExtractProductBriefSkill();
        $this->assertSame('extract-product-brief', $skill->name());
    }

    public function test_prompt_fragment_contains_key_phrase(): void
    {
        $skill = new ExtractProductBriefSkill();
        $text  = $skill->promptFragment();

        $this->assertStringContainsString('productBrief', $text);
        $this->assertStringContainsString('chocopie', $text);
    }

    public function test_applies_to_returns_true_by_default(): void
    {
        $skill = new ExtractProductBriefSkill();
        $this->assertTrue($skill->appliesTo([]));
    }
}
