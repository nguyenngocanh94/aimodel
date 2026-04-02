import { useMemo } from 'react';
import { AlertCircle, AlertTriangle, Info } from 'lucide-react';
import { Badge } from '@/shared/ui/badge';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import { selectDocument } from '@/features/workflow/store/workflow-selectors';
import { validateWorkflow } from '@/features/workflows/domain/graph-validator';
import type { ValidationIssue, ValidationSeverity } from '@/features/workflows/domain/workflow-types';

const severityIcons: Record<ValidationSeverity, typeof AlertCircle> = {
  error: AlertCircle,
  warning: AlertTriangle,
  info: Info,
};

const severityColors: Record<ValidationSeverity, string> = {
  error: 'text-destructive',
  warning: 'text-amber-500',
  info: 'text-blue-500',
};

const severityBadgeVariant: Record<ValidationSeverity, 'destructive' | 'secondary' | 'default'> = {
  error: 'destructive',
  warning: 'secondary',
  info: 'default',
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
      <div className="text-center py-6">
        <p className="text-sm text-muted-foreground">
          No validation issues found.
        </p>
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
      {/* Summary badges */}
      <div className="flex items-center gap-2">
        {errors.length > 0 && (
          <Badge variant="destructive" className="text-xs">
            {errors.length} error{errors.length !== 1 ? 's' : ''}
          </Badge>
        )}
        {warnings.length > 0 && (
          <Badge variant="secondary" className="text-xs">
            {warnings.length} warning{warnings.length !== 1 ? 's' : ''}
          </Badge>
        )}
        {infos.length > 0 && (
          <Badge variant="default" className="text-xs">
            {infos.length} info
          </Badge>
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
                  className="w-full text-left flex items-start gap-2 rounded-md border p-2 transition-colors hover:bg-accent/50"
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
                    <div className="flex items-center gap-1 mt-0.5">
                      <Badge
                        variant={severityBadgeVariant[issue.severity]}
                        className="h-4 px-1 text-[9px]"
                      >
                        {issue.code}
                      </Badge>
                      {issue.nodeId && (
                        <span className="text-[9px] text-muted-foreground font-mono">
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
