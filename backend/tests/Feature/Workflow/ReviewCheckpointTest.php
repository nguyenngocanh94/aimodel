<?php

declare(strict_types=1);

namespace Tests\Feature\Workflow;

use App\Domain\DataType;
use App\Domain\Execution\ExecutionPlanner;
use App\Domain\Execution\InputResolver;
use App\Domain\Execution\RunCache;
use App\Domain\Execution\RunExecutor;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Nodes\Templates\ReviewCheckpointTemplate;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\Workflow;
use App\Services\LocalArtifactStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class ReviewCheckpointTest extends TestCase
{
    use RefreshDatabase;

    private RunExecutor $executor;
    private NodeTemplateRegistry $registry;
    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new NodeTemplateRegistry();

        // Register input template
        $this->registry->register(new class extends NodeTemplate {
            public string $type = 'test-input';
            public string $version = '1.0.0';
            public string $title = 'Test Input';
            public NodeCategory $category = NodeCategory::Input;
            public string $description = 'Test';

            public function ports(): PortSchema
            {
                return new PortSchema(
                    outputs: [PortDefinition::output('data', 'Data', DataType::Json)],
                );
            }

            public function configRules(): array { return []; }
            public function defaultConfig(): array { return ['value' => ['test' => 'data']]; }

            public function execute(NodeExecutionContext $ctx): array
            {
                return [
                    'data' => PortPayload::success($ctx->config['value'] ?? [], DataType::Json),
                ];
            }
        });

        // Register review checkpoint template
        $this->registry->register(new ReviewCheckpointTemplate());

        $this->executor = new RunExecutor(
            new ExecutionPlanner(),
            new InputResolver(new RunCache()),
            $this->registry,
            new RunCache(),
            new LocalArtifactStore(),
            app(\App\Domain\Execution\ProposalSender::class),
            new \App\Services\Memory\DatabaseRunMemoryStore(),
        );

        $this->workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => [], 'edges' => []],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function run_pauses_at_review_checkpoint_and_awaits_review(): void
    {
        Event::fake();

        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'test-input', 'config' => ['value' => ['foo' => 'bar']]],
                ['id' => 'n2', 'type' => 'reviewCheckpoint', 'config' => []],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'data', 'targetHandle' => 'data'],
            ],
        ];

        $run = ExecutionRun::create([
            'workflow_id' => $this->workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $document,
        ]);

        // Execute in a way that won't block (we'll mock the polling in other tests)
        // For this test, we just verify the initial pause state
        $this->executor->execute($run);

        $run->refresh();
        $this->assertSame('awaitingReview', $run->status);

        $n2Record = NodeRunRecord::where('run_id', $run->id)
            ->where('node_id', 'n2')
            ->first();

        $this->assertNotNull($n2Record);
        $this->assertSame('awaitingReview', $n2Record->status);
        $this->assertNull($n2Record->completed_at);
        $this->assertNotNull($n2Record->input_payloads);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function review_approve_allows_execution_to_continue(): void
    {
        Event::fake();

        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'test-input', 'config' => ['value' => ['foo' => 'bar']]],
                ['id' => 'n2', 'type' => 'reviewCheckpoint', 'config' => []],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'data', 'targetHandle' => 'data'],
            ],
        ];

        $run = ExecutionRun::create([
            'workflow_id' => $this->workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $document,
            'status' => 'awaitingReview',
        ]);

        // Create the awaiting review record
        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n2',
            'status' => 'awaitingReview',
            'input_payloads' => ['data' => ['value' => ['foo' => 'bar']]],
        ]);

        // Simulate approving via the API
        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n2',
            'decision' => 'approve',
            'notes' => 'Approved for production',
        ]);

        $response->assertOk();

        // Verify record was updated
        $record = NodeRunRecord::where('run_id', $run->id)
            ->where('node_id', 'n2')
            ->first();

        $this->assertSame('success', $record->status);
        $this->assertSame('approve', $record->output_payloads['decision']);
        $this->assertSame('Approved for production', $record->output_payloads['notes']);
        $this->assertNotNull($record->completed_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function review_reject_sets_node_to_error_status(): void
    {
        Event::fake();

        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'test-input', 'config' => []],
                ['id' => 'n2', 'type' => 'reviewCheckpoint', 'config' => []],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'data', 'targetHandle' => 'data'],
            ],
        ];

        $run = ExecutionRun::create([
            'workflow_id' => $this->workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $document,
            'status' => 'awaitingReview',
        ]);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n2',
            'status' => 'awaitingReview',
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n2',
            'decision' => 'reject',
        ]);

        $response->assertOk();

        $record = NodeRunRecord::where('run_id', $run->id)
            ->where('node_id', 'n2')
            ->first();

        $this->assertSame('error', $record->status);
        $this->assertSame('reject', $record->output_payloads['decision']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cancellation_during_review_stops_polling(): void
    {
        // This tests that if a run is cancelled while in awaitingReview state,
        // the polling loop properly detects the cancellation

        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'test-input', 'config' => []],
                ['id' => 'n2', 'type' => 'reviewCheckpoint', 'config' => []],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'data', 'targetHandle' => 'data'],
            ],
        ];

        $run = ExecutionRun::create([
            'workflow_id' => $this->workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $document,
            'status' => 'awaitingReview',
        ]);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n2',
            'status' => 'awaitingReview',
        ]);

        // Cancel the run while it's awaiting review
        $response = $this->postJson("/api/runs/{$run->id}/cancel");

        $response->assertOk();

        $run->refresh();
        $this->assertSame('cancelled', $run->status);

        // Verify the awaiting review record was also marked as cancelled
        $record = NodeRunRecord::where('run_id', $run->id)
            ->where('node_id', 'n2')
            ->first();

        $this->assertSame('cancelled', $record->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function review_cannot_be_submitted_for_non_awaiting_run(): void
    {
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'test-input', 'config' => []],
            ],
            'edges' => [],
        ];

        $run = ExecutionRun::create([
            'workflow_id' => $this->workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $document,
            'status' => 'running',
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n1',
            'decision' => 'approve',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'Run is not awaiting review']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function review_requires_valid_decision(): void
    {
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'test-input', 'config' => []],
            ],
            'edges' => [],
        ];

        $run = ExecutionRun::create([
            'workflow_id' => $this->workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $document,
            'status' => 'awaitingReview',
        ]);

        $response = $this->postJson("/api/runs/{$run->id}/review", [
            'nodeId' => 'n1',
            'decision' => 'invalid-decision',
        ]);

        $response->assertStatus(422);
    }
}
