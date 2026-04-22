<?php

declare(strict_types=1);

namespace App\Domain\Planner;

use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeManifestBuilder;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Models\PastPlan;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Brief-to-{@see WorkflowPlan} planner.
 *
 * Lifecycle:
 *   Brief → prompt builder → LLM round(s) → JSON parse → plan hydrate →
 *   {@see WorkflowPlanValidator} → return on pass, else retry with error feedback.
 *
 * Downstream consumers (see 645.5 / 645.8 / ComposeWorkflowTool):
 *   - Validation errors are NOT translated into exceptions — callers inspect
 *     {@see PlannerResult::$validation} to decide what to surface.
 *   - {@see PlannerResult::$steps} carries a full trace so drift-eval can
 *     replay attempts.
 */
final class WorkflowPlanner
{
    public function __construct(
        private readonly Container $app,
        private readonly NodeTemplateRegistry $registry,
        private readonly NodeManifestBuilder $manifestBuilder,
        private readonly WorkflowPlanValidator $validator,
    ) {}

    public function plan(PlannerInput $input): PlannerResult
    {
        $catalog = $this->buildCatalog();
        $providerName = $input->provider ?? (string) (config('ai.default') ?? 'fireworks');
        $modelName = $input->model ?? '';

        /** @var list<PlannerAttempt> $attempts */
        $attempts = [];
        $currentPrompt = WorkflowPlannerPrompt::build($input, $catalog);
        $previousRawOutput = '';
        $previousErrors = [];
        $previousParseError = null;

        $maxRounds = $input->maxRetries + 1;
        $finalValidation = WorkflowPlanValidation::withErrors([[
            'path' => 'plan',
            'code' => 'planner_no_output',
            'message' => 'Planner produced no output',
        ]]);
        $finalPlan = null;

        for ($round = 1; $round <= $maxRounds; $round++) {
            if ($round > 1) {
                $currentPrompt = WorkflowPlannerPrompt::retry(
                    $input,
                    $catalog,
                    $previousRawOutput,
                    $previousErrors,
                    $previousParseError,
                );
            }

            [$raw, $structured] = $this->invokeLlm($input, $currentPrompt, $providerName, $modelName);

            // Hydrate WorkflowPlan from the already-decoded structured response.
            // The schema is enforced by the gateway, so fence-strip / lenient
            // JSON parsing is not needed. Parse errors here mean the provider
            // returned an empty/off-schema payload, which we treat as a retry.
            $parseError = null;
            $plan = null;
            try {
                if ($structured === []) {
                    throw new \RuntimeException('empty structured output from LLM');
                }
                $plan = WorkflowPlan::fromArray($structured);
            } catch (Throwable $e) {
                $parseError = $e->getMessage();
            }

            if ($plan === null) {
                $attempts[] = new PlannerAttempt(
                    round: $round,
                    promptUsed: $currentPrompt,
                    rawLlmOutput: $raw,
                    parsedPlan: null,
                    validation: null,
                    parseError: $parseError,
                );
                $previousRawOutput = $raw;
                $previousErrors = [];
                $previousParseError = $parseError;
                continue;
            }

            $validation = $this->validator->validate($plan);
            $attempts[] = new PlannerAttempt(
                round: $round,
                promptUsed: $currentPrompt,
                rawLlmOutput: $raw,
                parsedPlan: $plan,
                validation: $validation,
            );

            $finalValidation = $validation;
            $finalPlan = $plan;

            if ($validation->valid) {
                $this->persistSuccessfulPlan($input, $plan, $providerName, $modelName);

                return new PlannerResult(
                    plan: $plan,
                    validation: $validation,
                    attempts: $round,
                    steps: $attempts,
                    modelUsed: $this->resolvedModel($attempts, $modelName),
                    providerUsed: $providerName,
                );
            }

            $previousRawOutput = $raw;
            $previousErrors = $validation->errors;
            $previousParseError = null;
        }

        return new PlannerResult(
            plan: $finalPlan, // last parsed plan (may be structurally broken) or null
            validation: $finalValidation,
            attempts: count($attempts),
            steps: $attempts,
            modelUsed: $this->resolvedModel($attempts, $modelName),
            providerUsed: $providerName,
        );
    }

    /**
     * @return list<NodeGuide>
     */
    /**
     * @return list<object>
     */
    /**
     * @return list<object>
     */
    protected function plannerTools(): array
    {
        // tagged() returns RewindableGenerator when tag exists, [] otherwise.
        /** @var iterable<object>|array $tagged */
        $tagged = $this->app->tagged('planner.tools');

        return $tagged === [] ? [] : array_values(iterator_to_array($tagged));
    }

    /**
     * @return list<NodeGuide>
     */
    private function buildCatalog(): array
    {
        $guides = $this->registry->guides();
        // Stable order by nodeId for deterministic prompt hashes.
        ksort($guides);
        return array_values($guides);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>} [rawText, structuredPayload]
     */
    private function invokeLlm(PlannerInput $input, string $prompt, string $providerName, string $modelName): array
    {
        // WorkflowPlannerAgent implements HasProviderOptions so the system
        // prompt is cached against Anthropic (LC3). On non-Anthropic providers,
        // providerOptions() returns [] — plain `system` string path, no regression.
        $agent = new WorkflowPlannerAgent(
            instructions: $prompt,
            messages: [],
            tools: $this->plannerTools(),
            schema: self::planSchema(),
        );

        // Empty user prompt — the "real" prompt sits in instructions. Most
        // Chat Completion providers still require a user turn, so we pass
        // a nominal marker. For hermeticity in tests, Http::fake catches the
        // call before it leaves the process.
        $userTurn = 'PLAN_NOW';

        $response = $agent->prompt(
            prompt: $userTurn,
            provider: $providerName,
            model: $modelName === '' ? null : $modelName,
        );

        $raw = $response instanceof AgentResponse ? $response->text : (string) $response;
        $structured = $response instanceof StructuredAgentResponse
            ? (is_array($response->structured) ? $response->structured : [])
            : [];

        return [$raw, $structured];
    }

    /**
     * JSON schema mirroring {@see WorkflowPlan::fromArray}. Used by
     * {@see StructuredAnonymousAgent} + {@see RefinePlanTool} so the gateway
     * enforces plan shape and we hydrate the typed plan directly.
     */
    public static function planSchema(): \Closure
    {
        return static fn (JsonSchema $s) => [
            'intent'   => $s->string(),
            'vibeMode' => $s->string(),
            'nodes'    => $s->array()->items($s->object([
                'id'     => $s->string(),
                'type'   => $s->string(),
                'config' => $s->object(),
                'reason' => $s->string(),
                'label'  => $s->string(),
            ])),
            'edges' => $s->array()->items($s->object([
                'sourceNodeId'  => $s->string(),
                'sourcePortKey' => $s->string(),
                'targetNodeId'  => $s->string(),
                'targetPortKey' => $s->string(),
                'reason'        => $s->string(),
            ])),
            'assumptions' => $s->array()->items($s->string()),
            'rationale'   => $s->string(),
            'meta'        => $s->object([
                'plannerVersion' => $s->string(),
            ]),
        ];
    }

    /**
     * Persist a successful plan for the PriorPlanRetrievalTool. Guarded by
     * config('planner.persist_plans'); errors are swallowed to avoid breaking
     * the planner's happy path (e.g. missing migration in tests).
     */
    private function persistSuccessfulPlan(
        PlannerInput $input,
        WorkflowPlan $plan,
        string $providerName,
        string $modelName,
    ): void {
        if (! (bool) config('planner.persist_plans', true)) {
            return;
        }

        try {
            PastPlan::create([
                'brief' => $input->brief,
                'brief_hash' => PastPlan::hashBrief($input->brief),
                'plan' => $plan->toArray(),
                'provider' => $providerName !== '' ? $providerName : null,
                'model' => $modelName !== '' ? $modelName : null,
            ]);
        } catch (Throwable $e) {
            // Persistence is best-effort — log and move on.
            Log::warning('WorkflowPlanner: failed to persist past plan', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param list<PlannerAttempt> $attempts
     */
    private function resolvedModel(array $attempts, string $requested): string
    {
        if ($requested !== '') {
            return $requested;
        }
        // Fallback: report the configured provider's model from config/ai.php if discoverable.
        $default = config('ai.providers.fireworks.models.text.default');
        return is_string($default) && $default !== '' ? $default : 'unknown';
    }
}
