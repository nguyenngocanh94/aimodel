/**
 * RunNodeInspector - AiModel-638
 * Side panel showing execution details for a selected node.
 * Displays status, timestamps, config, inputs, and outputs.
 */

import { useState } from 'react';
import { X, Clock, Database, CheckCircle2, XCircle, Loader2, Clock as ClockIcon, SkipForward, StopCircle, PauseCircle, HelpCircle, Settings, ArrowDownToLine, ArrowUpFromLine } from 'lucide-react';
import { Button } from '@/shared/ui/button';
import { PayloadViewer } from '@/features/data-inspector/components/payload-viewer';
import { getTemplate } from '@/features/node-registry/node-registry';
import type { NodeRunRecord, WorkflowNode } from '@/features/workflows/domain/workflow-types';

interface RunNodeInspectorProps {
  readonly node: WorkflowNode | null;
  readonly record: NodeRunRecord | null;
  readonly onClose: () => void;
}

type InspectorTab = 'execution' | 'config';

const statusConfig: Record<
  NodeRunRecord['status'] | 'idle',
  { icon: React.ReactNode; className: string; bgClass: string; label: string }
> = {
  idle: {
    icon: <HelpCircle className="h-4 w-4" />,
    className: 'text-muted-foreground',
    bgClass: 'bg-muted',
    label: 'Not executed',
  },
  pending: {
    icon: <ClockIcon className="h-4 w-4" />,
    className: 'text-gray-600',
    bgClass: 'bg-gray-100 dark:bg-gray-800',
    label: 'Pending',
  },
  running: {
    icon: <Loader2 className="h-4 w-4 animate-spin" />,
    className: 'text-blue-600',
    bgClass: 'bg-blue-50 dark:bg-blue-900/20',
    label: 'Running',
  },
  success: {
    icon: <CheckCircle2 className="h-4 w-4" />,
    className: 'text-green-600',
    bgClass: 'bg-green-50 dark:bg-green-900/20',
    label: 'Success',
  },
  error: {
    icon: <XCircle className="h-4 w-4" />,
    className: 'text-red-600',
    bgClass: 'bg-red-50 dark:bg-red-900/20',
    label: 'Error',
  },
  skipped: {
    icon: <SkipForward className="h-4 w-4" />,
    className: 'text-gray-500',
    bgClass: 'bg-gray-50 dark:bg-gray-900/20',
    label: 'Skipped',
  },
  cancelled: {
    icon: <StopCircle className="h-4 w-4" />,
    className: 'text-yellow-600',
    bgClass: 'bg-yellow-50 dark:bg-yellow-900/20',
    label: 'Cancelled',
  },
  awaitingReview: {
    icon: <PauseCircle className="h-4 w-4" />,
    className: 'text-orange-600',
    bgClass: 'bg-orange-50 dark:bg-orange-900/20',
    label: 'Awaiting Review',
  },
};

function formatDuration(durationMs?: number): string {
  if (durationMs === undefined) return '-';
  if (durationMs < 1000) return `${durationMs}ms`;
  const seconds = Math.floor(durationMs / 1000);
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;
  return `${minutes}m ${remainingSeconds}s`;
}

function formatTimestamp(dateStr?: string): string {
  if (!dateStr) return '-';
  return new Date(dateStr).toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

export function RunNodeInspector({ node, record, onClose }: RunNodeInspectorProps) {
  const [tab, setTab] = useState<InspectorTab>('execution');

  // No node selected - show placeholder
  if (!node) {
    return (
      <div className="h-full flex flex-col">
        <div className="flex items-center justify-between px-4 py-3 border-b">
          <h3 className="font-semibold text-sm">Node Details</h3>
        </div>
        <div className="flex-1 flex flex-col items-center justify-center p-6 text-center">
          <div className="w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
            <HelpCircle className="h-6 w-6 text-muted-foreground" />
          </div>
          <p className="text-sm text-muted-foreground">
            Click a node on the canvas to view its execution details
          </p>
        </div>
      </div>
    );
  }

  const status = record ? statusConfig[record.status] : statusConfig.idle;
  const inputKeys = Object.keys(record?.inputPayloads ?? {});
  const outputKeys = Object.keys(record?.outputPayloads ?? {});
  const template = getTemplate(node.type);

  return (
    <div className="h-full flex flex-col">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b shrink-0">
        <div className="min-w-0 flex-1">
          <h3 className="font-semibold text-sm truncate">{node.label}</h3>
          <p className="text-xs text-muted-foreground font-mono truncate">
            {template?.category?.toUpperCase() ?? 'UTILITY'} · {node.type}
          </p>
        </div>
        <Button variant="ghost" size="sm" className="h-8 w-8 p-0 shrink-0" onClick={onClose}>
          <X className="h-4 w-4" />
        </Button>
      </div>

      {/* Tab bar */}
      <div className="flex border-b shrink-0">
        <button
          className={`flex-1 px-3 py-2 text-xs font-medium transition-colors ${
            tab === 'execution'
              ? 'text-foreground border-b-2 border-primary'
              : 'text-muted-foreground hover:text-foreground'
          }`}
          onClick={() => setTab('execution')}
        >
          Execution
        </button>
        <button
          className={`flex-1 px-3 py-2 text-xs font-medium transition-colors ${
            tab === 'config'
              ? 'text-foreground border-b-2 border-primary'
              : 'text-muted-foreground hover:text-foreground'
          }`}
          onClick={() => setTab('config')}
        >
          Config
        </button>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-auto p-4 space-y-4">
        {tab === 'execution' && (
          <>
            {/* Status section */}
            <div className={`rounded-lg border p-3 ${status.bgClass}`}>
              <div className="flex items-center gap-2 mb-2">
                <span className={status.className}>{status.icon}</span>
                <span className={`font-medium text-sm ${status.className}`}>{status.label}</span>
              </div>

              {record && (
                <div className="space-y-1.5 text-xs">
                  <div className="flex items-center gap-2 text-muted-foreground">
                    <Clock className="h-3 w-3 shrink-0" />
                    <span>Duration: {formatDuration(record.durationMs)}</span>
                  </div>
                  <div className="flex items-center gap-2 text-muted-foreground">
                    <ClockIcon className="h-3 w-3 shrink-0" />
                    <span>
                      {formatTimestamp(record.startedAt)} → {formatTimestamp(record.completedAt)}
                    </span>
                  </div>
                  {record.usedCache && (
                    <div className="flex items-center gap-2 text-primary">
                      <Database className="h-3 w-3 shrink-0" />
                      <span>Cache hit</span>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Error message */}
            {record?.errorMessage && (
              <div className="rounded-lg border border-red-200 bg-red-50 dark:bg-red-900/10 dark:border-red-800 p-3">
                <div className="flex items-start gap-2">
                  <XCircle className="h-4 w-4 text-red-500 mt-0.5 shrink-0" />
                  <div>
                    <p className="text-sm font-medium text-red-700 dark:text-red-400">Error</p>
                    <p className="text-sm text-red-600 dark:text-red-300">{record.errorMessage}</p>
                  </div>
                </div>
              </div>
            )}

            {/* Skip reason */}
            {record?.skipReason && (
              <div className="rounded-lg border border-gray-200 bg-gray-50 dark:bg-gray-900/10 dark:border-gray-700 p-3">
                <div className="flex items-start gap-2">
                  <SkipForward className="h-4 w-4 text-gray-400 mt-0.5 shrink-0" />
                  <div>
                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">Skipped</p>
                    <p className="text-sm text-gray-500">{record.skipReason}</p>
                  </div>
                </div>
              </div>
            )}

            {/* Node ID */}
            <div className="text-xs text-muted-foreground">
              <span className="font-medium">Node ID:</span>{' '}
              <span className="font-mono">{node.id}</span>
            </div>

            {/* Inputs section */}
            {inputKeys.length > 0 && (
              <div className="space-y-2">
                <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                  <ArrowDownToLine className="h-3 w-3" />
                  Inputs ({inputKeys.length})
                </h4>
                <div className="space-y-2">
                  {inputKeys.map((key) => (
                    <PayloadViewer
                      key={`input-${key}`}
                      portKey={key}
                      payload={record!.inputPayloads![key]}
                      source="lastRun"
                      showProducer={true}
                    />
                  ))}
                </div>
              </div>
            )}

            {/* Outputs section */}
            {outputKeys.length > 0 && (
              <div className="space-y-2">
                <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                  <ArrowUpFromLine className="h-3 w-3" />
                  Outputs ({outputKeys.length})
                </h4>
                <div className="space-y-2">
                  {outputKeys.map((key) => (
                    <PayloadViewer
                      key={`output-${key}`}
                      portKey={key}
                      payload={record!.outputPayloads![key]}
                      source="lastRun"
                      showProducer={false}
                    />
                  ))}
                </div>
              </div>
            )}

            {/* No data message */}
            {inputKeys.length === 0 && outputKeys.length === 0 && record && (
              <p className="text-sm text-muted-foreground italic">No payload data available for this node</p>
            )}

            {/* Not executed message */}
            {!record && (
              <p className="text-sm text-muted-foreground italic">This node was not executed in this run</p>
            )}
          </>
        )}

        {tab === 'config' && (
          <>
            {/* Node info */}
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <Settings className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium">Node Configuration</span>
              </div>
            </div>

            {/* Port definitions */}
            {template && (
              <div className="space-y-3">
                {template.inputs.length > 0 && (
                  <div className="space-y-1">
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                      Input Ports
                    </p>
                    <div className="space-y-1">
                      {template.inputs.map((p) => (
                        <div key={p.key} className="flex items-center gap-2 text-xs">
                          <span className="font-mono text-foreground">{p.key}</span>
                          <span className="text-muted-foreground">({p.dataType})</span>
                          {p.required && (
                            <span className="text-[9px] text-destructive">required</span>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>
                )}
                {template.outputs.length > 0 && (
                  <div className="space-y-1">
                    <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                      Output Ports
                    </p>
                    <div className="space-y-1">
                      {template.outputs.map((p) => (
                        <div key={p.key} className="flex items-center gap-2 text-xs">
                          <span className="font-mono text-foreground">{p.key}</span>
                          <span className="text-muted-foreground">({p.dataType})</span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* Config fields */}
            <div className="space-y-3">
              <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                Configuration Values
              </p>
              {Object.entries(node.config).map(([key, value]) => (
                <div key={key} className="space-y-1">
                  <label className="text-xs text-muted-foreground">{key}</label>
                  <div className="rounded-md border bg-muted/50 p-2">
                    <pre className="text-xs font-mono overflow-auto whitespace-pre-wrap break-all">
                      {typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value)}
                    </pre>
                  </div>
                </div>
              ))}
              {Object.keys(node.config).length === 0 && (
                <p className="text-sm text-muted-foreground italic">No configuration</p>
              )}
            </div>
          </>
        )}
      </div>
    </div>
  );
}
