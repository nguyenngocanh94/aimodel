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
use Illuminate\Support\Facades\Http;

class TelegramDeliverTemplate extends NodeTemplate
{
    public string $type { get => 'telegramDeliver'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Telegram Deliver'; }
    public NodeCategory $category { get => NodeCategory::Output; }
    public string $description { get => 'Sends workflow results to Telegram chat.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('content', 'Content', DataType::Json, required: true),
                PortDefinition::input('chatId', 'Chat ID', DataType::Text, required: false),
            ],
            outputs: [
                PortDefinition::output('deliveryResult', 'Delivery Result', DataType::Json),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'botToken' => ['required', 'string'],
            'defaultChatId' => ['required', 'string'],
            'messageFormat' => ['required', 'string', 'in:text,markdown,html,structured'],
            'includeTimestamp' => ['required', 'boolean'],
            'notifyOnSuccess' => ['required', 'boolean'],
            'maxMessageLength' => ['required', 'integer', 'min:100', 'max:4096'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'botToken' => '',
            'defaultChatId' => '',
            'messageFormat' => 'structured',
            'includeTimestamp' => true,
            'notifyOnSuccess' => true,
            'maxMessageLength' => 4096,
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $content = $ctx->inputValue('content');
        $chatIdInput = $ctx->inputValue('chatId');
        $chatId = !empty($chatIdInput) ? (string) $chatIdInput : ($ctx->config['defaultChatId'] ?? '');
        $botToken = $ctx->config['botToken'] ?? '';
        $messageFormat = $ctx->config['messageFormat'] ?? 'structured';
        $includeTimestamp = $ctx->config['includeTimestamp'] ?? true;
        $maxMessageLength = (int) ($ctx->config['maxMessageLength'] ?? 4096);

        $formattedText = $this->formatContent($content, $messageFormat, $includeTimestamp);
        $formattedText = mb_substr($formattedText, 0, $maxMessageLength);

        $parseMode = match ($messageFormat) {
            'markdown' => 'Markdown',
            'html' => 'HTML',
            default => null,
        };

        // Determine provider — use stub for empty/test tokens
        $provider = $ctx->config['provider'] ?? null;
        $isStub = $provider === 'stub' || $botToken === '' || $botToken === 'stub';

        if ($isStub) {
            $deliveryResult = [
                'ok' => true,
                'stub' => true,
                'chatId' => $chatId,
                'messageLength' => mb_strlen($formattedText),
                'format' => $messageFormat,
                'sentAt' => now()->toIso8601String(),
            ];
        } else {
            $payload = [
                'chat_id' => $chatId,
                'text' => $formattedText,
                'disable_notification' => !($ctx->config['notifyOnSuccess'] ?? true),
            ];

            if ($parseMode !== null) {
                $payload['parse_mode'] = $parseMode;
            }

            $response = Http::post(
                "https://api.telegram.org/bot{$botToken}/sendMessage",
                $payload,
            );

            $deliveryResult = [
                'ok' => $response->successful(),
                'statusCode' => $response->status(),
                'chatId' => $chatId,
                'messageLength' => mb_strlen($formattedText),
                'format' => $messageFormat,
                'sentAt' => now()->toIso8601String(),
                'telegramResponse' => $response->json(),
            ];
        }

        return [
            'deliveryResult' => PortPayload::success(
                value: $deliveryResult,
                schemaType: DataType::Json,
                sourceNodeId: $ctx->nodeId,
                sourcePortKey: 'deliveryResult',
                previewText: ($deliveryResult['ok'] ? 'Sent' : 'Failed') . ' to chat ' . $chatId,
            ),
        ];
    }

    /**
     * Format content for the Telegram message based on the selected format.
     */
    private function formatContent(mixed $content, string $format, bool $includeTimestamp): string
    {
        $timestamp = $includeTimestamp ? "\n\n---\nSent at: " . now()->toIso8601String() : '';

        if (is_string($content)) {
            return $content . $timestamp;
        }

        if (!is_array($content)) {
            return ((string) $content) . $timestamp;
        }

        return match ($format) {
            'text' => $this->formatAsText($content) . $timestamp,
            'markdown' => $this->formatAsMarkdown($content) . $timestamp,
            'html' => $this->formatAsHtml($content) . $timestamp,
            'structured' => $this->formatAsStructured($content) . $timestamp,
            default => json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . $timestamp,
        };
    }

    private function formatAsText(array $content): string
    {
        $lines = [];
        foreach ($content as $key => $value) {
            $lines[] = $key . ': ' . (is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return implode("\n", $lines);
    }

    private function formatAsMarkdown(array $content): string
    {
        $lines = [];
        foreach ($content as $key => $value) {
            $lines[] = '*' . $key . ':* ' . (is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return implode("\n", $lines);
    }

    private function formatAsHtml(array $content): string
    {
        $lines = [];
        foreach ($content as $key => $value) {
            $lines[] = '<b>' . htmlspecialchars((string) $key) . ':</b> ' . (is_string($value) ? htmlspecialchars($value) : json_encode($value, JSON_UNESCAPED_UNICODE));
        }

        return implode("\n", $lines);
    }

    private function formatAsStructured(array $content): string
    {
        return json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
