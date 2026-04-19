<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Domain\Nodes\NodeManifestBuilder;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Planner\WorkflowPlan;
use App\Domain\Planner\WorkflowPlanner;
use App\Domain\Planner\WorkflowPlanValidator;
use App\Services\TelegramAgent\AgentSessionStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Refine the pending workflow plan stored on {@see \App\Services\TelegramAgent\AgentSession}
 * based on the user's feedback. Re-calls the LLM with prior plan + feedback,
 * validates the refined plan, stores the updated plan back, increments attempt
 * counter. Bails with error JSON if the cap ({@see self::REFINEMENT_CAP}) is hit.
 *
 * CW2 — pairs with {@see ComposeWorkflowTool} (CW1) and {@see PersistWorkflowTool} (CW3).
 */
final class RefinePlanTool implements Tool
{
    public const REFINEMENT_CAP = 5;

    public function __construct(
        private readonly WorkflowPlanner $planner,
        private readonly NodeManifestBuilder $manifestBuilder,
        private readonly NodeTemplateRegistry $registry,
        private readonly WorkflowPlanValidator $validator,
        private readonly AgentSessionStore $sessionStore,
        public readonly string $chatId,
        public readonly string $botToken,
    ) {}

    public function description(): Stringable|string
    {
        return 'Refine the pending workflow plan based on user feedback. Reads the prior plan from session, re-invokes the planner with the feedback, stores the updated plan back. Call ONLY when the user asks for changes AND there is a pending plan.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'feedback' => $schema->string()
                ->description('The user\'s refinement request — verbatim. e.g. "thêm humor nhẹ", "đổi vibe aesthetic", "xoá imageGenerator".')
                ->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $feedback = trim((string) $request->string('feedback', ''));
        if (mb_strlen($feedback) < 3) {
            return json_encode([
                'error'   => 'feedback_too_short',
                'message' => 'Feedback quá ngắn, hãy mô tả rõ hơn.',
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $session = $this->sessionStore->load($this->chatId, $this->botToken);

        if ($session->pendingPlan === null) {
            return json_encode([
                'error'   => 'no_pending_plan',
                'message' => 'Chưa có plan nào để chỉnh — hãy tạo workflow mới trước.',
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        if ($session->pendingPlanAttempts >= self::REFINEMENT_CAP) {
            return json_encode([
                'error'   => 'refinement_cap_reached',
                'message' => 'Đã chỉnh ' . self::REFINEMENT_CAP . ' lần. Gõ "ok" để chốt plan hiện tại, hoặc "hủy" để bắt đầu lại.',
                'cap'     => self::REFINEMENT_CAP,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        // Hydrate prior plan from session.
        try {
            $priorPlan = WorkflowPlan::fromArray($session->pendingPlan);
        } catch (Throwable $e) {
            return json_encode([
                'error'   => 'prior_plan_corrupt',
                'message' => 'Plan hiện tại không đọc được: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        // Build a compact catalog preview — type/title/when_to_include/when_to_skip per node.
        $catalogPreview = [];
        foreach ($this->registry->all() as $template) {
            $guide = $template->plannerGuide();
            $catalogPreview[] = [
                'type'          => $template->type,
                'title'         => $template->title,
                'whenToInclude' => $guide->whenToInclude,
                'whenToSkip'    => $guide->whenToSkip,
            ];
        }

        $prompt = RefinePlanPrompt::build($priorPlan, $feedback, $catalogPreview);

        $agent = new AnonymousAgent(
            instructions: $prompt,
            messages: [],
            tools: [],
        );

        try {
            $response = $agent->prompt(
                prompt: 'REFINE_NOW',
                provider: (string) (config('ai.default') ?? 'fireworks'),
            );
        } catch (Throwable $e) {
            return json_encode([
                'error'   => 'llm_error',
                'message' => 'Lỗi khi gọi LLM: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $raw = $response instanceof AgentResponse ? $response->text : (string) $response;

        $json = $this->extractJson($raw);
        $parsed = json_decode($json, true);
        if (!is_array($parsed)) {
            return json_encode([
                'error'   => 'parse_failed',
                'message' => 'Không parse được response từ LLM.',
                'raw'     => mb_substr($raw, 0, 500),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        try {
            $refinedPlan = WorkflowPlan::fromArray($parsed);
        } catch (Throwable $e) {
            return json_encode([
                'error'   => 'hydrate_failed',
                'message' => 'Response không đúng schema: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $validation = $this->validator->validate($refinedPlan);
        if (!$validation->valid) {
            return json_encode([
                'error'  => 'validation_failed',
                'errors' => array_map(
                    static fn (array $e) => (string) ($e['message'] ?? 'unknown'),
                    array_slice($validation->errors, 0, 5),
                ),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $session->pendingPlan = $refinedPlan->toArray();
        $session->pendingPlanAttempts += 1;
        $this->sessionStore->save($session);

        $nodes = array_map(
            static fn ($n) => [
                'type'   => $n->type,
                'reason' => mb_substr($n->reason, 0, 120),
            ],
            $refinedPlan->nodes,
        );

        return json_encode([
            'available' => true,
            'attempt'   => $session->pendingPlanAttempts,
            'remaining' => self::REFINEMENT_CAP - $session->pendingPlanAttempts,
            'vibeMode'  => $refinedPlan->vibeMode,
            'nodes'     => $nodes,
            'rationale' => mb_substr($refinedPlan->rationale, 0, 400),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Lenient JSON extractor — strips markdown fences and returns the outermost
     * balanced {...} block. Copy of the logic in {@see WorkflowPlanner::parsePlan}
     * (kept private there; we inline a small version so this tool stays self-contained).
     */
    private function extractJson(string $raw): string
    {
        $cleaned = trim($raw);

        if (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```(?:json|JSON)?\s*\n?/', '', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/\n?```\s*$/', '', $cleaned) ?? $cleaned;
            $cleaned = trim($cleaned);
        }

        $len = strlen($cleaned);
        $start = -1;
        $depth = 0;
        $inString = false;
        $escape = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $cleaned[$i];

            if ($inString) {
                if ($escape) { $escape = false; continue; }
                if ($ch === '\\') { $escape = true; continue; }
                if ($ch === '"') { $inString = false; }
                continue;
            }

            if ($ch === '"') { $inString = true; continue; }
            if ($ch === '{') {
                if ($depth === 0) { $start = $i; }
                $depth++;
                continue;
            }
            if ($ch === '}') {
                $depth--;
                if ($depth === 0 && $start !== -1) {
                    return substr($cleaned, $start, $i - $start + 1);
                }
            }
        }

        return $cleaned;
    }
}
