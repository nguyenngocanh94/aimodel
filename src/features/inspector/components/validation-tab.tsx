import { useMemo } from 'react';
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import { selectDocument } from '@/features/workflow/store/workflow-selectors';
import { validateWorkflow } from '@/features/workflows/domain/graph-validator';
import type { ValidationIssue, ValidationSeverity } from '@/features/workflows/domain/workflow-types';

const severityIcons: Record<ValidationSeverity, typeof XCircle> = {
  error: XCircle,
  warning: AlertTriangle,
  info: Info,
};

const severityColors: Record<ValidationSeverity, string> = {
  error: 'text-destructive',
  warning: 'text-warning',
  info: 'text-muted-foreground',
};

/**
 * ValidationTab - Shows validation issues for the selected node or entire workflow
 *
 * Features per plan section 6.4:
 * - Config schema issues
 * - Missing required inputs
 * - Type mismatch issues
 * - Warnings (coercion, disabled upstream)
 * - Suggested remediations
 * - Grouped by severity
 */
export function ValidationTab({ nodeId }: { readonly nodeId?: string }) {
  const document = useWorkflowStore(selectDocument);
  const setSelectedNodeIds = useWorkflowStore((s) => s.setSelectedNodeIds);
  const setSelectedEdgeId = useWorkflowStore((s) => s.setSelectedEdgeId);

  const allIssues = useMemo(() => validateWorkflow(document), [document]);

  const issues = useMemo(() => {
    if (!nodeId) return allIssues;
    return allIssues.filter(
      (issue) => issue.nodeId === nodeId || !issue.nodeId,
    );
  }, [allIssues, nodeId]);

  const errors = issues.filter((i) => i.severity === 'error');
  const warnings = issues.filter((i) => i.severity === 'warning');
  const infos = issues.filter((i) => i.severity === 'info');

  if (issues.length === 0) {
    return (
      <div className="flex items-center gap-2 text-sm text-muted-foreground py-4">
        <CheckCircle2 className="h-4 w-4 text-success" aria-hidden="true" />
        No blocking issues
      </div>
    );
  }

  const handleIssueClick = (issue: ValidationIssue) => {
    if (issue.nodeId) {
      setSelectedNodeIds([issue.nodeId]);
      setSelectedEdgeId(null);
    } else if (issue.edgeId) {
      setSelectedNodeIds([]);
      setSelectedEdgeId(issue.edgeId);
    }
  };

  return (
    <div className="space-y-3">
      {/* Summary row */}
      <div className="flex items-center gap-3 pb-3 border-b border-border mb-3 text-xs">
        {errors.length > 0 && (
          <span className="text-destructive font-medium">
            {errors.length} error{errors.length !== 1 ? 's' : ''}
          </span>
        )}
        {warnings.length > 0 && (
          <span className="text-warning font-medium">
            {warnings.length} warning{warnings.length !== 1 ? 's' : ''}
          </span>
        )}
        {infos.length > 0 && (
          <span className="text-muted-foreground font-medium">
            {infos.length} info
          </span>
        )}
      </div>

      {/* Issue groups */}
      {[
        { label: 'Errors', items: errors },
        { label: 'Warnings', items: warnings },
        { label: 'Info', items: infos },
      ]
        .filter((group) => group.items.length > 0)
        .map((group) => (
          <div key={group.label} className="space-y-1">
            <h4 className="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
              {group.label}
            </h4>
            {group.items.map((issue) => {
              const Icon = severityIcons[issue.severity];
              return (
                <button
                  key={issue.id}
                  type="button"
                  onClick={() => handleIssueClick(issue)}
                  className="w-full text-left flex items-start gap-2 rounded-md border border-border p-2 transition-colors hover:bg-accent/50"
                  data-testid={`validation-item-${issue.code}`}
                >
                  <Icon
                    className={`h-3.5 w-3.5 mt-0.5 shrink-0 ${severityColors[issue.severity]}`}
                  />
                  <div className="min-w-0 flex-1">
                    <p className="text-xs">{issue.message}</p>
                    {issue.suggestion && (
                      <p className="text-[10px] text-muted-foreground mt-0.5">
                        {issue.suggestion}
                      </p>
                    )}
                    <div className="flex items-center gap-1.5 mt-0.5">
                      <span className="font-mono text-[10px] text-muted-foreground">
                        {issue.code}
                      </span>
                      {issue.nodeId && (
                        <span className="text-primary text-[10px] hover:underline cursor-pointer">
                          {issue.nodeId}
                        </span>
                      )}
                    </div>
                  </div>
                </button>
              );
            })}
          </div>
        ))}
    </div>
  );
}
