<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\BehaviorSkills;

use App\Services\TelegramAgent\SystemPrompt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TG-07: Verify ComposeWorkflowBehaviorSkill is activated in the prompt pipeline.
 *
 * The skill is configured in config/telegram_agent.php behavior_skills array.
 * This test confirms the composed prompt includes the propose→explain→approve→persist
 * block, meaning the skill is wired through SystemPrompt::build().
 */
final class ComposeWorkflowActivationTest extends TestCase
{
    #[Test]
    public function system_prompt_includes_compose_workflow_behavior_skill_fragment(): void
    {
        $prompt = SystemPrompt::build([], 'test-chat');

        // Key phrases from ComposeWorkflowBehaviorSkill::promptFragment().
        $this->assertStringContainsString(
            'ComposeWorkflowTool',
            $prompt,
            'Compose skill must reference ComposeWorkflowTool'
        );

        $this->assertStringContainsString(
            'PersistWorkflowTool',
            $prompt,
            'Compose skill must reference PersistWorkflowTool for the approval path'
        );

        $this->assertStringContainsString(
            'RefinePlanTool',
            $prompt,
            'Compose skill must reference RefinePlanTool for the refinement path'
        );

        // The "propose → explain → approve → persist" lifecycle markers.
        $this->assertStringContainsString(
            'DRAFT',
            $prompt,
            'Compose skill must include DRAFT step instruction'
        );

        $this->assertStringContainsString(
            'EXPLAIN',
            $prompt,
            'Compose skill must include EXPLAIN step instruction'
        );

        $this->assertStringContainsString(
            'APPROVAL',
            $prompt,
            'Compose skill must include APPROVAL pattern'
        );

        $this->assertStringContainsString(
            'REFINEMENT',
            $prompt,
            'Compose skill must include REFINEMENT pattern'
        );
    }

    #[Test]
    public function compose_workflow_skill_appears_in_config_behavior_skills(): void
    {
        $skills = config('telegram_agent.behavior_skills', []);

        $this->assertContains(
            \App\Services\TelegramAgent\BehaviorSkills\ComposeWorkflowBehaviorSkill::class,
            $skills,
            'ComposeWorkflowBehaviorSkill must be in telegram_agent.behavior_skills config'
        );
    }
}
