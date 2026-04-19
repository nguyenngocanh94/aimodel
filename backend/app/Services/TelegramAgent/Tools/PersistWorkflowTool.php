<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Domain\Planner\WorkflowPlan;
use App\Domain\Planner\WorkflowPlanToDocumentConverter;
use App\Models\Workflow;
use App\Services\TelegramAgent\AgentSessionStore;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Persist the pending plan (from {@see AgentSession::$pendingPlan}) as a
 * triggerable {@see Workflow} row.
 *
 * MUST only be called after explicit user approval ("ok", "được", "chốt",
 * "đồng ý"). CW3 — conversational workflow composition epic.
 */
final class PersistWorkflowTool implements Tool
{
    public function __construct(
        private readonly WorkflowPlanToDocumentConverter $converter,
        private readonly AgentSessionStore $sessionStore,
        public readonly string $chatId,
        public readonly string $botToken,
    ) {}

    public function description(): Stringable|string
    {
        return 'Persist the pending plan as a triggerable workflow. Call ONLY when the user has explicitly approved (e.g. said "ok", "được", "chốt", "đồng ý"). Do NOT call without approval.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()
                ->description('Kebab-case slug — short descriptor derived from the brief topic. Example: "health-tvc-9x16", "chocopie-short-video".')
                ->required(),
            'name' => $schema->string()
                ->description('Human-readable name for the workflow catalog. Example: "Health TVC 9:16", "Chocopie Short Video".')
                ->required(),
            'description' => $schema->string()
                ->description('Optional human-friendly nl_description. If omitted, one is auto-generated from the plan intent.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $rawSlug     = trim((string) $request->string('slug', ''));
        $name        = trim((string) $request->string('name', ''));
        $description = trim((string) $request->string('description', ''));

        if ($rawSlug === '' || $name === '') {
            return json_encode(
                ['error' => 'missing_fields', 'message' => 'Cần cả slug và name.'],
                JSON_UNESCAPED_UNICODE,
            );
        }

        // Normalise to kebab-case (Str::slug strips diacritics + lowercases).
        $slug = Str::slug($rawSlug);
        if ($slug === '' || mb_strlen($slug) > 120) {
            return json_encode(
                ['error' => 'invalid_slug', 'message' => 'Slug không hợp lệ.'],
                JSON_UNESCAPED_UNICODE,
            );
        }

        $session = $this->sessionStore->load($this->chatId, $this->botToken);
        if ($session->pendingPlan === null) {
            return json_encode(
                ['error' => 'no_pending_plan', 'message' => 'Chưa có plan để lưu — hãy tạo workflow mới trước.'],
                JSON_UNESCAPED_UNICODE,
            );
        }

        // Slug collision: try reserving, escalating to -v2, -v3 if prior-planner
        // rows own this slug.
        $finalSlug = $this->resolveSlugCollision($slug);
        if (is_array($finalSlug)) {
            // Non-planner workflow owns this slug — refuse and suggest alternative.
            return json_encode($finalSlug, JSON_UNESCAPED_UNICODE);
        }

        // Hydrate plan DTO.
        try {
            $plan = WorkflowPlan::fromArray($session->pendingPlan);
        } catch (\Throwable $e) {
            return json_encode(
                ['error' => 'hydrate_failed', 'message' => 'Plan trong session hỏng: ' . $e->getMessage()],
                JSON_UNESCAPED_UNICODE,
            );
        }

        $document = $this->converter->convert($plan);

        $autoDescription = 'Auto-generated workflow từ brief: ' . mb_substr($plan->intent, 0, 280);

        $workflow = Workflow::create([
            'name'           => $name,
            'slug'           => $finalSlug,
            'triggerable'    => true,
            'schema_version' => 1,
            'nl_description' => $description !== '' ? $description : $autoDescription,
            'param_schema'   => ['productBrief' => ['required', 'string', 'min:5']],
            'document'       => $document,
            'tags'           => ['planner', 'v1', $plan->vibeMode],
        ]);

        // Clear session after successful persist.
        $session->pendingPlan         = null;
        $session->pendingPlanAttempts = 0;
        $this->sessionStore->save($session);

        return json_encode([
            'workflowId'  => (string) $workflow->id,
            'slug'        => $workflow->slug,
            'name'        => $workflow->name,
            'triggerable' => true,
            'message'     => "Đã lưu workflow `{$workflow->slug}`. Gõ /list để xem hoặc 'chạy {$workflow->slug} cho <sản phẩm>' để dùng.",
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Resolve slug collisions.
     *
     * @return string|array<string, string>  string = final (safe) slug; array = error payload.
     */
    private function resolveSlugCollision(string $slug): string|array
    {
        $existing = Workflow::where('slug', $slug)->first();
        if ($existing === null) {
            return $slug;
        }

        $tags      = (array) ($existing->tags ?? []);
        $isPlanner = in_array('planner', $tags, true);

        if (!$isPlanner) {
            // Non-planner workflow owns this slug — caller must pick a different name.
            return [
                'error'      => 'slug_reserved',
                'message'    => "Slug `{$slug}` đã được dùng cho workflow khác. Hãy chọn slug khác — thử `{$slug}-v2`?",
                'suggestion' => "{$slug}-v2",
            ];
        }

        // Planner-owned collision: auto-append -vN.
        for ($n = 2; $n < 100; $n++) {
            $candidate = "{$slug}-v{$n}";
            if (Workflow::where('slug', $candidate)->doesntExist()) {
                return $candidate;
            }
        }

        return ['error' => 'slug_exhausted', 'message' => 'Đã có quá nhiều phiên bản của slug này. Chọn tên khác.'];
    }
}
