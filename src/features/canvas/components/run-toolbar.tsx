/**
 * RunToolbar - AiModel-ecs.5
 * Toolbar above the canvas for triggering mock execution runs.
 * Per plan section 6.6
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Play,
  Square,
  SkipForward,
  ArrowDown,
  ArrowUp,
  FlaskConical,
  CheckCircle2,
  XCircle,
  Clock,
  Loader2,
} from 'lucide-react';
import { Button } from '@/shared/ui/button';
import { Badge } from '@/shared/ui/badge';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import {
  selectDocument,
  selectSelectedNodeIds,
} from '@/features/workflow/store/workflow-selectors';
import { useRunStore } from '@/features/execution/store/run-store';
import {
  selectActiveRun,
  selectIsRunning,
  selectCanCancel,
  selectNodeRunRecords,
} from '@/features/execution/store/run-selectors';
import { planExecution } from '@/features/execution/domain/run-planner';
import { executeMockRun } from '@/features/execution/domain/mock-executor';
import { runCache } from '@/features/execution/domain/run-cache';

function formatElapsed(ms: number): string {
  if (ms < 1000) return `${Math.round(ms)}ms`;
  const seconds = Math.floor(ms / 1000);
  const minutes = Math.floor(seconds / 60);
  if (minutes === 0) return `${seconds}s`;
  return `${minutes}m ${seconds % 60}s`;
}

export function RunToolbar() {
  const document = useWorkflowStore(selectDocument);
  const selectedNodeIds = useWorkflowStore(selectSelectedNodeIds);
  const activeRun = useRunStore(selectActiveRun);
  const isRunning = useRunStore(selectIsRunning);
  const canCancel = useRunStore(selectCanCancel);
  const nodeRunRecords = useRunStore(selectNodeRunRecords);

  const statusCounts = useMemo(() => {
    const records = Object.values(nodeRunRecords);
    return {
      success: records.filter((r) => r.status === 'success').length,
      error: records.filter((r) => r.status === 'error').length,
      total: records.length,
    };
  }, [nodeRunRecords]);

  const hasSelectedNode = selectedNodeIds.length === 1;
  const selectedNodeId = hasSelectedNode ? selectedNodeIds[0] : undefined;

  // Elapsed timer
  const [elapsed, setElapsed] = useState<number | null>(null);

  useEffect(() => {
    if (!activeRun) {
      setElapsed(null);
      return;
    }

    const startTime = new Date(activeRun.startedAt).getTime();

    if (activeRun.completedAt) {
      setElapsed(new Date(activeRun.completedAt).getTime() - startTime);
      return;
    }

    // Update every 100ms while running
    const interval = setInterval(() => {
      setElapsed(Date.now() - startTime);
    }, 100);

    return () => clearInterval(interval);
  }, [activeRun]);

  const handleRun = useCallback(
    (trigger: 'runWorkflow' | 'runNode' | 'runFromHere' | 'runUpToHere') => {
      const plan = planExecution({
        workflow: document,
        trigger,
        targetNodeId: selectedNodeId,
      });

      const controller = new AbortController();
      executeMockRun({
        workflow: document,
        plan,
        runCache,
        signal: controller.signal,
      });
    },
    [document, selectedNodeId],
  );

  const handleCancel = useCallback(() => {
    const controller = useRunStore.getState().abortController;
    controller?.abort('User cancelled');
  }, []);

  // Status indicator
  const runStatus = activeRun?.status;
  const StatusIcon =
    runStatus === 'success'
      ? CheckCircle2
      : runStatus === 'error'
        ? XCircle
        : runStatus === 'running'
          ? Loader2
          : null;

  const statusColor =
    runStatus === 'success'
      ? 'text-green-500'
      : runStatus === 'error'
        ? 'text-destructive'
        : runStatus === 'running'
          ? 'text-amber-500'
          : 'text-muted-foreground';

  return (
    <div
      className="flex shrink-0 items-center gap-2 border-t bg-card px-3 py-1.5"
      data-testid="run-toolbar"
    >
      {/* Run Workflow */}
      <Button
        type="button"
        size="sm"
        variant="default"
        className="h-7 gap-1 text-xs"
        disabled={isRunning || document.nodes.length === 0}
        onClick={() => handleRun('runWorkflow')}
        title="Run all nodes in the workflow"
      >
        <Play className="h-3 w-3" />
        Run
      </Button>

      {/* Run Selected Node */}
      <Button
        type="button"
        size="sm"
        variant="outline"
        className="h-7 gap-1 text-xs"
        disabled={isRunning || !hasSelectedNode}
        onClick={() => handleRun('runNode')}
        title={
          hasSelectedNode
            ? 'Run the selected node and its upstream dependencies'
            : 'Select a node to run it'
        }
      >
        <SkipForward className="h-3 w-3" />
        Node
      </Button>

      {/* Run From Here */}
      <Button
        type="button"
        size="sm"
        variant="outline"
        className="h-7 gap-1 text-xs"
        disabled={isRunning || !hasSelectedNode}
        onClick={() => handleRun('runFromHere')}
        title={
          hasSelectedNode
            ? 'Run from the selected node downstream'
            : 'Select a node to run from'
        }
      >
        <ArrowDown className="h-3 w-3" />
        From
      </Button>

      {/* Run Up To Here */}
      <Button
        type="button"
        size="sm"
        variant="outline"
        className="h-7 gap-1 text-xs"
        disabled={isRunning || !hasSelectedNode}
        onClick={() => handleRun('runUpToHere')}
        title={
          hasSelectedNode
            ? 'Run up to and including the selected node'
            : 'Select a node to run up to'
        }
      >
        <ArrowUp className="h-3 w-3" />
        Up To
      </Button>

      {/* Cancel */}
      {isRunning && (
        <Button
          type="button"
          size="sm"
          variant="destructive"
          className="h-7 gap-1 text-xs"
          disabled={!canCancel}
          onClick={handleCancel}
          title="Cancel the current run"
        >
          <Square className="h-3 w-3" />
          Cancel
        </Button>
      )}

      {/* Separator */}
      <div className="mx-1 h-4 w-px bg-border" />

      {/* Mock mode indicator */}
      <div className="flex items-center gap-1" title="Running in mock mode">
        <FlaskConical className="h-3 w-3 text-muted-foreground" />
        <span className="text-[10px] text-muted-foreground">Mock</span>
      </div>

      {/* Status and elapsed */}
      <div className="ml-auto flex items-center gap-2">
        {StatusIcon && (
          <StatusIcon
            className={`h-3.5 w-3.5 ${statusColor} ${runStatus === 'running' ? 'animate-spin' : ''}`}
          />
        )}

        {activeRun && statusCounts.total > 0 && (
          <div className="flex items-center gap-1">
            {statusCounts.success > 0 && (
              <Badge
                variant="secondary"
                className="h-4 px-1 text-[9px] text-green-600"
              >
                {statusCounts.success}/{statusCounts.total}
              </Badge>
            )}
            {statusCounts.error > 0 && (
              <Badge variant="destructive" className="h-4 px-1 text-[9px]">
                {statusCounts.error} err
              </Badge>
            )}
          </div>
        )}

        {elapsed !== null && (
          <div className="flex items-center gap-1 text-[10px] text-muted-foreground">
            <Clock className="h-3 w-3" />
            {formatElapsed(elapsed)}
          </div>
        )}

        {runStatus && !isRunning && (
          <span className={`text-[10px] capitalize ${statusColor}`}>
            {runStatus}
          </span>
        )}
      </div>
    </div>
  );
}
