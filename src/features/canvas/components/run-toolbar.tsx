/**
 * RunToolbar - Three-zone layout per design system section 13
 * Left: workflow name + dirty indicator
 * Center: segmented run actions
 * Right: status chip + timer
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Play,
  Square,
  SkipForward,
  ArrowDown,
  Clock,
} from 'lucide-react';
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
      className="sticky top-0 z-10 flex h-12 items-center justify-between gap-3 border-b border-border/80 bg-background/95 px-3 text-foreground backdrop-blur supports-[backdrop-filter]:bg-background/80"
      data-testid="run-toolbar"
      aria-label="Run toolbar"
    >
      {/* Left zone: workflow name + dirty indicator */}
      <div className="flex min-w-0 items-center gap-2">
        <span className="truncate text-[13px] font-medium">
          {document.name || 'Untitled Workflow'}
        </span>
        {isDirty && (
          <span
            className="rounded-sm border border-warning/30 bg-warning/10 px-1.5 py-0.5 text-[10px] font-medium text-warning"
            data-testid="workflow-dirty-indicator"
            title="Unsaved local snapshot"
          >
            Unsaved
          </span>
        )}
      </div>

      {/* Center zone: segmented run actions */}
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

      {/* Right zone: status chip + timer */}
      <div className="flex items-center gap-2">
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
