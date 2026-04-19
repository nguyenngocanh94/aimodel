<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Skills;

use App\Services\TelegramAgent\Skills\RouteOrRefuseSkill;
use PHPUnit\Framework\TestCase;

final class RouteOrRefuseSkillTest extends TestCase
{
    public function test_name_returns_expected_slug(): void
    {
        $skill = new RouteOrRefuseSkill();
        $this->assertSame('route-or-refuse', $skill->name());
    }

    public function test_prompt_fragment_contains_key_phrases(): void
    {
        $skill = new RouteOrRefuseSkill();
        $text  = $skill->promptFragment();

        $this->assertStringContainsString('TUYỆT ĐỐI KHÔNG', $text);
        $this->assertStringContainsString('run_workflow', $text);
    }

    public function test_applies_to_returns_true_by_default(): void
    {
        $skill = new RouteOrRefuseSkill();
        $this->assertTrue($skill->appliesTo([]));
    }
}
