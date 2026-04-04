<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Execution\RunExecutor;
use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExecutionRunTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflowDocument(array $overrides = []): array
    {
        return array_merge([
            'nodes' => [
                [
                    'id' => 'node-user-prompt',
                    'type' => 'userPrompt',
                    'config' => ['provider' => 'stub', 'text' => 'Make a video about cats'],
                    'position' => ['x' => 0, 'y' => 0],
                ],
                [
                    'id' => 'node-script-writer',
                    'type' => 'scriptWriter',
                    'config' => [
                        'provider' => 'stub',
                        'style' => 'conversational',
                        'structure' => 'three_act',
                        'includeHook' => true,
                        'includeCTA' => true,
                        'targetDurationSeconds' => 60,
                    ],
                    'position' => ['x' => 300, 'y' => 0],
                ],
                [
                    'id' => 'node-scene-splitter',
                    'type' => 'sceneSplitter',
                    'config' => [
                        'provider' => 'stub',
                        'maxScenes' => 5,
                        'includeVisualDescriptions' => true,
                    ],
                    'position' => ['x' => 600, 'y' => 0],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-1',
                    'source' => 'node-user-prompt',
                    'sourceHandle' => 'prompt',
                    'target' => 'node-script-writer',
                    'targetHandle' => 'prompt',
                ],
                [
                    'id' => 'edge-2',
                    'source' => 'node-script-writer',
                    'sourceHandle' => 'script',
                    'target' => 'node-scene-splitter',
                    'targetHandle' => 'script',
                ],
            ],
        ], $overrides);
    }

    // --- Trigger endpoints ---

    public function test_trigger_run_returns_202(): void
    {
        Queue::fake();

        $workflow = Workflow::create([
            'name' => 'Test Workflow',
            'document' => $this->createWorkflowDocument(),
        ]);

        $response = $this->postJson("/api/workflows/{$workflow->id}/runs", [
            'trigger' => 'runWorkflow',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'data' => ['id', 'workflowId', 'trigger', 'status'],
        ]);

        $this->assertDatabaseHas('execution_runs', [
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'pending',
        ]);
    }

    public function test_trigger_invalid_trigger_returns_422(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test Workflow',
            'document' => $this->createWorkflowDocument(),
        ]);

        $response = $this->postJson("/api/workflows/{$workflow->id}/runs", [
            'trigger' => 'invalidTrigger',
        ]);

        $response->assertStatus(422);
    }

    public function test_show_run_returns_with_node_records(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test Workflow',
            'document' => $this->createWorkflowDocument(),
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'success',
            'document_snapshot' => $this->createWorkflowDocument(),
            'document_hash' => hash('sha256', 'test'),
            'node_config_hashes' => [],
        ]);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'node-user-prompt',
            'status' => 'success',
        ]);

        $response = $this->getJson("/api/runs/{$run->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'workflowId',
                'status',
                'nodeRunRecords',
            ],
        ]);
    }

    // --- Full execution flow ---

    public function test_full_run_creates_all_node_records(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test Pipeline',
            'document' => $this->createWorkflowDocument(),
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'pending',
            'document_snapshot' => $this->createWorkflowDocument(),
            'document_hash' => hash('sha256', 'test'),
            'node_config_hashes' => [],
        ]);

        $executor = app(RunExecutor::class);
        $executor->execute($run);

        $run->refresh();
        $this->assertSame('success', $run->status);

        $records = NodeRunRecord::where('run_id', $run->id)->get();
        $this->assertGreaterThanOrEqual(3, $records->count());

        foreach ($records as $record) {
            $this->assertContains($record->status, ['success', 'skipped']);
        }
    }

    public function test_run_node_trigger_executes_only_target(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test Pipeline',
            'document' => $this->createWorkflowDocument(),
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runNode',
            'target_node_id' => 'node-script-writer',
            'status' => 'pending',
            'document_snapshot' => $this->createWorkflowDocument(),
            'document_hash' => hash('sha256', 'test'),
            'node_config_hashes' => [],
        ]);

        $executor = app(RunExecutor::class);
        $executor->execute($run);

        $run->refresh();
        $records = NodeRunRecord::where('run_id', $run->id)->get();

        $executedNodeIds = $records
            ->whereNotIn('status', ['skipped'])
            ->pluck('node_id')
            ->toArray();

        // RunNode only runs the target node itself
        $this->assertContains('node-script-writer', $executedNodeIds);
        $this->assertNotContains('node-scene-splitter', $executedNodeIds);
    }

    public function test_error_in_one_node_sets_error_status(): void
    {
        $document = $this->createWorkflowDocument([
            'nodes' => [
                [
                    'id' => 'node-user-prompt',
                    'type' => 'userPrompt',
                    'config' => ['provider' => 'stub', 'text' => 'test'],
                    'position' => ['x' => 0, 'y' => 0],
                ],
                [
                    'id' => 'node-unknown',
                    'type' => 'nonExistentType',
                    'config' => [],
                    'position' => ['x' => 300, 'y' => 0],
                ],
            ],
            'edges' => [],
        ]);

        $workflow = Workflow::create([
            'name' => 'Error Test',
            'document' => $document,
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'pending',
            'document_snapshot' => $document,
            'document_hash' => hash('sha256', 'test'),
            'node_config_hashes' => [],
        ]);

        $executor = app(RunExecutor::class);
        $executor->execute($run);

        $run->refresh();
        $this->assertSame('error', $run->status);

        $errorRecords = NodeRunRecord::where('run_id', $run->id)
            ->where('status', 'error')
            ->get();
        $this->assertGreaterThanOrEqual(1, $errorRecords->count());
    }

    public function test_disabled_node_is_skipped(): void
    {
        $document = [
            'nodes' => [
                [
                    'id' => 'node-1',
                    'type' => 'userPrompt',
                    'config' => ['provider' => 'stub', 'text' => 'test'],
                    'position' => ['x' => 0, 'y' => 0],
                ],
                [
                    'id' => 'node-2',
                    'type' => 'scriptWriter',
                    'config' => [
                        'provider' => 'stub',
                        'style' => 'conversational',
                        'structure' => 'three_act',
                        'includeHook' => true,
                        'includeCTA' => true,
                        'targetDurationSeconds' => 60,
                    ],
                    'disabled' => true,
                    'position' => ['x' => 300, 'y' => 0],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge-1',
                    'source' => 'node-1',
                    'sourceHandle' => 'prompt',
                    'target' => 'node-2',
                    'targetHandle' => 'prompt',
                ],
            ],
        ];

        $workflow = Workflow::create([
            'name' => 'Skip Test',
            'document' => $document,
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'pending',
            'document_snapshot' => $document,
            'document_hash' => hash('sha256', 'test'),
            'node_config_hashes' => [],
        ]);

        $executor = app(RunExecutor::class);
        $executor->execute($run);

        $skipped = NodeRunRecord::where('run_id', $run->id)
            ->where('node_id', 'node-2')
            ->first();

        $this->assertNotNull($skipped);
        $this->assertSame('skipped', $skipped->status);
    }
}
