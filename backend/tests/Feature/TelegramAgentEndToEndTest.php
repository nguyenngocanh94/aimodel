<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end smoke test for the Telegram Agent Bridge.
 *
 * Exercises the full path:
 *   inbound webhook → TelegramWebhookController → TelegramAgent →
 *   Anthropic tool-use loop (faked) → RunWorkflowTool → ExecutionRun →
 *   RunWorkflowJob → Telegram sendMessage reply
 *
 * Second act: /status slash command → SlashCommandRouter → sendMessage
 * containing the runId from the first act (no Anthropic call needed).
 *
 * Budget: must complete under 2 seconds with no real network calls.
 */
final class TelegramAgentEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'FAKETOKEN';
    private const CHAT_ID   = '123';

    protected function setUp(): void
    {
        parent::setUp();
        // Pin the provider to Anthropic for this test so the fake Http sequence matches.
        // A parallel FireworksToolUseClientTest covers the Fireworks translation layer.
        config(['services.telegram_agent.provider' => 'anthropic']);
    }

    // -------------------------------------------------------------------------
    // Helpers — Anthropic fake response bodies
    // -------------------------------------------------------------------------

    private function anthropicToolUse(string $id, string $name, array $input): array
    {
        return [
            'stop_reason' => 'tool_use',
            'model'       => 'claude-sonnet-4-6',
            'content'     => [
                [
                    'type'  => 'tool_use',
                    'id'    => $id,
                    'name'  => $name,
                    'input' => $input,
                ],
            ],
        ];
    }

    private function anthropicEndTurn(string $text = 'OK'): array
    {
        return [
            'stop_reason' => 'end_turn',
            'model'       => 'claude-sonnet-4-6',
            'content'     => [['type' => 'text', 'text' => $text]],
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers — seed a triggerable workflow
    // -------------------------------------------------------------------------

    /**
     * Seed the story-writer-gated workflow (mirrors HumanGateDemoSeeder).
     * We create it inline so the test owns its own data and stays hermetic.
     */
    private function seedStoryWriterWorkflow(): Workflow
    {
        return Workflow::create([
            'name'           => 'StoryWriter (per-node gate) – Telegram',
            'slug'           => 'story-writer-gated',
            'triggerable'    => true,
            'nl_description' => 'Viết kịch bản video TVC ngắn tiếng Việt (GenZ). Dùng khi người dùng yêu cầu tạo kịch bản / ý tưởng video / story cho một sản phẩm.',
            'param_schema'   => ['productBrief' => ['required', 'string', 'min:5']],
            'document'       => [
                'nodes' => [
                    [
                        'id'     => 'story-writer',
                        'type'   => 'storyWriter',
                        'config' => [
                            'provider' => 'stub',
                            'apiKey'   => '',
                            'model'    => 'gpt-4o',
                        ],
                        'position' => ['x' => 200, 'y' => 200],
                    ],
                ],
                'edges' => [],
            ],
        ]);
    }

    // =========================================================================
    // The one end-to-end test
    // =========================================================================

    #[Test]
    public function test_telegram_agent_end_to_end_flow(): void
    {
        // ── 1. Seed catalog ────────────────────────────────────────────────────
        $workflow = $this->seedStoryWriterWorkflow();

        // ── 2. Arrange HTTP fakes (Queue must be faked BEFORE the POST) ────────
        Queue::fake();

        Http::fake([
            // Anthropic: return tool-use responses in sequence for the first webhook POST.
            // Round 1: list_workflows (agent discovers catalog)
            // Round 2: run_workflow (agent triggers the seeded workflow with params)
            // Round 3: end_turn with a Vietnamese confirmation
            'https://api.anthropic.com/*' => Http::sequence()
                ->push($this->anthropicToolUse('toolu_1', 'list_workflows', []), 200)
                ->push($this->anthropicToolUse('toolu_2', 'run_workflow', [
                    'slug'   => 'story-writer-gated',
                    'params' => ['productBrief' => 'bánh chocopie'],
                ]), 200)
                ->push($this->anthropicEndTurn('✅ Đã tạo kịch bản cho bánh chocopie! Kiểm tra /status để theo dõi tiến trình.'), 200),

            // Telegram: accept any sendMessage call (we inspect bodies via Http::recorded).
            'https://api.telegram.org/*' => Http::response(
                ['ok' => true, 'result' => ['message_id' => 999]],
                200,
            ),
        ]);

        // ── 3. Act 1 — NL intent (free-text Vietnamese message) ───────────────
        $act1Response = $this->postJson(
            '/api/telegram/webhook/' . self::BOT_TOKEN,
            [
                'update_id' => 1,
                'message'   => [
                    'chat'       => ['id' => self::CHAT_ID],
                    'text'       => 'viết kịch bản cho bánh chocopie',
                    'message_id' => 100,
                ],
            ],
        );

        $act1Response->assertOk();

        // ── 4. Assert 1 ────────────────────────────────────────────────────────

        // Exactly one ExecutionRun created for the seeded workflow.
        $this->assertSame(1, ExecutionRun::count(), 'Expected exactly one ExecutionRun to be created');

        $run = ExecutionRun::first();
        $this->assertSame((string) $workflow->id, (string) $run->workflow_id, 'ExecutionRun must belong to the story-writer-gated workflow');

        // The _agentParams must be injected into the first node's config.
        // RunWorkflowTool uses flat node['config'] when there is no node['data']['config'].
        $nodes = $run->document_snapshot['nodes'] ?? [];
        $this->assertNotEmpty($nodes, 'document_snapshot must contain nodes');
        $firstNodeConfig = $nodes[0]['config'] ?? [];
        $this->assertArrayHasKey('_agentParams', $firstNodeConfig, 'First node config must contain _agentParams');
        $this->assertSame(
            'bánh chocopie',
            $firstNodeConfig['_agentParams']['productBrief'] ?? null,
            'productBrief param must be "bánh chocopie"',
        );

        // RunWorkflowJob must have been dispatched exactly once.
        Queue::assertPushed(RunWorkflowJob::class, 1);

        // At least one Anthropic POST must have contained the catalog slug in its body.
        Http::assertSent(function ($request): bool {
            return str_contains((string) $request->url(), 'api.anthropic.com')
                && str_contains((string) $request->body(), 'story-writer-gated');
        });

        // At least one Telegram sendMessage must have been fired.
        Http::assertSent(function ($request): bool {
            return str_contains((string) $request->url(), 'api.telegram.org')
                && str_contains((string) $request->url(), 'sendMessage');
        });

        // Capture the number of Telegram sendMessage calls made so far.
        $telegramSendsBefore = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains((string) $pair[0]->url(), 'sendMessage'))
            ->count();

        $this->assertGreaterThanOrEqual(1, $telegramSendsBefore, 'At least one Telegram sendMessage must be sent during Act 1');

        // ── 5. Act 2 — /status slash command ──────────────────────────────────
        // The /status bare command goes through TelegramAgent → SlashCommandRouter
        // which queries DB and returns the 5 most recent telegramWebhook runs.
        // No Anthropic call is made for slash commands.
        $act2Response = $this->postJson(
            '/api/telegram/webhook/' . self::BOT_TOKEN,
            [
                'update_id' => 2,
                'message'   => [
                    'chat'       => ['id' => self::CHAT_ID],
                    'text'       => '/status',
                    'message_id' => 101,
                ],
            ],
        );

        $act2Response->assertOk();

        // ── 6. Assert 2 ────────────────────────────────────────────────────────

        // The total Telegram sendMessage count must have increased by at least 1.
        $allTelegramSends = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains((string) $pair[0]->url(), 'sendMessage'))
            ->values();

        $this->assertGreaterThan(
            $telegramSendsBefore,
            $allTelegramSends->count(),
            'At least one more Telegram sendMessage must be sent during Act 2 (/status)',
        );

        // The last Telegram sendMessage body must mention the runId from Act 1.
        $lastSend = $allTelegramSends->last();
        $this->assertNotNull($lastSend, 'No Telegram sendMessage recorded');

        $lastBody = json_decode((string) $lastSend[0]->body(), true);
        $lastText = $lastBody['text'] ?? '';

        $runId = (string) $run->id;
        $this->assertStringContainsString(
            $runId,
            $lastText,
            "The /status reply must contain the runId ({$runId}) from Act 1",
        );
    }
}
