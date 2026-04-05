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

class FinalExportTemplate extends NodeTemplate
{
    public string $type { get => 'finalExport'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Final Export'; }
    public NodeCategory $category { get => NodeCategory::Output; }
    public string $description { get => 'Returns export metadata for the final video asset.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('video', 'Video', DataType::VideoAsset),
            ],
            outputs: [
                PortDefinition::output('exported', 'Exported', DataType::Json),
            ],
        );
    }

    public function configRules(): array
    {
        return [];
    }

    public function defaultConfig(): array
    {
        return [];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $video = $ctx->inputValue('video');

        $exportMetadata = [
            'exportedAt' => now()->toIso8601String(),
            'format' => 'mp4',
            'durationSeconds' => is_array($video) ? ($video['totalDurationSeconds'] ?? 0) : 0,
            'resolution' => is_array($video) ? ($video['resolution'] ?? '1920x1080') : '1920x1080',
            'status' => 'ready',
        ];

        return [
            'exported' => PortPayload::success(
                value: $exportMetadata,
                schemaType: DataType::Json,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'exported',
                previewText: 'Export ready · ' . ($exportMetadata['durationSeconds']) . 's',
            ),
        ];
    }
}
