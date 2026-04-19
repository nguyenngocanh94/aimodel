<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Domain\Planner\PlannerInput;
use App\Domain\Planner\WorkflowPlan;
use App\Domain\Planner\WorkflowPlanner;
use App\Services\TelegramAgent\AgentSessionStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Draft a new workflow from a user brief. Calls {@see WorkflowPlanner}, stashes
 * the result as a pending plan in {@see \App\Services\TelegramAgent\AgentSession},
 * and returns a compact summary for the skill to explain to the user.
 *
 * CW1 — persists NOTHING to the workflow catalog. Approval + persist flows
 * live in {@see PersistWorkflowTool} (CW3).
 */
final class ComposeWorkflowTool implements Tool
{
    public function __construct(
        private readonly WorkflowPlanner $planner,
        private readonly AgentSessionStore $sessionStore,
        public readonly string $chatId,
        public readonly string $botToken,
    ) {}

    public function description(): Stringable|string
    {
        return 'Draft a new workflow from a user brief. Returns a plan (nodes, edges, knobs, rationale) WITHOUT persisting. You MUST follow this call with a reply explaining the plan in Vietnamese and asking for approval.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'brief' => $schema->string()
                ->description("The user's full creative brief — the message they sent. Include product, audience, tone, platform, format.")
                ->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $brief = (string) $request->string('brief', '');

        if (mb_strlen($brief) < 10) {
            return json_encode([
                'available' => false,
                'reason'    => 'Brief quá ngắn, cần ít nhất 10 ký tự.',
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $result = $this->planner->plan(new PlannerInput(brief: $brief));

        if (!$result->successful()) {
            $firstError = $result->validation->errors[0] ?? ['message' => 'Unknown validation error'];
            return json_encode([
                'available' => false,
                'reason'    => 'Không tạo được plan hợp lệ: ' . ((string) ($firstError['message'] ?? 'unknown')),
                'attempts'  => $result->attempts,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $plan = $result->plan;
        assert($plan instanceof WorkflowPlan);

        $session = $this->sessionStore->load($this->chatId, $this->botToken);
        $session->pendingPlan = $plan->toArray();
        $session->pendingPlanAttempts = 1;
        $this->sessionStore->save($session);

        $knobCount = array_sum(array_map(
            static fn ($node) => count((array) $node->config),
            $plan->nodes,
        ));

        $nodes = array_map(
            static fn ($node) => [
                'type'   => $node->type,
                'reason' => mb_substr($node->reason, 0, 120),
            ],
            $plan->nodes,
        );

        return json_encode([
            'available' => true,
            'vibeMode'  => $plan->vibeMode,
            'nodes'     => $nodes,
            'knobCount' => $knobCount,
            'rationale' => mb_substr($plan->rationale, 0, 400),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
