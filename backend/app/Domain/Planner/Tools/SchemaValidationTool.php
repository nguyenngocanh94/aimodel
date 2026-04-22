<?php

declare(strict_types=1);

namespace App\Domain\Planner\Tools;

use App\Domain\Planner\WorkflowPlan;
use App\Domain\Planner\WorkflowPlanValidator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Planner tool: validate a draft {@see WorkflowPlan} against the node
 * manifest + planner rules BEFORE the model commits a final JSON emission.
 *
 * Returns {valid, errors, warnings, hint} so the agent loop can self-correct
 * cheaply — each tool call is an order of magnitude cheaper than a full
 * retry round of the outer planner.
 *
 * LK-F3 — see docs/plans/2026-04-19-laravel-ai-capabilities.md.
 *
 * TODO(Gap B): once Completeness LC2 lands, bridge this through
 * HasStructuredOutput so the final JSON is emitted via the structured-output
 * pathway instead of text-parsing.
 */
final class SchemaValidationTool implements PlannerTool
{
    public function __construct(
        private readonly WorkflowPlanValidator $validator,
    ) {}

    public function description(): string
    {
        return 'Validate a draft plan (JSON matching OUTPUT JSON SCHEMA) against the node '
            . 'manifest + planner rules. Call BEFORE emitting final JSON. Returns '
            . '{valid:bool, errors:list, warnings:list, hint:string}. Only emit a final '
            . 'plan when valid:true.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_json' => $schema->string()
                ->description('The draft plan as a JSON string, same shape as OUTPUT JSON SCHEMA.')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $rawJson = (string) $request->string('plan_json', '');

        if ($rawJson === '') {
            return $this->response(
                valid: false,
                errors: [[
                    'path' => 'plan_json',
                    'code' => 'empty_input',
                    'message' => 'plan_json is required',
                ]],
                hint: 'Pass the full draft plan JSON object in the plan_json argument.',
            );
        }

        try {
            $data = json_decode($rawJson, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                throw new \RuntimeException('plan_json must decode to an object');
            }
            $plan = WorkflowPlan::fromArray($data);
        } catch (Throwable $e) {
            return $this->response(
                valid: false,
                errors: [[
                    'path' => 'plan_json',
                    'code' => 'parse_error',
                    'message' => $e->getMessage(),
                ]],
                hint: 'Ensure plan_json is a JSON object literal with keys {nodes, edges}.',
            );
        }

        try {
            $validation = $this->validator->validate($plan);
        } catch (Throwable $e) {
            return $this->response(
                valid: false,
                errors: [[
                    'path' => 'plan',
                    'code' => 'validator_error',
                    'message' => $e->getMessage(),
                ]],
                hint: 'The validator itself raised — likely a corrupt node reference. Re-check node types against the catalog.',
            );
        }

        return $this->response(
            valid: $validation->valid,
            errors: $validation->errors,
            warnings: $validation->warnings,
            hint: $validation->valid
                ? 'Plan validated. Safe to emit final JSON.'
                : 'Fix the errors listed and re-run this tool before emitting final JSON.',
        );
    }

    /**
     * @param list<array{path:string,code:string,message:string,context?:array}>  $errors
     * @param list<array{path:string,code:string,message:string,context?:array}>  $warnings
     */
    private function response(bool $valid, array $errors = [], array $warnings = [], string $hint = ''): string
    {
        return json_encode(
            [
                'valid' => $valid,
                'errors' => $errors,
                'warnings' => $warnings,
                'hint' => $hint,
            ],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
