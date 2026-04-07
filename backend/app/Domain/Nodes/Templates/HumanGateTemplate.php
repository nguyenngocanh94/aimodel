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

class HumanGateTemplate extends NodeTemplate
{
    public string $type { get => 'humanGate'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Human Gate'; }
    public NodeCategory $category { get => NodeCategory::Utility; }
    public string $description { get => 'Pauses workflow execution, sends data to an external channel, and waits for a human or AI response before resuming.'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('data', 'Data', DataType::Json),
            ],
            outputs: [
                PortDefinition::output('response', 'Response', DataType::Json),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'messageTemplate' => ['sometimes', 'string'],
            'channel' => ['sometimes', 'string', 'in:ui,telegram,mcp,any'],
            'timeoutSeconds' => ['sometimes', 'integer', 'min:0', 'max:86400'],
            'autoFallbackResponse' => ['sometimes', 'nullable', 'string'],
            'options' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'messageTemplate' => '',
            'channel' => 'ui',
            'timeoutSeconds' => 0,
            'autoFallbackResponse' => null,
            'options' => null,
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        // Check if a response has already been provided via review data
        $response = $ctx->config['_gateResponse'] ?? null;

        if ($response !== null) {
            return [
                'response' => PortPayload::success(
                    value: $response,
                    schemaType: DataType::Json,
                    sourceNodeId: $ctx->nodeId,
                    sourcePortKey: 'response',
                    previewText: is_string($response)
                        ? mb_substr($response, 0, 120)
                        : mb_substr(json_encode($response), 0, 120),
                ),
            ];
        }

        // Build gate data from input and config for the pending exception
        $inputPayload = $ctx->input('data');
        $inputValue = $inputPayload?->value;

        // Render the message template with input data placeholders
        $message = $this->renderMessageTemplate(
            $ctx->config['messageTemplate'] ?? '',
            is_array($inputValue) ? $inputValue : [],
        );

        throw new ReviewPendingException(
            nodeId: $ctx->nodeId,
            message: $message ?: 'Execution paused: awaiting human gate response',
        );
    }

    /**
     * Render a message template by replacing {{variable}} placeholders with input data values.
     */
    public function renderMessageTemplate(string $template, array $data): string
    {
        if ($template === '') {
            return '';
        }

        return preg_replace_callback('/\{\{(\w+)\}\}/', function (array $matches) use ($data): string {
            $key = $matches[1];

            if (!array_key_exists($key, $data)) {
                return $matches[0]; // leave placeholder as-is if key not found
            }

            $value = $data[$key];

            return is_string($value) ? $value : json_encode($value);
        }, $template);
    }
}
