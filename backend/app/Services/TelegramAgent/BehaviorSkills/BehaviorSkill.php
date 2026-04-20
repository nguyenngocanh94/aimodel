<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\BehaviorSkills;

/**
 * Composable instruction fragment for the Telegram Assistant system prompt.
 */
interface BehaviorSkill
{
    /**
     * Slug for logging, config, and ordering (e.g. "route-or-refuse").
     */
    public function name(): string;

    /**
     * Vietnamese-first instruction text merged into the composed system prompt.
     */
    public function promptFragment(): string;

    /**
     * When false, this skill is omitted for the given Telegram update.
     *
     * @param  array<string, mixed>  $update
     */
    public function appliesTo(array $update): bool;
}
