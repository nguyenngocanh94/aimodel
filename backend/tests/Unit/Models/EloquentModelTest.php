<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ExecutionRun;
use App\Models\NodeRunRecord;
use App\Models\RunCacheEntry;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EloquentModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function workflow_jsonb_casts_round_trip(): void
    {
        $workflow = Workflow::create([
            'name' => 'Test Workflow',
            'description' => 'A test workflow',
            'schema_version' => 1,
            'tags' => ['video', 'ai'],
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $fresh = Workflow::find($workflow->id);

        $this->assertIsArray($fresh->tags);
        $this->assertSame(['video', 'ai'], $fresh->tags);
        $this->assertIsArray($fresh->document);
        $this->assertEquals(['nodes' => [], 'edges' => []], $fresh->document);
    }

    #[Test]
    public function execution_run_jsonb_casts_round_trip(): void
    {
        $workflow = Workflow::create([
            'name' => 'W',
            'document' => ['nodes' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'planned_node_ids' => ['node-1', 'node-2'],
            'document_snapshot' => ['nodes' => [['id' => 'node-1']]],
            'node_config_hashes' => ['node-1' => 'abc123'],
            'started_at' => now(),
        ]);

        $fresh = ExecutionRun::find($run->id);

        $this->assertIsArray($fresh->planned_node_ids);
        $this->assertSame(['node-1', 'node-2'], $fresh->planned_node_ids);
        $this->assertIsArray($fresh->document_snapshot);
        $this->assertIsArray($fresh->node_config_hashes);
        $this->assertSame('abc123', $fresh->node_config_hashes['node-1']);
    }

    #[Test]
    public function node_run_record_jsonb_casts_round_trip(): void
    {
        $workflow = Workflow::create([
            'name' => 'W',
            'document' => ['nodes' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'started_at' => now(),
        ]);

        $record = NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'node-1',
            'status' => 'success',
            'input_payloads' => ['port-in' => ['value' => 'hello']],
            'output_payloads' => ['port-out' => ['value' => 'world']],
            'used_cache' => true,
            'duration_ms' => 150,
        ]);

        $fresh = NodeRunRecord::find($record->id);

        $this->assertIsArray($fresh->input_payloads);
        $this->assertIsArray($fresh->output_payloads);
        $this->assertSame('hello', $fresh->input_payloads['port-in']['value']);
        $this->assertTrue($fresh->used_cache);
        $this->assertSame(150, $fresh->duration_ms);
    }

    #[Test]
    public function run_cache_entry_jsonb_casts_round_trip(): void
    {
        $entry = RunCacheEntry::create([
            'cache_key' => 'test-key-' . uniqid(),
            'node_type' => 'script-writer',
            'template_version' => '1.0.0',
            'output_payloads' => ['script' => ['value' => 'Act 1...']],
            'created_at' => now(),
            'last_accessed_at' => now(),
        ]);

        $fresh = RunCacheEntry::find($entry->id);

        $this->assertIsArray($fresh->output_payloads);
        $this->assertSame('Act 1...', $fresh->output_payloads['script']['value']);
    }

    #[Test]
    public function execution_run_belongs_to_workflow(): void
    {
        $workflow = Workflow::create([
            'name' => 'W',
            'document' => ['nodes' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'started_at' => now(),
        ]);

        $this->assertTrue($run->workflow->is($workflow));
    }

    #[Test]
    public function execution_run_has_many_node_run_records(): void
    {
        $workflow = Workflow::create([
            'name' => 'W',
            'document' => ['nodes' => []],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'trigger' => 'runWorkflow',
            'started_at' => now(),
        ]);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'node-1',
        ]);

        NodeRunRecord::create([
            'run_id' => $run->id,
            'node_id' => 'node-2',
        ]);

        $this->assertCount(2, $run->nodeRunRecords);
    }
}
