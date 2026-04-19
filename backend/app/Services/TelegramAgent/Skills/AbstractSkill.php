<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Skills;

abstract class AbstractSkill implements Skill
{
    /**
     * @param  array<string, mixed>  $update
     */
    public function appliesTo(array $update): bool
    {
        return true;
    }
}
