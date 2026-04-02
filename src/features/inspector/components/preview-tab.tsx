import { useMemo } from 'react';
import { Eye, FlaskConical } from 'lucide-react';
import { Badge } from '@/shared/ui/badge';
import { Button } from '@/shared/ui/button';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import { selectDocument } from '@/features/workflow/store/workflow-selectors';
import { computeAllPreviews } from '@/features/workflows/domain/preview-engine';
import { getTemplate } from '@/features/node-registry/node-registry';
import { StoryboardPlayer } from '@/features/preview/components/storyboard-player';
import type { VideoAssetPayload } from '@/features/node-registry/templates/video-composer';
import type { WorkflowNode, PortPayload } from '@/features/workflows/domain/workflow-types';

interface PreviewTabProps {
  readonly node: WorkflowNode;
}

/**
 * PreviewTab - Shows preview output for the selected node
 *
 * Features per plan sections 6.4 and 10.3:
 * - Preview output display
 * - Fixture selector
 * - Source labeling (preview vs lastRun)
 */
export function PreviewTab({ node }: PreviewTabProps) {
  const document = useWorkflowStore(selectDocument);
  const commitAuthoring = useWorkflowStore((s) => s.commitAuthoring);

  const template = useMemo(() => getTemplate(node.type), [node.type]);

  const allPreviews = useMemo(
    () => computeAllPreviews(document),
    [document],
  );

  const nodePreview = allPreviews.get(node.id);

  if (!template) {
    return (
      <p className="text-xs text-muted-foreground p-3">
        Unknown node type: {node.type}
      </p>
    );
  }

  const handleApplyFixture = (fixtureId: string) => {
    const fixture = template.fixtures.find((f) => f.id === fixtureId);
    if (!fixture) return;
    const merged = { ...template.defaultConfig, ...fixture.config };
    commitAuthoring((doc) => ({
      ...doc,
      nodes: doc.nodes.map((n) =>
        n.id === node.id
          ? ({ ...n, config: merged } as typeof n)
          : n,
      ),
    }));
  };

  return (
    <div className="space-y-3">
      {/* Source label */}
      <div className="flex items-center gap-2">
        <Eye className="h-3.5 w-3.5 text-muted-foreground" />
        <Badge variant="secondary" className="text-[10px]">
          preview
        </Badge>
      </div>

      {/* Fixture selector */}
      {template.fixtures.length > 0 && (
        <div>
          <div className="flex items-center gap-1 mb-1">
            <FlaskConical className="h-3 w-3 text-muted-foreground" />
            <span className="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
              Fixtures
            </span>
          </div>
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

      {/* Preview outputs */}
      {nodePreview ? (
        <div className="space-y-2">
          <h4 className="text-[10px] font-medium text-muted-foreground uppercase tracking-wide">
            Outputs
          </h4>
          {Object.entries(nodePreview).map(([portKey, payload]) => (
            <PayloadCard key={portKey} portKey={portKey} payload={payload} />
          ))}
        </div>
      ) : (
        <p className="text-xs text-muted-foreground">
          No preview available. Connect inputs or select a fixture.
        </p>
      )}
    </div>
  );
}

function PayloadCard({
  portKey,
  payload,
}: {
  readonly portKey: string;
  readonly payload: PortPayload;
}) {
  return (
    <div className="rounded-md border p-2 space-y-1">
      <div className="flex items-center justify-between">
        <span className="text-xs font-medium">{portKey}</span>
        <Badge
          variant={payload.status === 'error' ? 'destructive' : 'secondary'}
          className="text-[9px] h-4 px-1"
        >
          {payload.status}
        </Badge>
      </div>

      <div className="text-[10px] text-muted-foreground">
        <span className="font-mono">{payload.schemaType}</span>
      </div>

      {/* Storyboard player for videoAsset payloads */}
      {payload.schemaType === 'videoAsset' && payload.value !== null && isVideoAssetPayload(payload.value) ? (
        <StoryboardPlayer videoAsset={payload.value} />
      ) : (
        <>
          {payload.previewText && (
            <p className="text-xs text-foreground line-clamp-3">
              {payload.previewText}
            </p>
          )}

          {payload.errorMessage && (
            <p className="text-xs text-destructive">{payload.errorMessage}</p>
          )}

          {payload.value !== null && !payload.previewText && (
            <pre className="text-[10px] text-muted-foreground bg-muted/50 rounded p-1 max-h-32 overflow-auto">
              {typeof payload.value === 'string'
                ? payload.value.slice(0, 500)
                : JSON.stringify(payload.value, null, 2).slice(0, 500)}
            </pre>
          )}
        </>
      )}
    </div>
  );
}

function isVideoAssetPayload(value: unknown): value is VideoAssetPayload {
  return (
    typeof value === 'object' &&
    value !== null &&
    'timeline' in value &&
    'posterFrameUrl' in value &&
    'storyboardPreview' in value
  )
}
