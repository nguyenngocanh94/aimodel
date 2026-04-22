<?php

declare(strict_types=1);

namespace App\Domain\Nodes\Concerns;

use App\Domain\Nodes\HumanProposal;
use App\Domain\Nodes\HumanResponse;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\PortPayload;

/**
 * Makes any NodeTemplate opt-in to a human-in-the-loop review cycle via config.
 *
 * Lifecycle when `config.humanGate.enabled` is true:
 *   1. RunExecutor calls propose() → the node runs its own execute(), the
 *      outputs are packaged into a HumanProposal and delivered to the human
 *      via the configured channel (telegram / ui / mcp).
 *   2. The human replies. RunExecutor rebuilds the context (including the
 *      saved node_state) and calls handleResponse():
 *        - pick / edit → finalise the outputs (optionally replacing the value
 *          for `edit`) and continue the pipeline.
 *        - prompt_back → record the feedback, re-run execute() with the
 *          accumulated feedback folded into config under `_humanFeedback`
 *          (latest) and `_humanFeedbackHistory` (all rounds), then return a
 *          fresh HumanProposal so the cycle loops.
 *
 * Templates that use the trait should merge `humanGateConfigRules()` /
 * `humanGateDefaultConfig()` into their own configRules/defaultConfig.
 * Override `humanGateFormatMessage()` or `humanGatePrimaryOutputKey()` to
 * customise the message shown to the human.
 */
trait InteractsWithHuman
{
    public function needsHumanLoop(array $config = []): bool
    {
        return (bool) ($config['humanGate']['enabled'] ?? false);
    }

    public function propose(NodeExecutionContext $ctx): HumanProposal
    {
        $outputs = $this->execute($ctx);

        return $this->buildProposal(
            ctx: $ctx,
            outputs: $outputs,
            attempt: 1,
            feedbackHistory: [],
        );
    }

    public function handleResponse(NodeExecutionContext $ctx, HumanResponse $response): array|HumanProposal
    {
        $state = $ctx->humanProposalState;
        $storedOutputs = $this->deserializeOutputs($state['outputs'] ?? []);

        if ($response->isPromptBack()) {
            $history = $state['feedbackHistory'] ?? [];
            $history[] = $response->feedback ?? '';

            $nextCtx = $ctx->withConfig(array_merge($ctx->config, [
                '_humanFeedback' => $response->feedback ?? '',
                '_humanFeedbackHistory' => $history,
            ]));

            $nextOutputs = $this->execute($nextCtx);

            return $this->buildProposal(
                ctx: $nextCtx,
                outputs: $nextOutputs,
                attempt: (int) ($state['attempt'] ?? 1) + 1,
                feedbackHistory: $history,
            );
        }

        if ($response->isEdit() && $response->editedContent !== null) {
            return $this->applyEdit($storedOutputs, $response->editedContent, $ctx);
        }

        // pick (or edit without content) → approve: surface stored outputs
        return $storedOutputs !== []
            ? $storedOutputs
            : $this->execute($ctx);
    }

    // ── Overridable hooks ──────────────────────────────────────────────

    /**
     * Which output port carries the "proposable" content?
     * Used for default message formatting and `edit` overrides.
     * Defaults to the first declared output.
     */
    protected function humanGatePrimaryOutputKey(): string
    {
        $outputs = $this->ports()->outputs;
        return $outputs[0]->key ?? 'output';
    }

    /**
     * Render the message shown to the human. Receives the freshly-produced
     * outputs and the current config so subclasses can tailor per node type.
     *
     * @param array<string, PortPayload> $outputs
     */
    protected function humanGateFormatMessage(array $outputs, array $config): string
    {
        $template = (string) ($config['humanGate']['messageTemplate'] ?? '');
        $primary = $outputs[$this->humanGatePrimaryOutputKey()] ?? null;
        $value = $primary?->value;

        $body = match (true) {
            is_string($value) => $value,
            is_array($value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            default => (string) ($primary?->previewText ?? ''),
        };

        if ($template === '') {
            return $body !== '' ? $body : 'Awaiting your review';
        }

        $context = is_array($value) ? $value : ['body' => $body];

        return preg_replace_callback('/\{\{(\w+)\}\}/', function (array $m) use ($context, $body): string {
            $key = $m[1];
            if ($key === 'body') {
                return $body;
            }
            if (!array_key_exists($key, $context)) {
                return $m[0];
            }
            $v = $context[$key];
            return is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        }, $template);
    }

    // ── Config helpers for templates to merge ──────────────────────────

    /**
     * @return array<string, array<int, string>>
     */
    protected function humanGateConfigRules(): array
    {
        return [
            'humanGate' => ['sometimes', 'array'],
            'humanGate.enabled' => ['sometimes', 'boolean'],
            'humanGate.channel' => ['sometimes', 'string', 'in:ui,telegram,mcp,any'],
            'humanGate.messageTemplate' => ['sometimes', 'string'],
            'humanGate.options' => ['sometimes', 'nullable', 'array'],
            'humanGate.botToken' => ['sometimes', 'string'],
            'humanGate.chatId' => ['sometimes', 'string'],
            'humanGate.timeoutSeconds' => ['sometimes', 'integer', 'min:0', 'max:86400'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function humanGateDefaultConfig(): array
    {
        return [
            'humanGate' => [
                'enabled' => false,
                'channel' => 'telegram',
                'messageTemplate' => '',
                'options' => ['Approve', 'Revise'],
                'botToken' => '',
                'chatId' => '',
                'timeoutSeconds' => 0,
            ],
        ];
    }

    // ── Internals ──────────────────────────────────────────────────────

    /**
     * @param array<string, PortPayload> $outputs
     * @param list<string> $feedbackHistory
     */
    private function buildProposal(
        NodeExecutionContext $ctx,
        array $outputs,
        int $attempt,
        array $feedbackHistory,
    ): HumanProposal {
        $config = $ctx->config;
        $gate = $config['humanGate'] ?? [];

        $message = $this->humanGateFormatMessage($outputs, $config);

        return new HumanProposal(
            message: $message,
            channel: (string) ($gate['channel'] ?? 'telegram'),
            payload: [
                'outputs' => $this->serializeOutputs($outputs),
                'options' => $gate['options'] ?? null,
                'attempt' => $attempt,
            ],
            state: [
                'attempt' => $attempt,
                'outputs' => $this->serializeOutputs($outputs),
                'feedbackHistory' => $feedbackHistory,
            ],
        );
    }

    /**
     * @param array<string, PortPayload> $outputs
     * @return array<string, array<string, mixed>>
     */
    private function serializeOutputs(array $outputs): array
    {
        $result = [];
        foreach ($outputs as $key => $payload) {
            $result[$key] = is_array($payload) ? $payload : $payload->toArray();
        }
        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $serialized
     * @return array<string, PortPayload>
     */
    private function deserializeOutputs(array $serialized): array
    {
        $result = [];
        foreach ($serialized as $key => $arr) {
            $result[$key] = PortPayload::fromArray($arr);
        }
        return $result;
    }

    /**
     * @param array<string, PortPayload> $stored
     * @return array<string, PortPayload>
     */
    private function applyEdit(array $stored, string $editedContent, NodeExecutionContext $ctx): array
    {
        $primaryKey = $this->humanGatePrimaryOutputKey();
        $existing = $stored[$primaryKey] ?? null;

        $decoded = json_decode($editedContent, true);
        $newValue = json_last_error() === JSON_ERROR_NONE ? $decoded : $editedContent;

        $stored[$primaryKey] = PortPayload::success(
            value: $newValue,
            schemaType: $existing?->schemaType ?? \App\Domain\DataType::Json,
            sourceNodeId: $ctx->nodeId,
            sourcePortKey: $primaryKey,
            previewText: mb_substr(is_string($newValue) ? $newValue : json_encode($newValue, JSON_UNESCAPED_UNICODE), 0, 120),
        );

        return $stored;
    }

}
