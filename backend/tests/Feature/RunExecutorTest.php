<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\DataType;
use App\Domain\Execution\ExecutionPlanner;
use App\Domain\Execution\InputResolver;
use App\Domain\Execution\ProposalSender;
use App\Domain\Execution\RunCache;
use App\Domain\Execution\RunExecutor;
use App\Domain\NodeCategory;
use App\Domain\Nodes\Exceptions\ReviewPendingException;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use App\Services\ArtifactStoreContract;
use App\Services\LocalArtifactStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RunExecutorTest extends TestCase
{
    use RefreshDatabase;

    private RunExecutor $executor;
    private NodeTemplateRegistry $registry;
    private Workflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->registry = new NodeTemplateRegistry();

        // Register a simple input template (no inputs, outputs prompt)
        $this->registry->register(new class extends NodeTemplate {
            public string $type = 'test-input';
            public string $version = '1.0.0';
            public string $title = 'Test Input';
            public NodeCategory $category = NodeCategory::Input;
            public string $description = 'Test';

            public function ports(): PortSchema
            {
                return new PortSchema(
                    outputs: [PortDefinition::output('out', 'Output', DataType::Text)],
                );
            }

            public function configRules(): array { return []; }
            public function defaultConfig(): array { return ['value' => 'hello']; }

            public function execute(NodeExecutionContext $ctx): array
            {
                return [
                    'out' => PortPayload::success($ctx->config['value'] ?? 'default', DataType::Text),
                ];
            }
        });

        // Register a processing template
        $this->registry->register(new class extends NodeTemplate {
            public string $type = 'test-processor';
            public string $version = '1.0.0';
            public string $title = 'Test Processor';
            public NodeCategory $category = NodeCategory::Script;
            public string $description = 'Test';

            public function ports(): PortSchema
            {
                return new PortSchema(
                    inputs: [PortDefinition::input('in', 'Input', DataType::Text)],
                    outputs: [PortDefinition::output('out', 'Output', DataType::Text)],
                );
            }

            public function configRules(): array { return []; }
            public function defaultConfig(): array { return []; }

            public function execute(NodeExecutionContext $ctx): array
            {
                $input = $ctx->inputValue('in') ?? '';
                return [
                    'out' => PortPayload::success("processed:{$input}", DataType::Text),
                ];
            }
        });

        $proposalSender = $this->createMock(ProposalSender::class);

        $this->executor = new RunExecutor(
            new ExecutionPlanner(),
            new InputResolver(new RunCache()),
            $this->registry,
            new RunCache(),
            new LocalArtifactStore(),
            $proposalSender,
            new \App\Services\Memory\DatabaseRunMemoryStore(),
        );

        $this->workflow = Workflow::create([
            'name' => 'Test',
            'document' => ['nodes' => [], 'edges' => []],
        ]);
    }

    #[Test]
    public function executes_nodes_in_topological_order(): void
    {
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'test-input', 'config' => ['value' => 'hello']],
                ['id' => 'n2', 'type' => 'test-processor', 'config' => []],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'out', 'targetHandle' => 'in'],
            ],
        ];

        $run = ExecutionRun::create([
            'workflow_id' => $this->workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $document,
        ]);

        $this->executor->execute($run);

        $run->refresh();
        $this->assertSame('success', $run->status);
        $this->assertNotNull($run->completed_at);

        $records = $run->nodeRunRecords()->orderBy('completed_at')->get();
        $this->assertCount(2, $records);

        $n1Record = $records->firstWhere('node_id', 'n1');
        $n2Record = $records->firstWhere('node_id', 'n2');

        $this->assertSame('success', $n1Record->status);
        $this->assertSame('success', $n2Record->status);
        $this->assertStringContainsString('processed:hello', $n2Record->output_payloads['out']['value']);
    }

    #[Test]
    public function skips_disabled_nodes(): void
    {
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'test-input', 'config' => ['value' => 'hi'], 'disabled' => true],
            ],
            'edges' => [],
        ];

        $run = ExecutionRun::create([
            'workflow_id' => $this->workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $document,
        ]);

        $this->executor->execute($run);

        $run->refresh();
        $record = $run->nodeRunRecords()->first();

        $this->assertSame('skipped', $record->status);
        $this->assertSame('disabled', $record->skip_reason);
    }

    #[Test]
    public function handles_error_in_node_without_crashing_run(): void
    {
        // Register a node that throws
        $this->registry->register(new class extends NodeTemplate {
            public string $type = 'test-error';
            public string $version = '1.0.0';
            public string $title = 'Error Node';
            public NodeCategory $category = NodeCategory::Utility;
            public string $description = 'Always fails';

            public function ports(): PortSchema
            {
                return new PortSchema(
                    outputs: [PortDefinition::output('out', 'Output', DataType::Text)],
                );
            }

            public function configRules(): array { return []; }
            public function defaultConfig(): array { return []; }

            public function execute(NodeExecutionContext $ctx): array
            {
                throw new \RuntimeException('Intentional test error');
            }
        });

        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'test-error', 'config' => []],
            ],
            'edges' => [],
        ];

        $run = ExecutionRun::create([
            'workflow_id' => $this->workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $document,
        ]);

        $this->executor->execute($run);

        $run->refresh();
        $this->assertSame('error', $run->status);

        $record = $run->nodeRunRecords()->first();
        $this->assertSame('error', $record->status);
        $this->assertSame('Intentional test error', $record->error_message);
    }

    #[Test]
    public function cache_hit_skips_execution(): void
    {
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'test-input', 'config' => ['value' => 'cached-test']],
            ],
            'edges' => [],
        ];

        // First run: populate cache
        $run1 = ExecutionRun::create([
            'workflow_id' => $this->workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $document,
        ]);
        $this->executor->execute($run1);

        // Second run: should hit cache
        $run2 = ExecutionRun::create([
            'workflow_id' => $this->workflow->id,
            'trigger' => 'runWorkflow',
            'document_snapshot' => $document,
        ]);
        $this->executor->execute($run2);

        $run2->refresh();
        $record = $run2->nodeRunRecords()->first();

        $this->assertSame('success', $record->status);
        $this->assertTrue($record->used_cache);
    }
}
