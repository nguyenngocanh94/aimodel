<?php

declare(strict_types=1);

namespace App\Domain\Planner;

use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeManifestBuilder;
use App\Domain\Nodes\NodeTemplateRegistry;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Responses\AgentResponse;
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

            $raw = $this->invokeLlm($input, $currentPrompt, $providerName, $modelName);

            // Parse JSON (lenient — strip fences / commentary first).
            $parseError = null;
            $plan = null;
            try {
                $plan = $this->parsePlan($raw);
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
    private function buildCatalog(): array
    {
        $guides = $this->registry->guides();
        // Stable order by nodeId for deterministic prompt hashes.
        ksort($guides);
        return array_values($guides);
    }

    private function invokeLlm(PlannerInput $input, string $prompt, string $providerName, string $modelName): string
    {
        $agent = new AnonymousAgent(
            instructions: $prompt,
            messages: [],
            tools: [],
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

        return $response instanceof AgentResponse ? $response->text : (string) $response;
    }

    /**
     * Lenient JSON parsing — strips markdown fences and pre/post commentary.
     * Throws on unrecoverable parse failure; caller treats as retry trigger.
     */
    private function parsePlan(string $raw): WorkflowPlan
    {
        $cleaned = trim($raw);

        // Strip markdown code fences if present (```json ... ``` or ``` ... ```).
        if (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```(?:json|JSON)?\s*\n?/', '', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/\n?```\s*$/', '', $cleaned) ?? $cleaned;
            $cleaned = trim($cleaned);
        }

        // If the model included prose before/after JSON, try to extract the
        // outermost balanced {...} block.
        $json = $this->extractJsonObject($cleaned);
        if ($json === null) {
            throw new \RuntimeException('no JSON object found in LLM output');
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $jsonError = json_last_error_msg();
            throw new \RuntimeException("invalid JSON: {$jsonError}");
        }

        return WorkflowPlan::fromArray($data);
    }

    /**
     * Extract the outermost JSON object from a string by brace-counting.
     * Handles strings (with escaped quotes) without going full parser.
     */
    private function extractJsonObject(string $s): ?string
    {
        $len = strlen($s);
        $start = -1;
        $depth = 0;
        $inString = false;
        $escape = false;

        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];

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
                    return substr($s, $start, $i - $start + 1);
                }
            }
        }

        return null;
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
