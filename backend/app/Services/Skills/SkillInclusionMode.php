<?php

declare(strict_types=1);

namespace App\Services\Skills;

/**
 * Controls how a skill is surfaced to the agent.
 */
enum SkillInclusionMode: string
{
    /**
     * Agent sees only the skill's name and description — no full instructions.
     * Best for agents with many skills where context window is precious.
     */
    case Lite = 'lite';

    /**
     * Agent receives the full skill instructions inline in the prompt.
     * Use sparingly for skills that must always be available.
     */
    case Full = 'full';
}
