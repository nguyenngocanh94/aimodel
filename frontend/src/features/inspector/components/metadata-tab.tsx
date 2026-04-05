import { useCallback } from 'react';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import type { WorkflowNode } from '@/features/workflows/domain/workflow-types';

interface MetadataTabProps {
  readonly node: WorkflowNode;
}

/**
 * MetadataTab - Shows node metadata per plan section 6.4
 *
 * Fields: node id, type, createdAt, updatedAt, notes
 */
export function MetadataTab({ node }: MetadataTabProps) {
  const commitAuthoring = useWorkflowStore((s) => s.commitAuthoring);

  const handleNotesChange = useCallback(
    (e: React.ChangeEvent<HTMLTextAreaElement>) => {
      const notes = e.target.value;
      commitAuthoring((doc) => ({
        ...doc,
        nodes: doc.nodes.map((n) =>
          n.id === node.id ? { ...n, notes } : n,
        ),
      }));
    },
    [commitAuthoring, node.id],
  );

  return (
    <div className="space-y-3">
      <MetadataRow label="ID" value={node.id} mono />
      <MetadataRow label="Type" value={node.type} />
      <MetadataRow
        label="Position"
        value={`(${Math.round(node.position.x)}, ${Math.round(node.position.y)})`}
      />
      <MetadataRow
        label="Disabled"
        value={node.disabled ? 'Yes' : 'No'}
      />

      {/* Notes */}
      <div>
        <label
          htmlFor="node-notes"
          className="block text-xs font-medium text-foreground mb-1"
        >
          Notes
        </label>
        <textarea
          id="node-notes"
          rows={3}
          value={node.notes ?? ''}
          onChange={handleNotesChange}
          placeholder="Add notes about this node..."
          className="w-full rounded-md border bg-background px-2 py-1 text-sm resize-y focus:outline-none focus:ring-1 focus:ring-ring placeholder:text-muted-foreground"
        />
      </div>
    </div>
  );
}

function MetadataRow({
  label,
  value,
  mono = false,
}: {
  readonly label: string;
  readonly value: string;
  readonly mono?: boolean;
}) {
  return (
    <div>
      <span className="block text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
        {label}
      </span>
      <span
        className={`text-sm text-foreground ${mono ? 'font-mono text-xs' : ''}`}
      >
        {value}
      </span>
    </div>
  );
}
