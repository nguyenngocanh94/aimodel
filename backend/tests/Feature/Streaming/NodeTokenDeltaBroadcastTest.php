<?php

declare(strict_types=1);

namespace Tests\Feature\Streaming;

use App\Domain\DataType;
use App\Domain\Execution\ExecutionPlanner;
use App\Domain\Execution\InputResolver;
use App\Domain\Execution\ProposalSender;
use App\Domain\Execution\RunCache;
use App\Domain\Execution\RunExecutor;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use App\Events\NodeTokenDelta;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use App\Services\LocalArtifactStore;
use App\Services\Memory\DatabaseRunMemoryStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LP-C2: assert that when a template calls `$ctx->emitTokenDelta(...)` during
 * execute(), the RunExecutor's closure broadcasts a `NodeTokenDelta` event on
 * the run's channel with a monotonically increasing `seq`.
 */
final class NodeTokenDeltaBroadcastTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function emit_token_delta_broadcasts_three_frames_in_order(): void
    {
        Event::fake([NodeTokenDelta::class]);

        $registry = new NodeTemplateRegistry();
        $registry->register(new class extends NodeTemplate {
            public string $type = 'streaming-stub';
            public string $version = '1.0.0';
            public string $title = 'Streaming Stub';
            public NodeCategory $category = NodeCategory::Script;
            public string $description = 'Emits three token deltas.';

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
                $ctx->emitTokenDelta('Hel', 'msg-1');
                $ctx->emitTokenDelta('lo ', 'msg-1');
                $ctx->emitTokenDelta('world', 'msg-1');
                return ['out' => PortPayload::success('Hello world', DataType::Text)];
            }
        });

        $executor = new RunExecutor(
            new ExecutionPlanner(),
            new InputResolver(new RunCache()),
            $registry,
            new RunCache(),
            new LocalArtifactStore(),
            $this->createMock(ProposalSender::class),
            new DatabaseRunMemoryStore(),
        );

        $workflow = Workflow::create([
            'name' => 'Stream test',
            'document' => [
                'nodes' => [
                    ['id' => 'n1', 'type' => 'streaming-stub', 'data' => ['config' => []]],
                ],
                'edges' => [],
            ],
        ]);

        $run = ExecutionRun::create([
            'workflow_id' => $workflow->id,
            'mode' => 'test',
            'trigger' => 'runWorkflow',
            'status' => 'queued',
        ]);

        $executor->execute($run);

        Event::assertDispatchedTimes(NodeTokenDelta::class, 3);

        $deltas = [];
        Event::assertDispatched(NodeTokenDelta::class, function (NodeTokenDelta $e) use (&$deltas, $run): bool {
            $this->assertSame($run->id, $e->runId);
            $this->assertSame('n1', $e->nodeId);
            $this->assertSame('msg-1', $e->messageId);
            $deltas[] = ['delta' => $e->delta, 'seq' => $e->seq];
            return true;
        });

        $this->assertSame([
            ['delta' => 'Hel', 'seq' => 0],
            ['delta' => 'lo ', 'seq' => 1],
            ['delta' => 'world', 'seq' => 2],
        ], $deltas);
    }

    #[Test]
    public function broadcast_payload_includes_expected_fields(): void
    {
        $event = new NodeTokenDelta(
            runId: 'r1',
            nodeId: 'n1',
            messageId: 'm1',
            delta: 'x',
            seq: 7,
        );

        $this->assertSame('node.token.delta', $event->broadcastAs());
        $this->assertSame(
            ['runId' => 'r1', 'nodeId' => 'n1', 'messageId' => 'm1', 'delta' => 'x', 'seq' => 7],
            $event->broadcastWith(),
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertSame('run.r1', $channels[0]->name);
    }
}
