<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;

class UserPromptTemplate extends NodeTemplate
{
    public string $type { get => 'userPrompt'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'User Prompt'; }
    public NodeCategory $category { get => NodeCategory::Input; }
    public string $description { get => 'Accepts a user-provided text prompt and forwards it to downstream nodes.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [],
            outputs: [
                PortDefinition::output('prompt', 'Prompt', DataType::Prompt),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:1'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'prompt' => '',
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $prompt = $ctx->config['prompt'] ?? '';

        return [
            'prompt' => PortPayload::success(
                value: $prompt,
                schemaType: DataType::Prompt,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'prompt',
                previewText: mb_substr($prompt, 0, 120),
            ),
        ];
    }
}
