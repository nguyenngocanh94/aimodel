<?php

declare(strict_types=1);

use App\Services\TelegramAgent\BehaviorSkills\ComposeWorkflowBehaviorSkill;
use App\Services\TelegramAgent\BehaviorSkills\ExtractProductBriefBehaviorSkill;
use App\Services\TelegramAgent\BehaviorSkills\NoRamblingBehaviorSkill;
use App\Services\TelegramAgent\BehaviorSkills\RouteOrRefuseBehaviorSkill;
use App\Services\TelegramAgent\BehaviorSkills\VietnameseToneBehaviorSkill;

return [
    /**
     * Behavior skill classes composed into the Assistant system prompt (order = emphasis).
     * These are prompt guardrails, NOT tools — see resources/skills/ for sdk-skills tool capsules.
     *
     * @var list<class-string<\App\Services\TelegramAgent\BehaviorSkills\BehaviorSkill>>
     */
    'behavior_skills' => [
        RouteOrRefuseBehaviorSkill::class,
        ComposeWorkflowBehaviorSkill::class,
        ExtractProductBriefBehaviorSkill::class,
        VietnameseToneBehaviorSkill::class,
        NoRamblingBehaviorSkill::class,
    ],
];
