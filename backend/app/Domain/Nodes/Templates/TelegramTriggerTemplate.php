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

class TelegramTriggerTemplate extends NodeTemplate
{
    public string $type { get => 'telegramTrigger'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Telegram Trigger'; }
    public NodeCategory $category { get => NodeCategory::Input; }
    public string $description { get => 'Starts workflow from Telegram message. Extracts text and images.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [],
            outputs: [
                PortDefinition::output('message', 'Message', DataType::Json),
                PortDefinition::output('text', 'Text', DataType::Text),
                PortDefinition::output('images', 'Images', DataType::ImageAssetList),
                PortDefinition::output('triggerInfo', 'Trigger Info', DataType::Json),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'botToken' => ['required', 'string'],
            'allowedChatIds' => ['sometimes', 'array'],
            'extractImages' => ['required', 'boolean'],
            'maxImages' => ['required', 'integer', 'min:1', 'max:10'],
            'filterKeywords' => ['sometimes', 'array'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'botToken' => '',
            'allowedChatIds' => [],
            'extractImages' => true,
            'maxImages' => 5,
            'filterKeywords' => [],
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $triggerPayload = $ctx->config['_triggerPayload'] ?? null;

        // No trigger payload — return idle outputs (preview mode)
        if ($triggerPayload === null) {
            return [
                'message' => PortPayload::idle(DataType::Json),
                'text' => PortPayload::idle(DataType::Text),
                'images' => PortPayload::idle(DataType::ImageAssetList),
                'triggerInfo' => PortPayload::idle(DataType::Json),
            ];
        }

        $message = $triggerPayload['message'] ?? $triggerPayload;
        $text = $message['text'] ?? $message['caption'] ?? '';
        $images = $this->extractPhotos($message, $ctx->config);

        $triggerInfo = [
            'chatId' => (string) ($message['chat']['id'] ?? ''),
            'messageId' => $message['message_id'] ?? null,
            'fromId' => $message['from']['id'] ?? null,
            'fromUsername' => $message['from']['username'] ?? null,
            'date' => $message['date'] ?? null,
            'receivedAt' => now()->toIso8601String(),
        ];

        return [
            'message' => PortPayload::success(
                value: $message,
                schemaType: DataType::Json,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'message',
                previewText: mb_substr(json_encode($message), 0, 120),
            ),
            'text' => PortPayload::success(
                value: $text,
                schemaType: DataType::Text,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'text',
                previewText: mb_substr($text, 0, 120),
            ),
            'images' => PortPayload::success(
                value: $images,
                schemaType: DataType::ImageAssetList,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'images',
                previewText: count($images) . ' image(s) extracted',
            ),
            'triggerInfo' => PortPayload::success(
                value: $triggerInfo,
                schemaType: DataType::Json,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'triggerInfo',
                previewText: 'Chat ' . $triggerInfo['chatId'] . ' · msg #' . ($triggerInfo['messageId'] ?? '?'),
            ),
        ];
    }

    /**
     * Extract photo file IDs from the Telegram message payload.
     *
     * @return array<int, array{fileId: string, width: int, height: int}>
     */
    private function extractPhotos(array $message, array $config): array
    {
        if (!($config['extractImages'] ?? true)) {
            return [];
        }

        $photos = $message['photo'] ?? [];

        if (empty($photos)) {
            return [];
        }

        $maxImages = (int) ($config['maxImages'] ?? 5);

        // Telegram sends multiple sizes per photo; take the largest of each group
        // For a single message, all photo entries are sizes of one image.
        // We take up to maxImages of the largest-resolution variants.
        $extracted = [];

        // Group by unique file — take largest by file_size or width
        // Telegram sends sizes sorted smallest to largest, so take the last.
        $largest = end($photos);

        if ($largest && isset($largest['file_id'])) {
            $extracted[] = [
                'fileId' => $largest['file_id'],
                'width' => $largest['width'] ?? 0,
                'height' => $largest['height'] ?? 0,
            ];
        }

        return array_slice($extracted, 0, $maxImages);
    }
}
