import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import { selectDocument } from '@/features/workflow/store/workflow-selectors';
import { getTemplate } from '@/features/node-registry/node-registry';
import { cn } from '@/shared/lib/utils';

interface ConnectDialogProps {
  readonly open: boolean;
  readonly onClose: () => void;
  readonly onConnect: (targetNodeId: string, sourcePort: string, targetPort: string) => void;
}

/**
 * ConnectDialog — Searchable dialog for connecting from selected node/port via 'C' shortcut.
 * Design system section 16: Connect from selected node/port via searchable dialog.
 */
export function ConnectDialog({ open, onClose, onConnect }: ConnectDialogProps) {
  const [query, setQuery] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  const document = useWorkflowStore(selectDocument);
  const selectedNodeIds = useWorkflowStore((s) => s.selectedNodeIds);

  const sourceNodeId = selectedNodeIds[0];
  const sourceNode = document.nodes.find((n) => n.id === sourceNodeId);
  const sourceTemplate = sourceNode ? getTemplate(sourceNode.type) : undefined;
  const sourceOutputs = useMemo(
    () => sourceTemplate?.outputs ?? [],
    [sourceTemplate],
  );

  // Find connectable target nodes (exclude self)
  const targetNodes = useMemo(() => {
    if (!sourceNodeId) return [];
    return document.nodes
      .filter((n) => n.id !== sourceNodeId)
      .filter(
        (n) =>
          n.label.toLowerCase().includes(query.toLowerCase()) ||
          n.type.toLowerCase().includes(query.toLowerCase()),
      );
  }, [document.nodes, sourceNodeId, query]);

  useEffect(() => {
    if (open) {
      setQuery('');
      requestAnimationFrame(() => inputRef.current?.focus());
    }
  }, [open]);

  const handleConnect = useCallback(
    (targetId: string, targetPort: string) => {
      const sourcePort = sourceOutputs[0]?.key ?? 'output';
      onConnect(targetId, sourcePort, targetPort);
      onClose();
    },
    [sourceOutputs, onConnect, onClose],
  );

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onClose();
      }
    },
    [onClose],
  );

  if (!open) return null;

  return (
    <div
      data-testid="connect-dialog"
      data-state="open"
      role="dialog"
      aria-label="Connect to node"
      className="fixed inset-0 z-[var(--z-dialog)] flex items-start justify-center pt-[20vh]"
      onClick={onClose}
      onKeyDown={handleKeyDown}
    >
      <div
        className="w-80 rounded-lg border border-border bg-card shadow-lg"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="border-b border-border p-3">
          <div className="mb-1 text-xs text-muted-foreground">
            Connect from: <span className="font-medium text-foreground">{sourceNode?.label ?? 'Unknown'}</span>
          </div>
          <input
            ref={inputRef}
            type="text"
            placeholder="Search target nodes..."
            aria-label="Search target nodes"
            className="w-full bg-transparent text-sm text-foreground placeholder:text-muted-foreground outline-none"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </div>
        <div className="max-h-64 overflow-y-auto p-1">
          {targetNodes.length === 0 && (
            <div className="px-3 py-6 text-center text-sm text-muted-foreground">
              No connectable nodes
            </div>
          )}
          {targetNodes.map((node) => {
            const template = getTemplate(node.type);
            const inputs = template?.inputs ?? [];
            return (
              <div key={node.id} className="px-1 py-0.5">
                <div className="px-2 py-1 text-xs font-medium text-muted-foreground">
                  {node.label}
                </div>
                {inputs.map((port) => (
                  <button
                    key={`${node.id}-${port.key}`}
                    type="button"
                    className={cn(
                      'flex w-full items-center gap-2 rounded-md px-3 py-1.5 text-left text-sm',
                      'text-foreground hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
                      'transition-hover',
                    )}
                    onClick={() => handleConnect(node.id, port.key)}
                  >
                    <span className="font-mono text-xs text-muted-foreground">{port.key}</span>
                    <span>{port.label}</span>
                  </button>
                ))}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
