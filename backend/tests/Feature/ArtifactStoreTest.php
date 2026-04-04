<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Artifact;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use App\Services\ArtifactStoreContract;
use App\Services\LocalArtifactStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ArtifactStoreTest extends TestCase
{
    use RefreshDatabase;

    private LocalArtifactStore $store;
    private string $runId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->store = new LocalArtifactStore();

        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'started_at' => now(),
        ]);

        $this->runId = $run->id;
    }

    #[Test]
    public function put_stores_file_and_creates_record(): void
    {
        $artifact = $this->store->put(
            $this->runId,
            'node-1',
            'test-image.png',
            'fake-png-contents',
            'image/png'
        );

        $this->assertInstanceOf(Artifact::class, $artifact);
        $this->assertSame('test-image.png', $artifact->name);
        $this->assertSame('image/png', $artifact->mime_type);
        $this->assertSame(strlen('fake-png-contents'), $artifact->size_bytes);

        Storage::disk('local')->assertExists("artifacts/{$this->runId}/node-1/test-image.png");
        $this->assertDatabaseHas('artifacts', ['name' => 'test-image.png']);
    }

    #[Test]
    public function get_retrieves_correct_bytes(): void
    {
        $content = 'test-binary-content-' . random_bytes(16);

        $artifact = $this->store->put(
            $this->runId,
            'node-1',
            'data.bin',
            $content,
            'application/octet-stream'
        );

        $retrieved = $this->store->get($artifact);
        $this->assertSame($content, $retrieved);
    }

    #[Test]
    public function delete_removes_file_and_record(): void
    {
        $artifact = $this->store->put(
            $this->runId,
            'node-1',
            'to-delete.txt',
            'content',
            'text/plain'
        );

        $artifactId = $artifact->id;
        $this->store->delete($artifact);

        Storage::disk('local')->assertMissing("artifacts/{$this->runId}/node-1/to-delete.txt");
        $this->assertDatabaseMissing('artifacts', ['id' => $artifactId]);
    }

    #[Test]
    public function delete_for_run_removes_all_artifacts(): void
    {
        $this->store->put($this->runId, 'node-1', 'a.txt', 'aaa', 'text/plain');
        $this->store->put($this->runId, 'node-2', 'b.txt', 'bbb', 'text/plain');

        $this->assertDatabaseCount('artifacts', 2);

        $this->store->deleteForRun($this->runId);

        $this->assertDatabaseCount('artifacts', 0);
    }

    #[Test]
    public function url_returns_api_endpoint(): void
    {
        $artifact = $this->store->put(
            $this->runId,
            'node-1',
            'image.png',
            'data',
            'image/png'
        );

        $url = $this->store->url($artifact);
        $this->assertStringContainsString("/api/artifacts/{$artifact->id}", $url);
    }

    #[Test]
    public function artifact_controller_streams_file(): void
    {
        $artifact = $this->store->put(
            $this->runId,
            'node-1',
            'download.txt',
            'hello world',
            'text/plain'
        );

        $response = $this->get("/api/artifacts/{$artifact->id}");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    #[Test]
    public function artifact_controller_returns_404_for_missing(): void
    {
        $response = $this->get('/api/artifacts/00000000-0000-0000-0000-000000000000');
        $response->assertNotFound();
    }

    #[Test]
    public function contract_is_bound_in_container(): void
    {
        $store = app(ArtifactStoreContract::class);
        $this->assertInstanceOf(LocalArtifactStore::class, $store);
    }
}
