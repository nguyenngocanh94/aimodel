<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RunStreamControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stream_returns_correct_sse_headers(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'running',
            'planned_node_ids' => ['n1', 'n2'],
        ]);

        $response = $this->get("/api/runs/{$run->id}/stream");

        $response->assertOk();
        
        // Check headers - Content-Type should include text/event-stream
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertEquals('keep-alive', $response->headers->get('Connection'));
        $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));
    }

    #[Test]
    public function stream_returns_404_for_missing_run(): void
    {
        $response = $this->get('/api/runs/00000000-0000-0000-0000-000000000000/stream');
        $response->assertNotFound();
    }

    #[Test]
    public function catchup_event_contains_current_run_state(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'test', 'config' => ['key' => 'val']],
                ],
                'edges' => [],
            ],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'running',
            'planned_node_ids' => ['n1'],
            'document_snapshot' => ['nodes' => [['id' => 'n1', 'type' => 'test']], 'edges' => []],
            'document_hash' => 'abc123',
            'node_config_hashes' => ['n1' => 'hash1'],
            'started_at' => now(),
        ]);

        $response = $this->get("/api/runs/{$run->id}/stream");

        $response->assertOk();

        // For StreamedResponse, we need to get the streamed content
        $content = $response->streamedContent();
        $this->assertNotFalse($content, 'Should have streamed content');
        $this->assertStringContainsString('event: run.catchup', $content);
        $this->assertStringContainsString('"run":', $content);
        $this->assertStringContainsString('"nodeRunRecords":', $content);
        $this->assertStringContainsString("\"id\":\"{$run->id}\"", $content);
        $this->assertStringContainsString('"status":"running"', $content);
    }

    #[Test]
    public function catchup_event_includes_all_node_run_records(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'running',
        ]);

        // Create multiple node run records
        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n1',
            'status' => 'success',
            'input_payloads' => ['input' => 'data'],
            'output_payloads' => ['output' => 'result'],
            'duration_ms' => 1000,
            'used_cache' => true,
        ]);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n2',
            'status' => 'error',
            'error_message' => 'Something went wrong',
        ]);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'n3',
            'status' => 'skipped',
            'skip_reason' => 'disabled',
        ]);

        $response = $this->get("/api/runs/{$run->id}/stream");

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertNotFalse($content, 'Should have streamed content');

        // Verify all node records are included
        $this->assertStringContainsString('"nodeId":"n1"', $content);
        $this->assertStringContainsString('"nodeId":"n2"', $content);
        $this->assertStringContainsString('"nodeId":"n3"', $content);
        $this->assertStringContainsString('"status":"success"', $content);
        $this->assertStringContainsString('"status":"error"', $content);
        $this->assertStringContainsString('"status":"skipped"', $content);
    }

    #[Test]
    public function events_are_formatted_as_valid_sse(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'status' => 'pending',
        ]);

        $response = $this->get("/api/runs/{$run->id}/stream");

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertNotFalse($content, 'Should have streamed content');

        // Parse SSE format: event: <type>\ndata: <json>\n\n
        $lines = explode("\n", $content);
        $foundEvent = false;
        $foundData = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, 'event: ')) {
                $foundEvent = true;
                $eventType = substr($line, 7);
                $this->assertNotEmpty($eventType);
            }
            if (str_starts_with($line, 'data: ')) {
                $foundData = true;
                $jsonData = substr($line, 6);
                // Verify valid JSON
                $decoded = json_decode($jsonData, true);
                $this->assertNotNull($decoded, 'SSE data should be valid JSON');
            }
        }

        $this->assertTrue($foundEvent, 'Response should contain event lines');
        $this->assertTrue($foundData, 'Response should contain data lines');
    }

    #[Test]
    public function catchup_payload_has_correct_structure(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test',
            'document' => [
                'nodes' => [['id' => 'n1', 'type' => 'test', 'config' => []]],
                'edges' => [],
            ],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runFromHere',
            'target_node_id' => 'n1',
            'status' => 'running',
            'planned_node_ids' => ['n1'],
            'document_snapshot' => ['nodes' => [['id' => 'n1', 'type' => 'test']], 'edges' => []],
            'document_hash' => 'test-hash',
            'node_config_hashes' => ['n1' => 'config-hash'],
            'started_at' => now(),
        ]);

        $response = $this->get("/api/runs/{$run->id}/stream");
        $content = $response->streamedContent();
        $this->assertNotFalse($content, 'Should have streamed content');

        // Extract the data portion from the catchup event
        $lines = explode("\n", $content);
        $jsonData = null;
        $captureNextData = false;

        foreach ($lines as $line) {
            if ($line === 'event: run.catchup') {
                $captureNextData = true;
            } elseif ($captureNextData && str_starts_with($line, 'data: ')) {
                $jsonData = substr($line, 6);
                break;
            }
        }

        $this->assertNotNull($jsonData, 'Should find catchup event data');
        $data = json_decode($jsonData, true);
        $this->assertNotNull($data, 'Catchup data should be valid JSON');

        // Verify run structure
        $this->assertArrayHasKey('run', $data);
        $this->assertArrayHasKey('id', $data['run']);
        $this->assertArrayHasKey('workflowId', $data['run']);
        $this->assertArrayHasKey('trigger', $data['run']);
        $this->assertArrayHasKey('targetNodeId', $data['run']);
        $this->assertArrayHasKey('plannedNodeIds', $data['run']);
        $this->assertArrayHasKey('status', $data['run']);
        $this->assertArrayHasKey('documentSnapshot', $data['run']);
        $this->assertArrayHasKey('documentHash', $data['run']);
        $this->assertArrayHasKey('nodeConfigHashes', $data['run']);
        $this->assertArrayHasKey('startedAt', $data['run']);

        // Verify nodeRunRecords is an array
        $this->assertArrayHasKey('nodeRunRecords', $data);
        $this->assertIsArray($data['nodeRunRecords']);
    }
}
