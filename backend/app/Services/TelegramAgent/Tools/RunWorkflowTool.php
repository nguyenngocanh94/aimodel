<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class RunWorkflowTool implements Tool
{
    public function __construct(
        public readonly string $chatId,
    ) {}

    public function description(): string
    {
        return "Start a workflow run. Provide the slug and the params the user has supplied. Params are validated against the workflow's param_schema.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug'   => $schema->string()->description('Workflow slug from the catalog')->required(),
            'params' => $schema->object()->description('Params matching the workflow schema')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $slug   = (string) $request->string('slug', '');
        $params = is_array($request['params'] ?? null) ? $request['params'] : [];

        // Resolve workflow
        $workflow = Workflow::triggerable()->bySlug($slug)->first();

        if ($workflow === null) {
            return json_encode(['error' => 'workflow_not_found', 'slug' => $slug], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        // Validate params against the workflow's param_schema
        $schema = $workflow->param_schema ?? [];

        if ($schema !== []) {
            $validator = Validator::make($params, $schema);

            if ($validator->fails()) {
                return json_encode([
                    'error'  => 'validation_failed',
                    'fields' => $validator->errors()->toArray(),
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        }

        // Build document snapshot
        $document = $workflow->document ?? ['nodes' => [], 'edges' => []];

        // Synthesize _triggerPayload for back-compat with telegramTrigger nodes
        $promptText    = (string) ($params['prompt'] ?? $params['productBrief'] ?? '');
        $triggerPayload = [
            'message' => [
                'chat'       => ['id' => (int) $this->chatId],
                'text'       => $promptText,
                'date'       => time(),
                'message_id' => 0,
            ],
            '_intake' => [
                'textParts'    => [$promptText],
                'imageUrls'    => [],
                'combinedText' => $promptText,
            ],
            'update_id' => 0,
        ];

        // Inject _agentParams into the first node's config and inject _triggerPayload
        // into any telegramTrigger node for backward compatibility
        $nodes = $document['nodes'] ?? [];

        foreach ($nodes as $index => &$node) {
            // Inject _agentParams into the first node
            if ($index === 0) {
                if (isset($node['data']['config'])) {
                    $node['data']['config']['_agentParams'] = $params;
                } else {
                    $node['config'] = array_merge($node['config'] ?? [], ['_agentParams' => $params]);
                }
            }

            // Inject _triggerPayload into any telegramTrigger node
            if (($node['type'] ?? '') === 'telegramTrigger') {
                if (isset($node['data']['config'])) {
                    $node['data']['config']['_triggerPayload'] = $triggerPayload;
                } else {
                    $node['config'] = array_merge($node['config'] ?? [], ['_triggerPayload' => $triggerPayload]);
                }
            }
        }
        unset($node);

        $document['nodes'] = $nodes;

        // Compute hashes matching TelegramWebhookController convention
        $documentHash = hash('sha256', json_encode($document, JSON_THROW_ON_ERROR));

        $nodeConfigHashes = [];
        foreach ($document['nodes'] as $node) {
            $config                          = $node['data']['config'] ?? $node['config'] ?? [];
            $nodeConfigHashes[$node['id']]   = hash('sha256', json_encode($config, JSON_THROW_ON_ERROR));
        }

        // Create execution run
        $run = ExecutionRun::create([
            'workflow_id'       => $workflow->id,
            'trigger'           => 'telegramWebhook',
            'target_node_id'    => null,
            'status'            => 'pending',
            'document_snapshot' => $document,
            'document_hash'     => $documentHash,
            'node_config_hashes' => $nodeConfigHashes,
        ]);

        RunWorkflowJob::dispatch($run->id);

        return json_encode([
            'runId'    => $run->id,
            'status'   => $run->status,
            'workflow' => $workflow->name,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
