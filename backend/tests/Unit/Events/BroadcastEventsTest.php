<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\NodeStatusChanged;
use App\Events\RunCompleted;
use App\Events\RunStarted;
use Illuminate\Broadcasting\Channel;
use PHPUnit\Framework\TestCase;

class BroadcastEventsTest extends TestCase
{
    // --- RunStarted ---

    public function test_run_started_serializes_correctly(): void
    {
        $event = new RunStarted(
            runId: 'run-123',
            status: 'running',
            plannedNodeIds: ['node-1', 'node-2', 'node-3'],
        );

        $data = $event->broadcastWith();

        $this->assertSame('run-123', $data['runId']);
        $this->assertSame('running', $data['status']);
        $this->assertSame(['node-1', 'node-2', 'node-3'], $data['plannedNodeIds']);
    }

    public function test_run_started_broadcasts_on_correct_channel(): void
    {
        $event = new RunStarted('run-456', 'running', []);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('run.run-456', $channels[0]->name);
    }

    public function test_run_started_broadcast_as(): void
    {
        $event = new RunStarted('run-1', 'running', []);
        $this->assertSame('run.started', $event->broadcastAs());
    }

    // --- NodeStatusChanged ---

    public function test_node_status_changed_serializes_correctly(): void
    {
        $event = new NodeStatusChanged(
            runId: 'run-123',
            nodeId: 'node-1',
            status: 'success',
            outputPayloads: ['script' => ['value' => 'test']],
            durationMs: 150,
            errorMessage: null,
            skipReason: null,
            usedCache: true,
        );

        $data = $event->broadcastWith();

        $this->assertSame('run-123', $data['runId']);
        $this->assertSame('node-1', $data['nodeId']);
        $this->assertSame('success', $data['status']);
        $this->assertSame(['script' => ['value' => 'test']], $data['outputPayloads']);
        $this->assertSame(150, $data['durationMs']);
        $this->assertNull($data['errorMessage']);
        $this->assertTrue($data['usedCache']);
    }

    public function test_node_status_changed_with_error(): void
    {
        $event = new NodeStatusChanged(
            runId: 'run-123',
            nodeId: 'node-2',
            status: 'error',
            errorMessage: 'Provider timeout',
        );

        $data = $event->broadcastWith();

        $this->assertSame('error', $data['status']);
        $this->assertSame('Provider timeout', $data['errorMessage']);
        $this->assertFalse($data['usedCache']);
    }

    public function test_node_status_changed_broadcasts_on_run_channel(): void
    {
        $event = new NodeStatusChanged('run-789', 'node-1', 'running');

        $channels = $event->broadcastOn();

        $this->assertSame('run.run-789', $channels[0]->name);
    }

    public function test_node_status_changed_broadcast_as(): void
    {
        $event = new NodeStatusChanged('run-1', 'node-1', 'running');
        $this->assertSame('node.status', $event->broadcastAs());
    }

    // --- RunCompleted ---

    public function test_run_completed_serializes_correctly(): void
    {
        $event = new RunCompleted(
            runId: 'run-123',
            status: 'success',
            terminationReason: null,
            completedAt: '2026-04-05T00:00:00+00:00',
        );

        $data = $event->broadcastWith();

        $this->assertSame('run-123', $data['runId']);
        $this->assertSame('success', $data['status']);
        $this->assertNull($data['terminationReason']);
        $this->assertSame('2026-04-05T00:00:00+00:00', $data['completedAt']);
    }

    public function test_run_completed_with_error(): void
    {
        $event = new RunCompleted(
            runId: 'run-123',
            status: 'error',
            terminationReason: 'node_error',
        );

        $data = $event->broadcastWith();

        $this->assertSame('error', $data['status']);
        $this->assertSame('node_error', $data['terminationReason']);
    }

    public function test_run_completed_broadcasts_on_correct_channel(): void
    {
        $event = new RunCompleted('run-999', 'success');

        $channels = $event->broadcastOn();

        $this->assertSame('run.run-999', $channels[0]->name);
    }

    public function test_run_completed_broadcast_as(): void
    {
        $event = new RunCompleted('run-1', 'success');
        $this->assertSame('run.completed', $event->broadcastAs());
    }
}
