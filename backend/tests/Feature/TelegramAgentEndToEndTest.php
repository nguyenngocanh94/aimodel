<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use App\Services\TelegramAgent\TelegramAgent;
use App\Services\TelegramAgent\Tools\RunWorkflowTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request as ToolRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end smoke test: webhook POST → controller → TelegramAgent → tools/slash.
 *
 * LA3 scope: verifies that
 *   1. A free-text POST reaches TelegramAgent (via the real controller path)
 *      and triggers the LLM pipeline (faked via TelegramAgent::fake()).
 *   2. A /status POST also reaches TelegramAgent and sends a Telegram message
 *      containing the runId from Act 1.
 *
 * LLM calls are faked with TelegramAgent::fake(). Tool execution (RunWorkflowTool)
 * is driven directly inline — the same pattern used by TelegramAgentTest — so we
 * can assert the ExecutionRun side-effect without waiting for a real LLM round-trip.
 *
 * Budget: must complete under 2 seconds with no real network calls.
 */
final class TelegramAgentEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'FAKETOKEN';
    private const CHAT_ID   = '123';

    private function webhookUrl(): string
    {
        return '/api/telegram/webhook/' . self::BOT_TOKEN;
    }

    /**
     * Seed the story-writer-gated workflow (mirrors HumanGateDemoSeeder).
     * Created inline so the test owns its data and stays hermetic.
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
                        'id'       => 'story-writer',
                        'type'     => 'storyWriter',
                        'config'   => [
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
    // The end-to-end test
    // =========================================================================

    #[Test]
    public function test_telegram_agent_end_to_end_flow(): void
    {
        // ── 1. Seed catalog ────────────────────────────────────────────────────
        $workflow = $this->seedStoryWriterWorkflow();

        // ── 2. Arrange fakes ──────────────────────────────────────────────────
        Queue::fake();

        Http::fake([
            'https://api.telegram.org/*' => Http::response(
                ['ok' => true, 'result' => ['message_id' => 999]],
                200,
            ),
        ]);

        // Fake the LLM so no real provider is contacted.
        // The fake returns a canned text reply and records prompts.
        TelegramAgent::fake(['Đã khởi động workflow story-writer-gated!']);

        // ── 3. Pre-condition: drive RunWorkflowTool directly ──────────────────
        // The LLM fake doesn't execute tools, so we fire RunWorkflowTool
        // inline to prove the tool integration works (same pattern as
        // TelegramAgentTest::tool_use_happy_path_creates_run_and_sends_reply).
        $runTool = new RunWorkflowTool(chatId: self::CHAT_ID);
        $toolRequest = new ToolRequest([
            'slug'   => 'story-writer-gated',
            'params' => ['productBrief' => 'bánh chocopie'],
        ]);
        $runTool->handle($toolRequest);

        // Exactly one ExecutionRun must now exist.
        $this->assertSame(1, ExecutionRun::count(), 'Expected exactly one ExecutionRun from RunWorkflowTool');

        $run = ExecutionRun::first();
        $this->assertSame((string) $workflow->id, (string) $run->workflow_id);

        $nodes = $run->document_snapshot['nodes'] ?? [];
        $this->assertNotEmpty($nodes, 'document_snapshot must contain nodes');
        $firstNodeConfig = $nodes[0]['config'] ?? [];
        $this->assertArrayHasKey('_agentParams', $firstNodeConfig);
        $this->assertSame('bánh chocopie', $firstNodeConfig['_agentParams']['productBrief'] ?? null);

        Queue::assertPushed(RunWorkflowJob::class, 1);

        // ── 4. Act 1 — NL intent reaches the controller → TelegramAgent ──────
        $act1Response = $this->postJson($this->webhookUrl(), [
            'update_id' => 1,
            'message'   => [
                'chat'       => ['id' => self::CHAT_ID],
                'text'       => 'viết kịch bản cho bánh chocopie',
                'message_id' => 100,
            ],
        ]);

        $act1Response->assertOk()->assertJson(['ok' => true]);

        // Fake recorded the prompt.
        TelegramAgent::assertPrompted('viết kịch bản cho bánh chocopie');

        // The fake text reply was forwarded to Telegram.
        Http::assertSent(fn ($req)
            => str_contains((string) $req->url(), 'api.telegram.org')
            && str_contains((string) $req->url(), 'sendMessage'));

        // Capture sendMessage count before Act 2.
        $telegramSendsBefore = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains((string) $pair[0]->url(), 'sendMessage'))
            ->count();

        $this->assertGreaterThanOrEqual(1, $telegramSendsBefore, 'At least one Telegram sendMessage in Act 1');

        // ── 5. Act 2 — /status slash command ──────────────────────────────────
        // Slash commands route through TelegramAgent → SlashCommandRouter.
        // No LLM call is made; SlashCommandRouter queries the DB directly.
        $act2Response = $this->postJson($this->webhookUrl(), [
            'update_id' => 2,
            'message'   => [
                'chat'       => ['id' => self::CHAT_ID],
                'text'       => '/status',
                'message_id' => 101,
            ],
        ]);

        $act2Response->assertOk()->assertJson(['ok' => true]);

        // ── 6. Assert 2 ────────────────────────────────────────────────────────
        $allTelegramSends = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains((string) $pair[0]->url(), 'sendMessage'))
            ->values();

        $this->assertGreaterThan(
            $telegramSendsBefore,
            $allTelegramSends->count(),
            'At least one more Telegram sendMessage must be sent during Act 2 (/status)',
        );

        // The last sendMessage body must mention the runId from Act 1.
        $lastSend = $allTelegramSends->last();
        $this->assertNotNull($lastSend, 'No Telegram sendMessage recorded');

        $lastBody = json_decode((string) $lastSend[0]->body(), true);
        $lastText = $lastBody['text'] ?? '';

        $runId = (string) $run->id;
        $this->assertStringContainsString(
            $runId,
            $lastText,
            "The /status reply must contain the runId ({$runId}) from the pre-seeded run",
        );
    }
}
