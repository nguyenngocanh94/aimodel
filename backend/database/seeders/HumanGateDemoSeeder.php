<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Workflow;
use Illuminate\Database\Seeder;

class HumanGateDemoSeeder extends Seeder
{
    /**
     * Seed two demo workflows that exercise the HumanGate node:
     *
     *   1. HumanGate Demo – UI
     *      storyWriter → humanGate (channel=ui)
     *
     *   2. HumanGate Demo – Telegram
     *      storyWriter → humanGate (channel=telegram, botToken+chatId from env)
     *
     * Env vars (optional, used only by the Telegram variant):
     *   TELEGRAM_BOT_TOKEN — bot token the workflow should use to send/receive.
     *   TELEGRAM_CHAT_ID   — chat id to deliver the gate proposal to.
     */
    public function run(): void
    {
        Workflow::updateOrCreate(
            ['name' => 'HumanGate Demo – UI'],
            [
                'description'    => 'storyWriter → humanGate on the UI channel. Start a run, the gate pauses awaiting a pick/prompt-back from the canvas.',
                'schema_version' => 1,
                'tags'           => ['demo', 'humanGate', 'ui'],
                'document'       => self::buildDocument(channel: 'ui'),
                // Catalog metadata — internal demo, not agent-triggerable
                'triggerable'    => false,
                'slug'           => null,
                'nl_description' => null,
                'param_schema'   => null,
            ],
        );

        Workflow::updateOrCreate(
            ['name' => 'HumanGate Demo – Telegram'],
            [
                'description'    => 'storyWriter → humanGate on the Telegram channel. The gate sends the story to TELEGRAM_CHAT_ID; reply in Telegram to resume.',
                'schema_version' => 1,
                'tags'           => ['demo', 'humanGate', 'telegram'],
                'document'       => self::buildDocument(
                    channel: 'telegram',
                    botToken: (string) env('TELEGRAM_BOT_TOKEN', ''),
                    chatId: (string) env('TELEGRAM_CHAT_ID', ''),
                ),
                // Catalog metadata — internal demo, not agent-triggerable
                'triggerable'    => false,
                'slug'           => null,
                'nl_description' => null,
                'param_schema'   => null,
            ],
        );

        Workflow::updateOrCreate(
            ['name' => 'StoryWriter (per-node gate) – Telegram'],
            [
                'description'    => 'Single storyWriter node with the InteractsWithHuman plugin turned on. No separate humanGate node — the storyWriter itself proposes its draft, accepts prompt-back feedback, and re-drafts until approved.',
                'schema_version' => 1,
                'tags'           => ['demo', 'humanGate', 'plugin', 'telegram'],
                'document'       => self::buildInlineGateDocument(
                    botToken: (string) env('TELEGRAM_BOT_TOKEN', ''),
                    chatId: (string) env('TELEGRAM_CHAT_ID', ''),
                ),
                // Catalog metadata — agent-triggerable
                'slug'           => 'story-writer-gated',
                'triggerable'    => true,
                'nl_description' => 'Viết kịch bản video TVC ngắn tiếng Việt (GenZ). Dùng khi người dùng yêu cầu tạo kịch bản / ý tưởng video / story cho một sản phẩm.',
                'param_schema'   => ['productBrief' => ['required', 'string', 'min:5']],
            ],
        );

        $this->command->info('HumanGate demo workflows seeded (UI + Telegram + per-node gate).');
    }

    private static function buildInlineGateDocument(string $botToken, string $chatId): array
    {
        return [
            'nodes' => [
                [
                    'id' => 'story-writer',
                    'type' => 'storyWriter',
                    'config' => [
                        'provider' => 'stub',
                        'apiKey' => '',
                        'model' => 'gpt-4o',
                        'targetDurationSeconds' => 30,
                        'storyFormula' => 'problem_agitation_solution',
                        'emotionalTone' => 'relatable_humor',
                        'productIntegrationStyle' => 'natural_use',
                        'genZAuthenticity' => 'high',
                        'vietnameseDialect' => 'neutral',
                        'humanGate' => [
                            'enabled' => true,
                            'channel' => 'telegram',
                            'messageTemplate' => '',
                            'options' => ['Approve', 'Revise'],
                            'botToken' => $botToken,
                            'chatId' => $chatId,
                            'timeoutSeconds' => 0,
                        ],
                    ],
                    'position' => ['x' => 200, 'y' => 200],
                ],
            ],
            'edges' => [],
        ];
    }

    private static function buildDocument(string $channel, string $botToken = '', string $chatId = ''): array
    {
        return [
            'nodes' => [
                [
                    'id' => 'story-writer',
                    'type' => 'storyWriter',
                    'config' => [
                        'provider' => 'stub',
                        'apiKey' => '',
                        'model' => 'gpt-4o',
                        'targetDurationSeconds' => 30,
                        'storyFormula' => 'problem_agitation_solution',
                        'emotionalTone' => 'relatable_humor',
                        'productIntegrationStyle' => 'natural_use',
                        'genZAuthenticity' => 'high',
                        'vietnameseDialect' => 'neutral',
                    ],
                    'position' => ['x' => 0, 'y' => 200],
                ],
                [
                    'id' => 'human-gate',
                    'type' => 'humanGate',
                    'config' => [
                        'messageTemplate' => "Story draft ready — please review.\n\nTitle: {{title}}\nHook: {{hook}}\n\nReply with an option number (1/2/3) to pick, or type feedback to prompt-back.",
                        'channel' => $channel,
                        'timeoutSeconds' => 0,
                        'autoFallbackResponse' => null,
                        'options' => ['Approve', 'Revise tone', 'Rewrite completely'],
                        'botToken' => $botToken,
                        'chatId' => $chatId,
                    ],
                    'position' => ['x' => 400, 'y' => 200],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-story-to-gate',
                    'sourceNodeId' => 'story-writer',
                    'sourcePortKey' => 'storyArc',
                    'targetNodeId' => 'human-gate',
                    'targetPortKey' => 'data',
                ],
            ],
        ];
    }
}
