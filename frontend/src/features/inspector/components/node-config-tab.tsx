import { useCallback, useEffect, useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { RotateCcw } from 'lucide-react';
import { Button } from '@/shared/ui/button';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import { getTemplate, type NodeTemplate } from '@/features/node-registry/node-registry';
import type { WorkflowNode } from '@/features/workflows/domain/workflow-types';
import { ZodFormFields } from './zod-form-fields';

interface NodeConfigTabProps {
  readonly node: WorkflowNode;
}

/**
 * NodeConfigTab - Config form with React Hook Form + Zod validation
 *
 * Features per plan section 6.4:
 * - Node title editing
 * - Typed config form generated from Zod schema
 * - Real-time validation
 * - Reset to defaults
 * - Fixture selector
 */
export function NodeConfigTab({ node }: NodeConfigTabProps) {
  const commitAuthoring = useWorkflowStore((s) => s.commitAuthoring);
  const template = useMemo(() => getTemplate(node.type), [node.type]);

  if (!template) {
    return (
      <p className="text-xs text-muted-foreground p-3">
        Unknown node type: {node.type}
      </p>
    );
  }

  return (
    <NodeConfigForm node={node} template={template as NodeTemplate<Record<string, unknown>>} commitAuthoring={commitAuthoring} />
  );
}

interface NodeConfigFormProps {
  readonly node: WorkflowNode;
  readonly template: NodeTemplate<Record<string, unknown>>;
  readonly commitAuthoring: (recipe: (doc: import('@/features/workflows/domain/workflow-types').WorkflowDocument) => import('@/features/workflows/domain/workflow-types').WorkflowDocument) => void;
}

function NodeConfigForm({ node, template, commitAuthoring }: NodeConfigFormProps) {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isDirty },
  } = useForm<Record<string, unknown>>({
    resolver: zodResolver(template.configSchema),
    defaultValues: node.config as Record<string, unknown>,
    mode: 'onChange',
  });

  // Reset form when node changes
  useEffect(() => {
    reset(node.config as Record<string, unknown>);
  }, [node.id, node.config, reset]);

  const onSubmit = useCallback(
    (data: Record<string, unknown>) => {
      commitAuthoring((doc) => ({
        ...doc,
        nodes: doc.nodes.map((n) =>
          n.id === node.id ? ({ ...n, config: data } as typeof n) : n,
        ),
      }));
    },
    [commitAuthoring, node.id],
  );

  const handleResetToDefaults = useCallback(() => {
    const defaults = template.defaultConfig as Record<string, unknown>;
    reset(defaults);
    commitAuthoring((doc) => ({
      ...doc,
      nodes: doc.nodes.map((n) =>
        n.id === node.id ? ({ ...n, config: defaults } as typeof n) : n,
      ),
    }));
  }, [template.defaultConfig, reset, commitAuthoring, node.id]);

  const handleApplyFixture = useCallback(
    (fixtureId: string) => {
      const fixture = template.fixtures.find((f) => f.id === fixtureId);
      if (!fixture) return;
      const merged = { ...template.defaultConfig, ...fixture.config };
      reset(merged as Record<string, unknown>);
      commitAuthoring((doc) => ({
        ...doc,
        nodes: doc.nodes.map((n) =>
          n.id === node.id ? ({ ...n, config: merged } as typeof n) : n,
        ),
      }));
    },
    [template, reset, commitAuthoring, node.id],
  );

  const handleTitleChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const newLabel = e.target.value;
      commitAuthoring((doc) => ({
        ...doc,
        nodes: doc.nodes.map((n) =>
          n.id === node.id ? { ...n, label: newLabel } : n,
        ),
      }));
    },
    [commitAuthoring, node.id],
  );

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      {/* Node title */}
      <div>
        <label htmlFor="node-title" className="block text-[11px] font-medium text-muted-foreground mb-1">
          Title
        </label>
        <input
          id="node-title"
          type="text"
          value={node.label}
          onChange={handleTitleChange}
          className="h-8 w-full rounded-md border border-input bg-muted px-2 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        />
      </div>

      {/* Config fields from Zod schema */}
      <ZodFormFields
        schema={template.configSchema}
        register={register}
        errors={errors}
      />

      {/* Actions */}
      <div className="flex items-center gap-2">
        {isDirty && (
          <Button type="submit" size="sm" className="h-7 text-xs">
            Apply
          </Button>
        )}
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="h-7 text-xs"
          onClick={handleResetToDefaults}
          title="Reset to defaults"
        >
          <RotateCcw className="h-3 w-3 mr-1" />
          Defaults
        </Button>
      </div>

      {/* Fixture selector */}
      {template.fixtures.length > 0 && (
        <div>
          <label className="block text-[11px] font-medium text-muted-foreground mb-1">
            Fixtures
          </label>
          <div className="flex flex-wrap gap-1">
            {template.fixtures.map((fixture) => (
              <Button
                key={fixture.id}
                type="button"
                variant="outline"
                size="sm"
                className="h-6 text-[10px] px-2"
                onClick={() => handleApplyFixture(fixture.id)}
              >
                {fixture.label}
              </Button>
            ))}
          </div>
        </div>
      )}
    </form>
  );
}
