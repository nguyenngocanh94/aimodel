/**
 * RunToolbar - Three-zone layout per design system section 13
 * Left: workflow name + dirty indicator
 * Center: segmented run actions
 * Right: status chip + timer
 */

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Play,
  Square,
  SkipForward,
  ArrowDown,
  Clock,
} from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/shared/ui/button';
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
import { useTriggerRun, useCancelRun } from '@/shared/api/mutations';
import { connectToRunStream } from '@/shared/api/sse';
import { useParams } from '@tanstack/react-router';

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
  const isDirty = useWorkflowStore((s) => s.dirty);
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

  const { workflowId } = useParams({ from: '/workflows/$workflowId' });
  const triggerRun = useTriggerRun(workflowId);
  const cancelRun = useCancelRun(activeRun?.id ?? '');
  const sseCleanupRef = useRef<(() => void) | null>(null);

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

    const interval = setInterval(() => {
      setElapsed(Date.now() - startTime);
    }, 100);

    return () => clearInterval(interval);
  }, [activeRun]);

  // Clean up SSE on unmount
  useEffect(() => {
    return () => {
      sseCleanupRef.current?.();
    };
  }, []);

  const handleRun = useCallback(
    async (trigger: 'runWorkflow' | 'runNode' | 'runFromHere' | 'runUpToHere') => {
      try {
        const result = await triggerRun.mutateAsync({
          trigger,
          targetNodeId: selectedNodeId,
        });

        const run = (result as { data?: { id?: string } })?.data;
        if (!run?.id) return;

        toast.success('Run started');

        // Connect SSE for live updates
        sseCleanupRef.current?.();
        const { cleanup } = connectToRunStream(run.id, {
          onCatchup: (data) => {
            // Hydrate run store with catchup data
            useRunStore.getState().hydrateCatchup?.(data);
          },
          onRunStarted: (data) => {
            useRunStore.getState().startRun?.({
              id: run.id,
              plannedNodeIds: data.plannedNodeIds,
            });
          },
          onNodeStatus: (data) => {
            useRunStore.getState().updateNodeRecord?.({
              nodeId: data.nodeId,
              status: data.status as 'pending' | 'running' | 'success' | 'error' | 'skipped' | 'cancelled',
              outputPayloads: data.outputPayloads as Record<string, unknown> | undefined,
              durationMs: data.durationMs,
              errorMessage: data.errorMessage,
              usedCache: data.usedCache,
            });
          },
          onRunCompleted: (data) => {
            useRunStore.getState().completeRun?.({
              status: data.status,
              terminationReason: data.terminationReason,
            });
            cleanup();
            sseCleanupRef.current = null;

            if (data.status === 'success') {
              toast.success('Run completed successfully');
            } else if (data.status === 'error') {
              toast.error('Run failed');
            } else if (data.status === 'cancelled') {
              toast.info('Run cancelled');
            }
          },
          onError: () => {
            toast.error('Lost connection to run stream');
          },
        });
        sseCleanupRef.current = cleanup;
      } catch {
        toast.error('Failed to start run');
      }
    },
    [triggerRun, selectedNodeId],
  );

  const handleCancel = useCallback(async () => {
    try {
      await cancelRun.mutateAsync();
      sseCleanupRef.current?.();
      sseCleanupRef.current = null;
      toast.info('Run cancellation requested');
    } catch {
      toast.error('Failed to cancel run');
    }
  }, [cancelRun]);

  // Derive status chip text
  const runStatus = activeRun?.status;
  const statusText = isRunning
    ? `Running ${statusCounts.success}/${statusCounts.total}`
    : runStatus === 'success'
      ? `Done ${statusCounts.success}/${statusCounts.total}`
      : runStatus === 'error'
        ? `Failed ${statusCounts.error} err`
        : 'Idle';

  return (
    <header
      className="flex h-12 shrink-0 items-center justify-between gap-3 border-b border-border/80 bg-background/95 px-3 text-foreground backdrop-blur supports-[backdrop-filter]:bg-background/80"
      data-testid="run-toolbar"
      aria-label="Run toolbar"
    >
      {/* Left zone: run actions only (name is in app-header) */}
      <div className="flex items-center gap-2">
        <Button
          type="button"
          size="sm"
          variant="default"
          className="h-8 gap-1.5 px-3 text-sm font-medium"
          disabled={isRunning || document.nodes.length === 0}
          onClick={() => handleRun('runWorkflow')}
          title="Run the full workflow"
          data-testid="run-btn-workflow"
        >
          <Play className="h-3.5 w-3.5" aria-hidden="true" />
          Run Workflow
        </Button>

        <Button
          type="button"
          size="sm"
          variant="secondary"
          className="h-8 gap-1.5 border border-input px-3 text-sm"
          disabled={isRunning || !hasSelectedNode}
          onClick={() => handleRun('runNode')}
          title="Run the selected node"
          data-testid="run-btn-node"
        >
          <SkipForward className="h-3.5 w-3.5" aria-hidden="true" />
          Run Node
        </Button>

        <Button
          type="button"
          size="sm"
          variant="secondary"
          className="h-8 gap-1.5 border border-input px-3 text-sm"
          disabled={isRunning || !hasSelectedNode}
          onClick={() => handleRun('runFromHere')}
          title="Run downstream from current selection"
          data-testid="run-btn-from-here"
        >
          <ArrowDown className="h-3.5 w-3.5" aria-hidden="true" />
          Run From Here
        </Button>

        {isRunning && (
          <Button
            type="button"
            size="sm"
            variant="destructive"
            className="h-8 gap-1.5 px-3 text-sm"
            disabled={!canCancel}
            onClick={handleCancel}
            title="Stop active execution"
            data-testid="run-btn-cancel"
          >
            <Square className="h-3.5 w-3.5" aria-hidden="true" />
            Cancel
          </Button>
        )}
      </div>

      {/* Right zone: dirty indicator + status + timer */}
      <div className="flex items-center gap-3">
        {isDirty && (
          <span
            className="rounded-sm border border-warning/30 bg-warning/10 px-1.5 py-0.5 text-[10px] font-medium text-warning"
            data-testid="workflow-dirty-indicator"
            title="Unsaved changes"
          >
            Modified
          </span>
        )}
        
        <span
          className="inline-flex items-center gap-1.5 rounded-md border border-border bg-muted px-2 py-1 font-mono text-[11px] text-muted-foreground"
          data-testid="run-status-chip"
        >
          {/* Status dot - 6px colored indicator */}
          <span
            className={`h-1.5 w-1.5 rounded-full ${
              !runStatus
                ? 'bg-muted-foreground/50'
                : runStatus === 'running'
                  ? 'bg-signal animate-status-dot'
                  : runStatus === 'success'
                    ? 'bg-success'
                    : 'bg-destructive'
            }`}
            aria-hidden="true"
          />
          {statusText}
        </span>

        {elapsed !== null && (
          <span className="inline-flex items-center gap-1 font-mono text-[11px] text-muted-foreground tabular-nums">
            <Clock className="h-3 w-3" aria-hidden="true" />
            {formatElapsed(elapsed)}
          </span>
        )}

        {activeRun && (
          <span className="font-mono text-[10px] text-muted-foreground/60">
            {activeRun.id.slice(0, 8)}
          </span>
        )}
      </div>
    </header>
  );
}
