<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

use App\Services\TelegramAgent\BehaviorSkills\BehaviorSkill;
use App\Services\TelegramAgent\BehaviorSkills\BehaviorSkillComposer;

/**
 * Composes the TelegramAgent system prompt from configured skills + catalog preview.
 */
final class SystemPrompt
{
    /**
     * Build the system prompt for the agent.
     *
     * @param  array<int, array{slug: string, name: string, nl_description: string|null, param_schema: array|null}>  $catalogPreview
     * @param  array<string, mixed>  $update  Telegram update (for skill appliesTo filters).
     */
    public static function build(array $catalogPreview, string $chatId, array $update = []): string
    {
        /** @var list<class-string<BehaviorSkill>> $skillClasses */
        $skillClasses = config('telegram_agent.behavior_skills', []);

        $skills = [];

        foreach ($skillClasses as $class) {
            $skills[] = app($class);
        }

        return app(BehaviorSkillComposer::class)->compose($skills, $update, $catalogPreview, $chatId);
    }
}
