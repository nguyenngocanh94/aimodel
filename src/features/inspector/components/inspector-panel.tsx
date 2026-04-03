import { useEffect, useMemo } from 'react';
import { Settings2, Info, Eye, AlertTriangle, Database, MousePointer } from 'lucide-react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/ui/tabs';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import {
  selectDocument,
  selectSelectedNodeIds,
} from '@/features/workflow/store/workflow-selectors';
import { NodeConfigTab } from './node-config-tab';
import { MetadataTab } from './metadata-tab';
import { PreviewTab } from './preview-tab';
import { ValidationTab } from './validation-tab';
import { DataInspectorPanel } from '@/features/data-inspector/components/data-inspector-panel';

/**
 * InspectorPanel - Right panel per design system section 12.
 * Fixed-width, sticky header + sticky tab list, scroll only inside tab content.
 */
export function InspectorPanel() {
  const document = useWorkflowStore(selectDocument);
  const selectedNodeIds = useWorkflowStore(selectSelectedNodeIds);
  const inspectorTab = useWorkflowStore((s) => s.inspectorTab);
  const setInspectorTab = useWorkflowStore((s) => s.setInspectorTab);

  const setSelectedNodeIds = useWorkflowStore((s) => s.setSelectedNodeIds);

  const selectedNode = useMemo(() => {
    if (selectedNodeIds.length !== 1) return null;
    return document.nodes.find((n) => n.id === selectedNodeIds[0]) ?? null;
  }, [document.nodes, selectedNodeIds]);

  // Recovery: clear stale selection if selected node was deleted
  useEffect(() => {
    if (selectedNodeIds.length === 1 && !selectedNode) {
      setSelectedNodeIds([]);
    }
  }, [selectedNode, selectedNodeIds, setSelectedNodeIds]);

  return (
    <aside
      className="flex h-full w-[400px] min-w-[320px] max-w-[480px] flex-col border-l border-border bg-card text-card-foreground"
      data-testid="inspector-panel-content"
      aria-label="Inspector"
    >
      {/* Sticky header */}
      <div className="sticky top-0 z-10 border-b border-border bg-card/95 px-4 py-3 backdrop-blur">
        <div className="flex items-center justify-between gap-2">
          <div className="min-w-0">
            {selectedNode ? (
              <>
                <h2 className="truncate text-sm font-medium">
                  {selectedNode.label}
                </h2>
                <p className="truncate font-mono text-[11px] text-muted-foreground">
                  {selectedNode.id}
                </p>
              </>
            ) : (
              <h2 className="text-sm font-medium text-muted-foreground">
                No selection
              </h2>
            )}
          </div>
        </div>
      </div>

      {/* Tabs when node is selected */}
      {selectedNode ? (
        <Tabs
          value={inspectorTab}
          onValueChange={(v) => setInspectorTab(v as typeof inspectorTab)}
          className="flex min-h-0 flex-1 flex-col"
        >
          <TabsList
            className="grid grid-cols-5 rounded-none border-b border-border bg-card px-2 py-1"
            data-testid="inspector-tabs"
          >
            <TabsTrigger
              value="config"
              className="text-xs gap-1 transition-tab data-[state=active]:text-primary data-[state=active]:border-b-2 data-[state=active]:border-primary"
              data-testid="inspector-tab-config"
            >
              <Settings2 className="h-3 w-3" aria-hidden="true" />
              Config
            </TabsTrigger>
            <TabsTrigger
              value="preview"
              className="text-xs gap-1 transition-tab data-[state=active]:text-primary data-[state=active]:border-b-2 data-[state=active]:border-primary"
              data-testid="inspector-tab-preview"
            >
              <Eye className="h-3 w-3" aria-hidden="true" />
              Preview
            </TabsTrigger>
            <TabsTrigger
              value="data"
              className="text-xs gap-1 transition-tab data-[state=active]:text-primary data-[state=active]:border-b-2 data-[state=active]:border-primary"
              data-testid="inspector-tab-data"
            >
              <Database className="h-3 w-3" aria-hidden="true" />
              Data
            </TabsTrigger>
            <TabsTrigger
              value="validation"
              className="text-xs gap-1 transition-tab data-[state=active]:text-primary data-[state=active]:border-b-2 data-[state=active]:border-primary"
              data-testid="inspector-tab-validation"
            >
              <AlertTriangle className="h-3 w-3" aria-hidden="true" />
              Valid
            </TabsTrigger>
            <TabsTrigger
              value="metadata"
              className="text-xs gap-1 transition-tab data-[state=active]:text-primary data-[state=active]:border-b-2 data-[state=active]:border-primary"
              data-testid="inspector-tab-metadata"
            >
              <Info className="h-3 w-3" aria-hidden="true" />
              Meta
            </TabsTrigger>
          </TabsList>

          <div className="min-h-0 flex-1 overflow-auto bg-card px-4 py-4">
            <TabsContent value="config" className="m-0">
              <NodeConfigTab node={selectedNode} />
            </TabsContent>
            <TabsContent value="preview" className="m-0">
              <PreviewTab node={selectedNode} />
            </TabsContent>
            <TabsContent value="data" className="m-0">
              <DataInspectorPanel />
            </TabsContent>
            <TabsContent value="validation" className="m-0">
              <ValidationTab nodeId={selectedNode.id} />
            </TabsContent>
            <TabsContent value="metadata" className="m-0">
              <MetadataTab node={selectedNode} />
            </TabsContent>
          </div>
        </Tabs>
      ) : (
        /* No selection: empty state guidance */
        <div className="flex flex-col items-center justify-center gap-3 h-full">
          <MousePointer className="h-8 w-8 text-muted-foreground/40" aria-hidden="true" />
          <p className="text-sm text-muted-foreground">Select a node to inspect</p>
          <p className="text-xs text-muted-foreground/70">Click a node on the canvas or use keyboard navigation</p>
        </div>
      )}
    </aside>
  );
}
