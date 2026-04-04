<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Domain\Nodes\Exceptions\ReviewPendingException;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;

class ReviewCheckpointTemplate extends NodeTemplate
{
    public string $type { get => 'reviewCheckpoint'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Review Checkpoint'; }
    public NodeCategory $category { get => NodeCategory::Utility; }
    public string $description { get => 'Pauses execution for human review before passing data through to downstream nodes.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('data', 'Data', DataType::Json),
            ],
            outputs: [
                PortDefinition::output('data', 'Data', DataType::Json),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'approved' => ['sometimes', 'boolean'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'approved' => false,
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $approved = $ctx->config['approved'] ?? false;

        if (!$approved) {
            throw new ReviewPendingException(nodeId: $ctx->nodeId);
        }

        $inputPayload = $ctx->input('data');
        $value = $inputPayload?->value;

        return [
            'data' => PortPayload::success(
                value: $value,
                schemaType: DataType::Json,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'data',
                previewText: is_string($value)
                    ? mb_substr($value, 0, 120)
                    : mb_substr(json_encode($value), 0, 120),
            ),
        ];
    }
}
