<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\BehaviorSkills;

abstract class AbstractBehaviorSkill implements BehaviorSkill
{
    /**
     * @param  array<string, mixed>  $update
     */
    public function appliesTo(array $update): bool
    {
        return true;
    }
}
