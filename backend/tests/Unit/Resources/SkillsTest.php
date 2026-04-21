<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use App\Services\Skills\SkillRegistry;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class SkillsTest extends TestCase
{
    public function test_skills_list_command_returns_7_telegram_slugs(): void
    {
        Artisan::call('skills:list');
        $output = Artisan::output();

        $expectedSlugs = [
            'list-workflows',
            'run-workflow',
            'get-run-status',
            'cancel-run',
            'reply',
            'compose-workflow',
            'catalog',
        ];

        foreach ($expectedSlugs as $slug) {
            $this->assertStringContainsString($slug, $output, "Expected slug '{$slug}' missing from skills:list output");
        }

        $this->assertStringContainsString('7 skill(s) found', $output);
    }

    public function test_skill_registry_discovers_all_7_skills(): void
    {
        $registry = new SkillRegistry();
        $slugs = $registry->slugs();

        $expectedSlugs = [
            'list-workflows',
            'run-workflow',
            'get-run-status',
            'cancel-run',
            'reply',
            'compose-workflow',
            'catalog',
        ];

        $this->assertCount(7, $slugs, 'Expected exactly 7 skills');
        foreach ($expectedSlugs as $slug) {
            $this->assertContains($slug, $slugs, "Expected slug '{$slug}' not found in registry");
        }
    }

    public function test_lite_skills_have_no_tool_classes(): void
    {
        $registry = new SkillRegistry();

        $liteSlugs = ['list-workflows', 'get-run-status', 'cancel-run', 'catalog'];
        foreach ($liteSlugs as $slug) {
            $skill = $registry->get($slug);
            $this->assertNotNull($skill, "Skill '{$slug}' not found");
            $this->assertSame('lite', $skill->mode->value, "Skill '{$slug}' should be lite mode");
        }
    }

    public function test_full_skills_have_tool_classes(): void
    {
        $registry = new SkillRegistry();

        $fullSlugs = ['reply', 'run-workflow', 'compose-workflow'];
        foreach ($fullSlugs as $slug) {
            $skill = $registry->get($slug);
            $this->assertNotNull($skill, "Skill '{$slug}' not found");
            $this->assertSame('full', $skill->mode->value, "Skill '{$slug}' should be full mode");
            $this->assertNotEmpty($skill->toolClasses, "Full skill '{$slug}' should have tool classes");
        }
    }

    public function test_skill_body_is_not_empty_for_all_skills(): void
    {
        $registry = new SkillRegistry();

        foreach ($registry->all() as $slug => $skill) {
            $this->assertNotEmpty($skill->body, "Skill '{$slug}' body should not be empty");
        }
    }
}
