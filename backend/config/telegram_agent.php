<?php

declare(strict_types=1);

use App\Services\TelegramAgent\Skills\ComposeWorkflowSkill;
use App\Services\TelegramAgent\Skills\ExtractProductBriefSkill;
use App\Services\TelegramAgent\Skills\NoRamblingSkill;
use App\Services\TelegramAgent\Skills\RouteOrRefuseSkill;
use App\Services\TelegramAgent\Skills\VietnameseToneSkill;

return [
    /**
     * Skill classes composed into the Assistant system prompt (order = emphasis).
     *
     * @var list<class-string<\App\Services\TelegramAgent\Skills\Skill>>
     */
    'skills' => [
        RouteOrRefuseSkill::class,
        ComposeWorkflowSkill::class,
        ExtractProductBriefSkill::class,
        VietnameseToneSkill::class,
        NoRamblingSkill::class,
    ],
];
