<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Execution\RunExecutor;
use App\Models\Artifact;
use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EndToEndPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function createFullPipeline(): Workflow
    {
        return Workflow::create([
            'name' => 'Full M1 Pipeline',
            'document' => [
                'nodes' => [
                    [
                        'id' => 'user-prompt',
                        'type' => 'userPrompt',
                        'config' => ['prompt' => 'A video about the future of AI'],
                        'position' => ['x' => 0, 'y' => 0],
                    ],
                    [
                        'id' => 'script-writer',
                        'type' => 'scriptWriter',
                        'config' => [
                            'provider' => 'stub',
                            'style' => 'cinematic narration',
                            'structure' => 'three_act',
                            'includeHook' => true,
                            'includeCTA' => true,
                            'targetDurationSeconds' => 60,
                        ],
                        'position' => ['x' => 300, 'y' => 0],
                    ],
                    [
                        'id' => 'scene-splitter',
                        'type' => 'sceneSplitter',
                        'config' => [
                            'provider' => 'stub',
                            'maxScenes' => 5,
                            'includeVisualDescriptions' => true,
                        ],
                        'position' => ['x' => 600, 'y' => 0],
                    ],
                    [
                        'id' => 'prompt-refiner',
                        'type' => 'promptRefiner',
                        'config' => [
                            'provider' => 'stub',
                            'imageStyle' => 'cinematic',
                            'aspectRatio' => '16:9',
                            'detailLevel' => 'detailed',
                        ],
                        'position' => ['x' => 900, 'y' => 0],
                    ],
                    [
                        'id' => 'image-gen',
                        'type' => 'imageGenerator',
                        'config' => [
                            'provider' => 'stub',
                            'inputMode' => 'prompt',
                            'outputMode' => 'single',
                        ],
                        'position' => ['x' => 1200, 'y' => 0],
                    ],
                ],
                'edges' => [
                    [
                        'id' => 'e1',
                        'source' => 'user-prompt',
                        'sourceHandle' => 'prompt',
                        'target' => 'script-writer',
                        'targetHandle' => 'prompt',
                    ],
                    [
                        'id' => 'e2',
                        'source' => 'script-writer',
                        'sourceHandle' => 'script',
                        'target' => 'scene-splitter',
                        'targetHandle' => 'script',
                    ],
                    [
                        'id' => 'e3',
                        'source' => 'scene-splitter',
                        'sourceHandle' => 'scenes',
                        'target' => 'prompt-refiner',
                        'targetHandle' => 'scenes',
                    ],
                    [
                        'id' => 'e4',
                        'source' => 'prompt-refiner',
                        'sourceHandle' => 'prompts',
                        'target' => 'image-gen',
                        'targetHandle' => 'prompt',
                    ],
                ],
            ],
        ]);
    }

    public function test_full_pipeline_completes_with_all_nodes_success(): void
    {
        $workflow = $this->createFullPipeline();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'pending',
            'document_snapshot' => $workflow->document,
            'document_hash' => hash('sha256', json_encode($workflow->document)),
            'node_config_hashes' => [],
        ]);

        $executor = app(RunExecutor::class);
        $executor->execute($run);

        $run->refresh();
        $this->assertSame('success', $run->status);
        $this->assertNotNull($run->completed_at);

        $records = NodeRunRecord::where('run_id', $run->id)->get();
        $this->assertCount(5, $records);

        foreach ($records as $record) {
            $this->assertSame('success', $record->status, "Node {$record->node_id} should be success, got {$record->status}");
        }
    }

    public function test_script_writer_produces_script_output(): void
    {
        $workflow = $this->createFullPipeline();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'pending',
            'document_snapshot' => $workflow->document,
            'document_hash' => hash('sha256', json_encode($workflow->document)),
            'node_config_hashes' => [],
        ]);

        app(RunExecutor::class)->execute($run);

        $scriptRecord = NodeRunRecord::where('run_id', $run->id)
            ->where('node_id', 'script-writer')
            ->first();

        $this->assertSame('success', $scriptRecord->status);
        $this->assertArrayHasKey('script', $scriptRecord->output_payloads);
    }

    public function test_scene_splitter_produces_scene_list_output(): void
    {
        $workflow = $this->createFullPipeline();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'pending',
            'document_snapshot' => $workflow->document,
            'document_hash' => hash('sha256', json_encode($workflow->document)),
            'node_config_hashes' => [],
        ]);

        app(RunExecutor::class)->execute($run);

        $sceneRecord = NodeRunRecord::where('run_id', $run->id)
            ->where('node_id', 'scene-splitter')
            ->first();

        $this->assertSame('success', $sceneRecord->status);
        $this->assertArrayHasKey('scenes', $sceneRecord->output_payloads);
    }

    public function test_image_generator_creates_artifacts(): void
    {
        $workflow = $this->createFullPipeline();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'pending',
            'document_snapshot' => $workflow->document,
            'document_hash' => hash('sha256', json_encode($workflow->document)),
            'node_config_hashes' => [],
        ]);

        app(RunExecutor::class)->execute($run);

        $imageRecord = NodeRunRecord::where('run_id', $run->id)
            ->where('node_id', 'image-gen')
            ->first();

        $this->assertSame('success', $imageRecord->status);
        $this->assertArrayHasKey('image', $imageRecord->output_payloads);

        // Check artifacts were created
        $artifacts = Artifact::where('run_id', $run->id)
            ->where('node_id', 'image-gen')
            ->get();

        $this->assertGreaterThanOrEqual(1, $artifacts->count());
        foreach ($artifacts as $artifact) {
            $this->assertSame('image/png', $artifact->mime_type);
            $this->assertGreaterThan(0, $artifact->size_bytes);
        }
    }

    public function test_get_run_returns_complete_data(): void
    {
        $workflow = $this->createFullPipeline();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'pending',
            'document_snapshot' => $workflow->document,
            'document_hash' => hash('sha256', json_encode($workflow->document)),
            'node_config_hashes' => [],
        ]);

        app(RunExecutor::class)->execute($run);

        $response = $this->getJson("/api/runs/{$run->id}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'success')
            ->assertJsonCount(5, 'data.nodeRunRecords');
    }
}
