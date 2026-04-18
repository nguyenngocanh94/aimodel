<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use App\Services\Anthropic\ToolDefinition;
use App\Services\TelegramAgent\AgentContext;
use App\Services\TelegramAgent\AgentTool;
use Illuminate\Support\Facades\Validator;

final class RunWorkflowTool implements AgentTool
{
    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'run_workflow',
            description: "Start a workflow run. Provide the slug and the params the user has supplied. Params are validated against the workflow's param_schema.",
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'slug' => ['type' => 'string'],
                    'params' => ['type' => 'object'],
                ],
                'required' => ['slug', 'params'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(array $input, AgentContext $ctx): array
    {
        $slug = (string) ($input['slug'] ?? '');
        $params = is_array($input['params'] ?? null) ? $input['params'] : [];

        // Resolve workflow
        $workflow = Workflow::triggerable()->bySlug($slug)->first();

        if ($workflow === null) {
            return ['error' => 'workflow_not_found', 'slug' => $slug];
        }

        // Validate params against the workflow's param_schema
        $schema = $workflow->param_schema ?? [];

        if ($schema !== []) {
            $validator = Validator::make($params, $schema);

            if ($validator->fails()) {
                return [
                    'error' => 'validation_failed',
                    'fields' => $validator->errors()->toArray(),
                ];
            }
        }

        // Build document snapshot
        $document = $workflow->document ?? ['nodes' => [], 'edges' => []];

        // Synthesize _triggerPayload for back-compat with telegramTrigger nodes
        $promptText = (string) ($params['prompt'] ?? $params['productBrief'] ?? '');
        $triggerPayload = [
            'message' => [
                'chat' => ['id' => (int) $ctx->chatId],
                'text' => $promptText,
                'date' => time(),
                'message_id' => 0,
            ],
            '_intake' => [
                'textParts' => [$promptText],
                'imageUrls' => [],
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
            $config = $node['data']['config'] ?? $node['config'] ?? [];
            $nodeConfigHashes[$node['id']] = hash('sha256', json_encode($config, JSON_THROW_ON_ERROR));
        }

        // Create execution run
        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'telegramWebhook',
            'target_node_id' => null,
            'status' => 'pending',
            'document_snapshot' => $document,
            'document_hash' => $documentHash,
            'node_config_hashes' => $nodeConfigHashes,
        ]);

        RunWorkflowJob::dispatch($run->id);

        return [
            'runId' => $run->id,
            'status' => $run->status,
            'workflow' => $workflow->name,
        ];
    }
}
