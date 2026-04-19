<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use App\Services\TelegramAgent\SlashCommandRouter;
use App\Services\TelegramAgent\TelegramAgent;
use App\Services\TelegramAgent\Tools\ComposeWorkflowTool;
use App\Services\TelegramAgent\Tools\ReplyTool;
use App\Services\TelegramAgent\Tools\RunWorkflowTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MemoryConversationStore;
use Tests\TestCase;

/**
 * Hermetic regression harness for Telegram Assistant expected boundaries (no live LLM).
 *
 * @see docs/plans/2026-04-19-telegram-assistant-skills.md (TA4)
 */
final class AssistantBehaviorTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'test-bot-token-behavior';
    private const CHAT_ID   = '424242';

    /** Twelve canonical scenario ids — keep in sync with test methods below. */
    private const BEHAVIOR_CASE_IDS = [
        'chocopie_regression',
        'status_slash',
        'list_slash',
        'tra_sua_brief',
        'weather_off_topic',
        'math_off_topic',
        'small_talk_off_topic',
        'poem_content_trap',
        'no_match_compose_stub',
        'english_brief',
        'ambiguous_lam_video',
        'two_products',
    ];

    private function telegramUrl(): string
    {
        return 'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage';
    }

    private function makeAgent(): TelegramAgent
    {
        return new TelegramAgent(
            chatId: self::CHAT_ID,
            botToken: self::BOT_TOKEN,
            slashRouter: new SlashCommandRouter(),
        );
    }

    private function seedStoryWriterGated(): Workflow
    {
        return Workflow::create([
            'name'           => 'StoryWriter (per-node gate) – Telegram',
            'document'       => ['nodes' => [], 'edges' => []],
            'slug'           => 'story-writer-gated',
            'triggerable'    => true,
            'nl_description' => 'Viết kịch bản video TVC ngắn tiếng Việt.',
            'param_schema'   => ['productBrief' => ['required', 'string', 'min:5']],
        ]);
    }

    private function updateWithText(string $text): array
    {
        return [
            'message' => [
                'chat' => ['id' => self::CHAT_ID],
                'text' => $text,
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $memory = new MemoryConversationStore();
        $this->app->instance(ConversationStore::class, $memory);
        $memory->forgetUser(self::CHAT_ID . ':' . self::BOT_TOKEN);
    }

    #[Test]
    public function test_behavior_case_01_chocopie_regression_product_brief_sanity(): void
    {
        Queue::fake();
        $workflow = $this->seedStoryWriterGated();

        $brief = <<<'TXT'
        Sản phẩm: Bánh Chocopie
        Đối tượng: giới trẻ / GenZ
        Tone: story telling, truyền cảm hứng
        TXT;

        $tool = new RunWorkflowTool(chatId: self::CHAT_ID);
        $out  = $tool->handle(new Request([
            'slug'   => 'story-writer-gated',
            'params' => ['productBrief' => $brief],
        ]));

        $this->assertStringContainsString('Chocopie', $brief);
        $this->assertMatchesRegularExpression('/GenZ|giới trẻ/i', $brief);

        $this->assertDatabaseHas('execution_runs', [
            'workflow_id' => $workflow->id,
            'trigger'     => 'telegramWebhook',
        ]);
        Queue::assertPushed(RunWorkflowJob::class, 1);

        $this->assertStringContainsString('runId', $out);
    }

    #[Test]
    public function test_behavior_case_02_status_slash_skips_llm(): void
    {
        Http::fake([$this->telegramUrl() => Http::response(['ok' => true], 200)]);

        TelegramAgent::fake()->preventStrayPrompts();

        $this->makeAgent()->handle($this->updateWithText('/status'), self::BOT_TOKEN);

        TelegramAgent::assertNeverPrompted();
        Http::assertSent(fn ($req) => str_contains($req->url(), 'sendMessage'));
    }

    #[Test]
    public function test_behavior_case_03_list_slash_mentions_catalog_slug(): void
    {
        $this->seedStoryWriterGated();

        Http::fake([$this->telegramUrl() => Http::response(['ok' => true], 200)]);

        TelegramAgent::fake()->preventStrayPrompts();

        $this->makeAgent()->handle($this->updateWithText('/list'), self::BOT_TOKEN);

        TelegramAgent::assertNeverPrompted();

        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'sendMessage')) {
                return false;
            }
            $body = json_decode((string) $req->body(), true);
            $text = (string) ($body['text'] ?? '');

            return str_contains($text, 'story-writer-gated');
        });
    }

    #[Test]
    public function test_behavior_case_04_tra_sua_brief_maps_to_run_parameters(): void
    {
        Queue::fake();
        $workflow = $this->seedStoryWriterGated();

        $tool = new RunWorkflowTool(chatId: self::CHAT_ID);
        $tool->handle(new Request([
            'slug'   => 'story-writer-gated',
            'params' => ['productBrief' => "Trà sữa TH — TVC 15s, tone tươi trẻ, nhấn mạnh thương hiệu."],
        ]));

        $this->assertDatabaseHas('execution_runs', ['workflow_id' => $workflow->id]);
        Queue::assertPushed(RunWorkflowJob::class, 1);
    }

    #[Test]
    public function test_behavior_case_05_weather_off_topic_reply_does_not_create_run(): void
    {
        Http::fake([$this->telegramUrl() => Http::response(['ok' => true], 200)]);
        $this->seedStoryWriterGated();

        $before = ExecutionRun::count();

        $reply = new ReplyTool(botToken: self::BOT_TOKEN, chatId: self::CHAT_ID);
        $reply->handle(new Request(['text' => 'Xin lỗi, mình chỉ hỗ trợ workflow.']));

        $this->assertSame($before, ExecutionRun::count());
    }

    #[Test]
    public function test_behavior_case_06_math_off_topic_reply_does_not_create_run(): void
    {
        Http::fake([$this->telegramUrl() => Http::response(['ok' => true], 200)]);
        $this->seedStoryWriterGated();

        $before = ExecutionRun::count();

        $reply = new ReplyTool(botToken: self::BOT_TOKEN, chatId: self::CHAT_ID);
        $reply->handle(new Request(['text' => 'Mình không giải toán ở đây, bạn cần chạy workflow không?']));

        $this->assertSame($before, ExecutionRun::count());
    }

    #[Test]
    public function test_behavior_case_07_small_talk_decline_does_not_create_run(): void
    {
        Http::fake([$this->telegramUrl() => Http::response(['ok' => true], 200)]);
        $this->seedStoryWriterGated();

        $before = ExecutionRun::count();

        $reply = new ReplyTool(botToken: self::BOT_TOKEN, chatId: self::CHAT_ID);
        $reply->handle(new Request(['text' => 'Mình khỏe, cảm ơn bạn. Bạn muốn chạy workflow nào?']));

        $this->assertSame($before, ExecutionRun::count());
    }

    #[Test]
    public function test_behavior_case_08_poem_trap_decline_does_not_create_run(): void
    {
        Http::fake([$this->telegramUrl() => Http::response(['ok' => true], 200)]);
        $this->seedStoryWriterGated();

        $before = ExecutionRun::count();

        $reply = new ReplyTool(botToken: self::BOT_TOKEN, chatId: self::CHAT_ID);
        $reply->handle(new Request(['text' => 'Mình không viết thơ tại đây; bạn có muốn dùng workflow trong catalog không?']));

        $this->assertSame($before, ExecutionRun::count());
    }

    #[Test]
    public function test_behavior_case_09_no_match_compose_workflow_stub(): void
    {
        $tool = new ComposeWorkflowTool();
        $raw  = $tool->handle(new Request(['brief' => 'tạo landing page cho sản phẩm X']));
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $this->assertFalse($data['available']);
        $this->assertStringContainsString('aimodel-645', (string) $data['reason']);
    }

    #[Test]
    public function test_behavior_case_10_english_brief_passes_through_run_tool(): void
    {
        Queue::fake();
        $workflow = $this->seedStoryWriterGated();

        $brief = 'create a TVC script for Coca-Cola targeting teens, upbeat tone, 30s.';

        $tool = new RunWorkflowTool(chatId: self::CHAT_ID);
        $tool->handle(new Request([
            'slug'   => 'story-writer-gated',
            'params' => ['productBrief' => $brief],
        ]));

        $this->assertStringContainsStringIgnoringCase('coca', $brief);
        $this->assertDatabaseHas('execution_runs', ['workflow_id' => $workflow->id]);
    }

    #[Test]
    public function test_behavior_case_11_ambiguous_lam_video_clarification_via_reply(): void
    {
        Http::fake([$this->telegramUrl() => Http::response(['ok' => true], 200)]);
        $this->seedStoryWriterGated();

        $before = ExecutionRun::count();

        $reply = new ReplyTool(botToken: self::BOT_TOKEN, chatId: self::CHAT_ID);
        $reply->handle(new Request(['text' => 'Bạn cho mình tên sản phẩm hoặc brief cụ thể nhé?']));

        $this->assertSame($before, ExecutionRun::count());
    }

    #[Test]
    public function test_behavior_case_12_two_products_combined_brief(): void
    {
        Queue::fake();
        $workflow = $this->seedStoryWriterGated();

        $brief = "Video quảng cáo cho Chocopie và Oreo — so sánh hai vị, tone vui, 20s.";

        $tool = new RunWorkflowTool(chatId: self::CHAT_ID);
        $tool->handle(new Request([
            'slug'   => 'story-writer-gated',
            'params' => ['productBrief' => $brief],
        ]));

        $this->assertStringContainsStringIgnoringCase('chocopie', $brief);
        $this->assertStringContainsStringIgnoringCase('oreo', $brief);
        $this->assertDatabaseHas('execution_runs', ['workflow_id' => $workflow->id]);
    }

    #[Test]
    public function test_harness_lists_twelve_behavior_cases(): void
    {
        $this->assertCount(12, self::BEHAVIOR_CASE_IDS);
    }
}
