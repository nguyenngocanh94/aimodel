import { useMemo } from 'react';
import { Settings2, Info, Workflow, Eye, AlertTriangle, Database } from 'lucide-react';
import { Panel, PanelContent, PanelHeader, PanelTitle } from '@/shared/ui/panel';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/ui/tabs';
import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import {
  selectDocument,
  selectSelectedNodeIds,
  selectSelectedEdgeId,
} from '@/features/workflow/store/workflow-selectors';
import { NodeConfigTab } from './node-config-tab';
import { MetadataTab } from './metadata-tab';
import { PreviewTab } from './preview-tab';
import { ValidationTab } from './validation-tab';
import { DataInspectorPanel } from '@/features/data-inspector/components/data-inspector-panel';

/**
 * InspectorPanel - Right panel with context-sensitive inspection
 *
 * Modes per plan section 6.4:
 * - node selected: Config, Metadata tabs
 * - edge selected: edge info
 * - workflow selected / nothing: workflow summary
 */
export function InspectorPanel() {
  const document = useWorkflowStore(selectDocument);
  const selectedNodeIds = useWorkflowStore(selectSelectedNodeIds);
  const selectedEdgeId = useWorkflowStore(selectSelectedEdgeId);
  const inspectorTab = useWorkflowStore((s) => s.inspectorTab);
  const setInspectorTab = useWorkflowStore((s) => s.setInspectorTab);

  const selectedNode = useMemo(() => {
    if (selectedNodeIds.length !== 1) return null;
    return document.nodes.find((n) => n.id === selectedNodeIds[0]) ?? null;
  }, [document.nodes, selectedNodeIds]);

  const selectedEdge = useMemo(() => {
    if (!selectedEdgeId) return null;
    return document.edges.find((e) => e.id === selectedEdgeId) ?? null;
  }, [document.edges, selectedEdgeId]);

  return (
    <Panel
      variant="ghost"
      className="flex h-full min-h-0 flex-col rounded-none border-0 border-l"
    >
      <PanelHeader className="border-b px-3 py-2">
        <PanelTitle className="text-sm font-medium">Inspector</PanelTitle>
      </PanelHeader>

      <PanelContent className="flex flex-1 flex-col overflow-hidden p-0">
        {/* Node selected */}
        {selectedNode && (
          <Tabs
            value={inspectorTab}
            onValueChange={(v) => setInspectorTab(v as typeof inspectorTab)}
            className="flex flex-1 flex-col overflow-hidden"
          >
            <TabsList className="mx-3 mt-2 h-8">
              <TabsTrigger value="config" className="text-xs h-6 gap-1">
                <Settings2 className="h-3 w-3" />
                Config
              </TabsTrigger>
              <TabsTrigger value="validation" className="text-xs h-6 gap-1">
                <AlertTriangle className="h-3 w-3" />
                Valid
              </TabsTrigger>
              <TabsTrigger value="data" className="text-xs h-6 gap-1">
                <Eye className="h-3 w-3" />
                Preview
              </TabsTrigger>
              <TabsTrigger value="inspect" className="text-xs h-6 gap-1">
                <Database className="h-3 w-3" />
                Data
              </TabsTrigger>
              <TabsTrigger value="metadata" className="text-xs h-6 gap-1">
                <Info className="h-3 w-3" />
                Meta
              </TabsTrigger>
            </TabsList>

            <div className="flex-1 overflow-y-auto px-3 py-2">
              <TabsContent value="config" className="m-0">
                <NodeConfigTab node={selectedNode} />
              </TabsContent>
              <TabsContent value="validation" className="m-0">
                <ValidationTab nodeId={selectedNode.id} />
              </TabsContent>
              <TabsContent value="data" className="m-0">
                <PreviewTab node={selectedNode} />
              </TabsContent>
              <TabsContent value="inspect" className="m-0">
                <DataInspectorPanel />
              </TabsContent>
              <TabsContent value="metadata" className="m-0">
                <MetadataTab node={selectedNode} />
              </TabsContent>
            </div>
          </Tabs>
        )}

        {/* Edge selected or nothing selected: show Data Inspector */}
        {!selectedNode && (
          <DataInspectorPanel />
        )}
      </PanelContent>
    </Panel>
  );
}
