<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    private function sampleDocument(): array
    {
        return [
            'nodes' => [
                ['id' => 'node-1', 'type' => 'script-writer', 'disabled' => false],
            ],
            'edges' => [],
        ];
    }

    #[Test]
    public function create_workflow_with_valid_document(): void
    {
        $response = $this->postJson('/api/workflows', [
            'name' => 'My Workflow',
            'description' => 'Test description',
            'document' => $this->sampleDocument(),
            'tags' => ['video', 'test'],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'description', 'schemaVersion', 'tags', 'document', 'createdAt', 'updatedAt'],
            ])
            ->assertJsonPath('data.name', 'My Workflow')
            ->assertJsonPath('data.tags', ['video', 'test']);

        $this->assertDatabaseHas('workflows', ['name' => 'My Workflow']);
    }

    #[Test]
    public function create_rejects_missing_name(): void
    {
        $response = $this->postJson('/api/workflows', [
            'document' => $this->sampleDocument(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function create_rejects_missing_document(): void
    {
        $response = $this->postJson('/api/workflows', [
            'name' => 'Workflow',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document']);
    }

    #[Test]
    public function list_with_pagination(): void
    {
        Workflow::factory()->count(20)->create();

        $response = $this->getJson('/api/workflows?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    public function list_with_search_filter(): void
    {
        Workflow::factory()->create(['name' => 'Alpha Workflow']);
        Workflow::factory()->create(['name' => 'Beta Pipeline']);

        $response = $this->getJson('/api/workflows?search=Alpha');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha Workflow');
    }

    #[Test]
    public function list_with_tags_filter(): void
    {
        Workflow::factory()->create(['tags' => ['video', 'ai']]);
        Workflow::factory()->create(['tags' => ['audio']]);

        $response = $this->getJson('/api/workflows?tags=video');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function show_returns_full_document(): void
    {
        $workflow = Workflow::factory()->create([
            'document' => $this->sampleDocument(),
        ]);

        $response = $this->getJson("/api/workflows/{$workflow->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $workflow->id)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'document'],
            ]);
    }

    #[Test]
    public function update_modifies_document(): void
    {
        $workflow = Workflow::factory()->create();

        $newDocument = [
            'nodes' => [
                ['id' => 'node-1', 'type' => 'updated'],
                ['id' => 'node-2', 'type' => 'new-node'],
            ],
            'edges' => [['source' => 'node-1', 'target' => 'node-2']],
        ];

        $response = $this->putJson("/api/workflows/{$workflow->id}", [
            'name' => 'Updated Name',
            'document' => $newDocument,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('workflows', ['name' => 'Updated Name']);
    }

    #[Test]
    public function delete_cascades_to_runs(): void
    {
        $workflow = Workflow::factory()->create();

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'started_at' => now(),
        ]);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'node-1',
        ]);

        $response = $this->deleteJson("/api/workflows/{$workflow->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
        $this->assertDatabaseMissing('execution_runs', ['id' => $run->id]);
        $this->assertDatabaseMissing('node_run_records', ['run_id' => $run->id]);
    }

    #[Test]
    public function show_returns_404_for_missing_workflow(): void
    {
        $response = $this->getJson('/api/workflows/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }
}
