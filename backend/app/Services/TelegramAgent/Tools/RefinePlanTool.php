<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Domain\Nodes\NodeManifestBuilder;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Planner\WorkflowPlan;
use App\Domain\Planner\WorkflowPlanner;
use App\Domain\Planner\WorkflowPlannerAgent;
use App\Domain\Planner\WorkflowPlanValidator;
use App\Services\TelegramAgent\AgentSessionStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Responses\StructuredAgentResponse;
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

        // Use the named WorkflowPlannerAgent so refinement round-trips also
        // benefit from Anthropic prompt caching on the refiner system prompt.
        $agent = new WorkflowPlannerAgent(
            instructions: $prompt,
            messages: [],
            tools: [],
            schema: WorkflowPlanner::planSchema(),
        );

        try {
            $providerArg = array_values(array_map(
                'strval',
                (array) config('ai.failover.text', [config('ai.default') ?? 'fireworks'])
            ));
            // #region agent log
            \Illuminate\Support\Facades\Log::info('debug.llm.refine.request', [
                'sessionId' => '477860',
                'runId' => 'post-fix',
                'hypothesisId' => 'H14',
                'location' => 'RefinePlanTool.php:124',
                'provider' => $providerArg,
                'promptLength' => mb_strlen($prompt),
                'promptPreview' => mb_substr($prompt, 0, 500),
                'timestamp' => (int) round(microtime(true) * 1000),
            ]);
            // #endregion
            $response = $agent->prompt(
                prompt: 'REFINE_NOW',
                provider: $providerArg,
            );
            // #region agent log
            \Illuminate\Support\Facades\Log::info('debug.llm.refine.response', [
                'sessionId' => '477860',
                'runId' => 'post-fix',
                'hypothesisId' => 'H14',
                'location' => 'RefinePlanTool.php:138',
                'responseTextLength' => mb_strlen((string) ($response->text ?? '')),
                'responseTextPreview' => mb_substr((string) ($response->text ?? ''), 0, 500),
                'timestamp' => (int) round(microtime(true) * 1000),
            ]);
            // #endregion
        } catch (Throwable $e) {
            // #region agent log
            \Illuminate\Support\Facades\Log::error('debug.llm.refine.error', [
                'sessionId' => '477860',
                'runId' => 'post-fix',
                'hypothesisId' => 'H14',
                'location' => 'RefinePlanTool.php:149',
                'error' => $e->getMessage(),
                'exceptionClass' => $e::class,
                'timestamp' => (int) round(microtime(true) * 1000),
            ]);
            // #endregion
            return json_encode([
                'error'   => 'llm_error',
                'message' => 'Lỗi khi gọi LLM: ' . $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $structured = $response instanceof StructuredAgentResponse && is_array($response->structured)
            ? $response->structured
            : [];

        if ($structured === []) {
            return json_encode([
                'error'   => 'parse_failed',
                'message' => 'LLM trả về payload rỗng hoặc không đúng schema.',
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        try {
            $refinedPlan = WorkflowPlan::fromArray($structured);
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

}
