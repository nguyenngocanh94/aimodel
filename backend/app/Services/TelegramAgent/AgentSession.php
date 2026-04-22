<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent;

/**
 * Per-chat conversation state for the Telegram Assistant.
 *
 * Owned by {@see AgentSessionStore} which persists it to Redis keyed on
 * "{chatId}:{botToken}".  Only ephemeral state lives here — nothing that
 * would be a mystery if it evaporated after an hour.
 *
 * Current fields:
 *   - pendingPlan:          serialized {@see \App\Domain\Planner\WorkflowPlan}
 *                           awaiting user approval / refinement (CW1+).
 *   - pendingPlanAttempts:  1 after the first compose, +1 per refine round.
 */
final class AgentSession
{
    public function __construct(
        public readonly string $chatId,
        public readonly string $botToken,
        public ?array $pendingPlan = null,
        public int $pendingPlanAttempts = 0,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'chatId' => $this->chatId,
            'botToken' => $this->botToken,
            'pendingPlan' => $this->pendingPlan,
            'pendingPlanAttempts' => $this->pendingPlanAttempts,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, string $chatId, string $botToken): self
    {
        $pendingPlan = $data['pendingPlan'] ?? null;
        if ($pendingPlan !== null && !is_array($pendingPlan)) {
            $pendingPlan = null;
        }

        return new self(
            chatId: $chatId,
            botToken: $botToken,
            pendingPlan: $pendingPlan,
            pendingPlanAttempts: (int) ($data['pendingPlanAttempts'] ?? 0),
        );
    }
}
