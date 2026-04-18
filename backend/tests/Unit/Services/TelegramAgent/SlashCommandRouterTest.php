<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent;

use App\Models\ExecutionRun;
use App\Models\Workflow;
use App\Services\TelegramAgent\SlashCommandRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SlashCommandRouterTest extends TestCase
{
    use RefreshDatabase;

    private SlashCommandRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new SlashCommandRouter();
    }

    // -------------------------------------------------------------------------
    // /start
    // -------------------------------------------------------------------------

    #[Test]
    public function start_returns_reply_containing_both_triggerable_workflow_slugs(): void
    {
        $this->seedTriggerableWorkflows();

        $reply = $this->router->route('/start', 'chat-1');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('story-writer', $reply);
        $this->assertStringContainsString('tvc-pipeline', $reply);
    }

    // -------------------------------------------------------------------------
    // /list
    // -------------------------------------------------------------------------

    #[Test]
    public function list_returns_reply_containing_both_triggerable_workflow_slugs(): void
    {
        $this->seedTriggerableWorkflows();

        $reply = $this->router->route('/list', 'chat-1');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('story-writer', $reply);
        $this->assertStringContainsString('tvc-pipeline', $reply);
    }

    // -------------------------------------------------------------------------
    // /help
    // -------------------------------------------------------------------------

    #[Test]
    public function help_returns_non_empty_text_containing_status_command(): void
    {
        $reply = $this->router->route('/help', 'chat-1');

        $this->assertNotNull($reply);
        $this->assertNotEmpty($reply);
        $this->assertStringContainsString('/status', $reply);
    }

    // -------------------------------------------------------------------------
    // /status (no runs)
    // -------------------------------------------------------------------------

    #[Test]
    public function status_with_no_runs_returns_vietnamese_empty_message(): void
    {
        $reply = $this->router->route('/status', 'chat-1');

        $this->assertNotNull($reply);
        // Must mention "chưa có run nào" or equivalent Vietnamese (case-insensitive)
        $this->assertMatchesRegularExpression('/chưa có run nào/ui', $reply);
    }

    // -------------------------------------------------------------------------
    // /status <runId>
    // -------------------------------------------------------------------------

    #[Test]
    public function status_with_run_id_returns_reply_containing_run_id_and_status(): void
    {
        $run = $this->createTelegramRun(['status' => 'running']);

        $reply = $this->router->route("/status {$run->id}", 'chat-1');

        $this->assertNotNull($reply);
        $this->assertStringContainsString($run->id, $reply);
        $this->assertStringContainsString('running', $reply);
    }

    // -------------------------------------------------------------------------
    // /status bogus-id
    // -------------------------------------------------------------------------

    #[Test]
    public function status_with_bogus_id_returns_not_found_message(): void
    {
        $reply = $this->router->route('/status bogus-uuid-that-does-not-exist', 'chat-1');

        $this->assertNotNull($reply);
        $this->assertMatchesRegularExpression('/không tìm thấy/ui', $reply);
    }

    // -------------------------------------------------------------------------
    // /cancel <runId> — cancellable
    // -------------------------------------------------------------------------

    #[Test]
    public function cancel_on_running_run_updates_status_to_cancelled(): void
    {
        $run = $this->createTelegramRun(['status' => 'running']);

        $reply = $this->router->route("/cancel {$run->id}", 'chat-1');

        $this->assertNotNull($reply);
        $this->assertStringContainsString($run->id, $reply);

        $fresh = ExecutionRun::find($run->id);
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('userCancelled', $fresh->termination_reason);
    }

    // -------------------------------------------------------------------------
    // /cancel <runId> — not cancellable
    // -------------------------------------------------------------------------

    #[Test]
    public function cancel_on_success_run_refuses_and_does_not_mutate(): void
    {
        $run = $this->createTelegramRun(['status' => 'success']);

        $reply = $this->router->route("/cancel {$run->id}", 'chat-1');

        $this->assertNotNull($reply);
        // Must explain it can't cancel (e.g. mentions 'success' or 'không thể huỷ')
        $this->assertStringContainsString('success', $reply);

        $fresh = ExecutionRun::find($run->id);
        $this->assertSame('success', $fresh->status);
        $this->assertNull($fresh->termination_reason);
    }

    // -------------------------------------------------------------------------
    // /reset
    // -------------------------------------------------------------------------

    #[Test]
    public function reset_returns_placeholder_reply(): void
    {
        $reply = $this->router->route('/reset', 'chat-1');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('Session reset', $reply);
        $this->assertStringContainsString('Storage cleared by caller', $reply);
    }

    // -------------------------------------------------------------------------
    // Non-slash input → null
    // -------------------------------------------------------------------------

    #[Test]
    public function non_slash_input_returns_null(): void
    {
        $result = $this->router->route('hello', 'chat-1');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Unknown slash → Vietnamese invalid-command string (NOT null)
    // -------------------------------------------------------------------------

    #[Test]
    public function unknown_slash_command_returns_invalid_command_vietnamese_string(): void
    {
        $reply = $this->router->route('/nope', 'chat-1');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('Lệnh không hợp lệ', $reply);
        $this->assertStringContainsString('/help', $reply);
    }

    // -------------------------------------------------------------------------
    // Case-insensitive: /STATUS == /status
    // -------------------------------------------------------------------------

    #[Test]
    public function status_command_is_case_insensitive(): void
    {
        $run = $this->createTelegramRun(['status' => 'pending']);

        $replyLower = $this->router->route("/status {$run->id}", 'chat-1');
        $replyUpper = $this->router->route("/STATUS {$run->id}", 'chat-1');

        $this->assertNotNull($replyLower);
        $this->assertNotNull($replyUpper);
        // Both should contain the run id and status
        $this->assertStringContainsString($run->id, $replyLower);
        $this->assertStringContainsString($run->id, $replyUpper);
        $this->assertStringContainsString('pending', $replyLower);
        $this->assertStringContainsString('pending', $replyUpper);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seedTriggerableWorkflows(): void
    {
        Workflow::create([
            'name'           => 'Story Writer',
            'slug'           => 'story-writer',
            'triggerable'    => true,
            'nl_description' => 'Viết kịch bản video TVC ngắn tiếng Việt.',
            'document'       => ['nodes' => []],
        ]);

        Workflow::create([
            'name'           => 'TVC Pipeline',
            'slug'           => 'tvc-pipeline',
            'triggerable'    => true,
            'nl_description' => 'Pipeline đầy đủ từ prompt đến video.',
            'document'       => ['nodes' => []],
        ]);

        // A non-triggerable workflow that must NOT appear in lists.
        Workflow::create([
            'name'        => 'Internal Demo',
            'slug'        => 'internal-demo',
            'triggerable' => false,
            'document'    => ['nodes' => []],
        ]);
    }

    private function createTelegramRun(array $attributes = []): ExecutionRun
    {
        $workflow = Workflow::create([
            'name'     => 'Test Workflow',
            'document' => ['nodes' => []],
        ]);

        return ExecutionRun::create(array_merge([
            'workflow_id' => $workflow->id,
            'trigger'     => 'telegramWebhook',
            'status'      => 'running',
            'started_at'  => now(),
        ], $attributes));
    }
}
