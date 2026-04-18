<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\PendingInteraction;
use App\Models\Workflow;
use App\Services\TelegramAgent\AgentContext;
use App\Services\TelegramAgent\Tools\GetRunStatusTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GetRunStatusToolTest extends TestCase
{
    use RefreshDatabase;

    private AgentContext $ctx;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = new AgentContext(
            chatId: '123456',
            userId: 'user-1',
            sessionId: 'session-abc',
            botToken: 'bot-token-xyz',
        );
    }

    private function makeRun(array $overrides = []): ExecutionRun
    {
        $workflow = Workflow::create([
            'name' => 'Test Workflow',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        return ExecutionRun::create(array_merge([
            'workflow_id' => $workflow->id,
            'trigger' => 'telegramWebhook',
            'status' => 'running',
            'document_snapshot' => ['nodes' => [], 'edges' => []],
            'document_hash' => 'abc123',
            'node_config_hashes' => [],
        ], $overrides));
    }

    #[Test]
    public function definition_returns_correct_tool_definition(): void
    {
        $tool = new GetRunStatusTool();
        $def = $tool->definition();

        $this->assertSame('get_run_status', $def->name);
    }

    #[Test]
    public function happy_path_returns_run_status_and_metadata(): void
    {
        $run = $this->makeRun(['status' => 'running']);

        $tool = new GetRunStatusTool();
        $result = $tool->execute(['runId' => $run->id], $this->ctx);

        $this->assertSame($run->id, $result['runId']);
        $this->assertSame('running', $result['status']);
        $this->assertNull($result['terminationReason']);
        $this->assertNull($result['pending']);
    }

    #[Test]
    public function returns_current_node_id_from_running_node_record(): void
    {
        $run = $this->makeRun(['status' => 'running']);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'node-abc',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $tool = new GetRunStatusTool();
        $result = $tool->execute(['runId' => $run->id], $this->ctx);

        $this->assertSame('node-abc', $result['currentNodeId']);
    }

    #[Test]
    public function returns_pending_interaction_summary_when_present(): void
    {
        $run = $this->makeRun(['status' => 'awaitingReview']);

        PendingInteraction::create([
            'run_id' => $run->id,
            'node_id' => 'node-gate',
            'channel' => 'telegram',
            'chat_id' => '123456',
            'status' => 'waiting',
            'proposal_payload' => ['question' => 'Approve this?'],
        ]);

        $tool = new GetRunStatusTool();
        $result = $tool->execute(['runId' => $run->id], $this->ctx);

        $this->assertNotNull($result['pending']);
        $this->assertSame('node-gate', $result['pending']['nodeId']);
        $this->assertSame('telegram', $result['pending']['channel']);
        $this->assertSame('waiting', $result['pending']['status']);
    }

    #[Test]
    public function returns_run_not_found_for_unknown_id(): void
    {
        $tool = new GetRunStatusTool();
        $result = $tool->execute(['runId' => 'non-existent-id'], $this->ctx);

        $this->assertSame('run_not_found', $result['error']);
    }

    #[Test]
    public function returns_termination_reason_for_finished_run(): void
    {
        $run = $this->makeRun([
            'status' => 'cancelled',
            'termination_reason' => 'userCancelled',
            'completed_at' => now(),
        ]);

        $tool = new GetRunStatusTool();
        $result = $tool->execute(['runId' => $run->id], $this->ctx);

        $this->assertSame('cancelled', $result['status']);
        $this->assertSame('userCancelled', $result['terminationReason']);
        $this->assertNotNull($result['completedAt']);
    }
}
