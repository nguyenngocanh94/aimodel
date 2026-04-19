import { useCallback, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { RotateCcw } from 'lucide-react';
import { Button } from '@/shared/ui/button';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import {
  useResolvedNodeTemplate,
  type NodeTemplate,
} from '@/features/node-registry/node-registry';
import type { WorkflowNode, WorkflowDocument } from '@/features/workflows/domain/workflow-types';
import type { JsonSchemaNode } from '@/features/node-registry/manifest/types';
import { JsonSchemaForm } from './JsonSchemaForm';
import { ZodFormFields } from './zod-form-fields';

// Shared commitAuthoring recipe type
type CommitAuthoring = (
  recipe: (doc: WorkflowDocument) => WorkflowDocument,
) => void;

interface NodeConfigTabProps {
  readonly node: WorkflowNode;
}

/**
 * NodeConfigTab - Config form with manifest-driven JsonSchemaForm (NM3) or
 * legacy Zod form for non-pilot templates.
 *
 * Rendering priority:
 * 1. Manifest entry with configSchema → JsonSchemaForm (manifest-driven)
 * 2. Template configSchema (Zod) → legacy ZodFormFields
 * 3. Neither available yet → loading skeleton ("Đang tải schema…")
 */
export function NodeConfigTab({ node }: NodeConfigTabProps) {
  const commitAuthoring = useWorkflowStore((s) => s.commitAuthoring) as CommitAuthoring;
  const { template, manifestConfigSchema, manifestEntry, defaultConfig } =
    useResolvedNodeTemplate(node.type);

  if (!template) {
    return (
      <p className="text-xs text-muted-foreground p-3">
        Unknown node type: {node.type}
      </p>
    );
  }

  // Prefer manifest-driven form when manifest has a schema for this type
  if (manifestConfigSchema) {
    return (
      <ManifestConfigForm
        node={node}
        schema={manifestConfigSchema}
        defaultConfig={defaultConfig}
        commitAuthoring={commitAuthoring}
        template={template}
      />
    );
  }

  // Fall back to legacy Zod form for non-pilot templates
  if (template.configSchema) {
    return (
      <LegacyZodConfigForm
        node={node}
        template={template as NodeTemplate<Record<string, unknown>> & {
          readonly configSchema: NonNullable<NodeTemplate<Record<string, unknown>>['configSchema']>;
        }}
        commitAuthoring={commitAuthoring}
      />
    );
  }

  // Manifest is loading (no local schema, no manifest entry yet) — show skeleton
  if (!manifestEntry) {
    return (
      <p className="text-sm text-muted-foreground p-3">Đang tải schema…</p>
    );
  }

  // Manifest loaded but node has no config schema at all
  return (
    <p className="text-xs text-muted-foreground p-3">
      No config schema available for this node type.
    </p>
  );
}

// ────────────────────────────────────────────────────────────────
// Manifest-driven config form (NM3+)
// ────────────────────────────────────────────────────────────────

interface ManifestConfigFormProps {
  readonly node: WorkflowNode;
  readonly schema: JsonSchemaNode;
  readonly defaultConfig: Readonly<Record<string, unknown>>;
  readonly commitAuthoring: CommitAuthoring;
  readonly template: {
    readonly fixtures: readonly {
      id: string;
      label: string;
      config?: Partial<Record<string, unknown>>;
    }[];
  };
}

function ManifestConfigForm({
  node,
  schema,
  defaultConfig,
  commitAuthoring,
  template,
}: ManifestConfigFormProps) {
  const handleChange = useCallback(
    (next: Record<string, unknown>) => {
      commitAuthoring((doc) => ({
        ...doc,
        nodes: doc.nodes.map((n) =>
          n.id === node.id ? ({ ...n, config: next } as typeof n) : n,
        ),
      }));
    },
    [commitAuthoring, node.id],
  );

  const handleResetToDefaults = useCallback(() => {
    commitAuthoring((doc) => ({
      ...doc,
      nodes: doc.nodes.map((n) =>
        n.id === node.id
          ? ({ ...n, config: defaultConfig } as typeof n)
          : n,
      ),
    }));
  }, [defaultConfig, commitAuthoring, node.id]);

  const handleApplyFixture = useCallback(
    (fixtureId: string) => {
      const fixture = template.fixtures.find((f) => f.id === fixtureId);
      if (!fixture) return;
      const merged = { ...defaultConfig, ...fixture.config };
      commitAuthoring((doc) => ({
        ...doc,
        nodes: doc.nodes.map((n) =>
          n.id === node.id ? ({ ...n, config: merged } as typeof n) : n,
        ),
      }));
    },
    [template, defaultConfig, commitAuthoring, node.id],
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
    <div className="space-y-4">
      {/* Node title */}
      <div>
        <label
          htmlFor="node-title"
          className="block text-[11px] font-medium text-muted-foreground mb-1"
        >
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

      {/* Manifest-driven config form */}
      <JsonSchemaForm
        schema={schema}
        value={node.config as Record<string, unknown>}
        onChange={handleChange}
      />

      {/* Actions */}
      <div className="flex items-center gap-2">
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
    </div>
  );
}

// ────────────────────────────────────────────────────────────────
// Legacy Zod-based config form (non-pilot templates)
// ────────────────────────────────────────────────────────────────

interface LegacyZodConfigFormProps {
  readonly node: WorkflowNode;
  readonly template: NodeTemplate<Record<string, unknown>> & {
    readonly configSchema: NonNullable<NodeTemplate<Record<string, unknown>>['configSchema']>;
  };
  readonly commitAuthoring: CommitAuthoring;
}

function LegacyZodConfigForm({ node, template, commitAuthoring }: LegacyZodConfigFormProps) {
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
    const defaults = (template.defaultConfig ?? {}) as Record<string, unknown>;
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
      const merged = { ...(template.defaultConfig ?? {}), ...fixture.config };
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
        <label
          htmlFor="node-title"
          className="block text-[11px] font-medium text-muted-foreground mb-1"
        >
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
