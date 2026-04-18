<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\PendingInteraction;
use App\Models\Workflow;
use App\Services\TelegramAgent\Tools\GetRunStatusTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\StringType;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GetRunStatusToolTest extends TestCase
{
    use RefreshDatabase;

    private function makeRun(array $overrides = []): ExecutionRun
    {
        $workflow = Workflow::create([
            'name'     => 'Test Workflow',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        return ExecutionRun::create(array_merge([
            'workflow_id'       => $workflow->id,
            'trigger'           => 'telegramWebhook',
            'status'            => 'running',
            'document_snapshot' => ['nodes' => [], 'edges' => []],
            'document_hash'     => 'abc123',
            'node_config_hashes' => [],
        ], $overrides));
    }

    #[Test]
    public function description_returns_non_empty_string(): void
    {
        $tool = new GetRunStatusTool();
        $this->assertNotEmpty($tool->description());
    }

    #[Test]
    public function schema_has_run_id_string_required(): void
    {
        $schema = (new GetRunStatusTool())->schema(new JsonSchemaTypeFactory());

        $this->assertArrayHasKey('runId', $schema);
        $this->assertInstanceOf(StringType::class, $schema['runId']);
    }

    #[Test]
    public function happy_path_returns_run_status_and_metadata(): void
    {
        $run = $this->makeRun(['status' => 'running']);

        $tool   = new GetRunStatusTool();
        $result = json_decode($tool->handle(new Request(['runId' => $run->id])), true);

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
            'run_id'     => $run->id,
            'node_id'    => 'node-abc',
            'status'     => 'running',
            'started_at' => now(),
        ]);

        $result = json_decode((new GetRunStatusTool())->handle(new Request(['runId' => $run->id])), true);

        $this->assertSame('node-abc', $result['currentNodeId']);
    }

    #[Test]
    public function returns_pending_interaction_summary_when_present(): void
    {
        $run = $this->makeRun(['status' => 'awaitingReview']);

        PendingInteraction::create([
            'run_id'           => $run->id,
            'node_id'          => 'node-gate',
            'channel'          => 'telegram',
            'chat_id'          => '123456',
            'status'           => 'waiting',
            'proposal_payload' => ['question' => 'Approve this?'],
        ]);

        $result = json_decode((new GetRunStatusTool())->handle(new Request(['runId' => $run->id])), true);

        $this->assertNotNull($result['pending']);
        $this->assertSame('node-gate', $result['pending']['nodeId']);
        $this->assertSame('telegram', $result['pending']['channel']);
        $this->assertSame('waiting', $result['pending']['status']);
    }

    #[Test]
    public function returns_run_not_found_for_unknown_id(): void
    {
        $result = json_decode((new GetRunStatusTool())->handle(new Request(['runId' => 'non-existent-id'])), true);

        $this->assertSame('run_not_found', $result['error']);
    }

    #[Test]
    public function returns_termination_reason_for_finished_run(): void
    {
        $run = $this->makeRun([
            'status'             => 'cancelled',
            'termination_reason' => 'userCancelled',
            'completed_at'       => now(),
        ]);

        $result = json_decode((new GetRunStatusTool())->handle(new Request(['runId' => $run->id])), true);

        $this->assertSame('cancelled', $result['status']);
        $this->assertSame('userCancelled', $result['terminationReason']);
        $this->assertNotNull($result['completedAt']);
    }
}
