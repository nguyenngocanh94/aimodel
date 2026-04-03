import { useCallback, useMemo, useRef, useState } from 'react';
import {
  ReactFlow,
  Background,
  Controls,
  MiniMap,
  type Connection,
  type NodeChange,
  type EdgeChange,
  type OnSelectionChangeParams,
  useReactFlow,
  ReactFlowProvider,
  applyNodeChanges,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import { useWorkflowStore } from '@/features/workflow/store/workflow-store';
import { selectDocument } from '@/features/workflow/store/workflow-selectors';
import { getTemplate } from '@/features/node-registry/node-registry';
import { WorkflowNodeCard, type WorkflowNodeType } from './workflow-node-card';
import { WorkflowEdge, type WorkflowEdgeType } from './workflow-edge';
import { useCanvasShortcuts } from '../hooks/use-canvas-shortcuts';
import { useConnectionValidation } from '../hooks/use-connection-validation';
import { CanvasEmptyState } from './canvas-empty-state';
import { QuickAddDialog } from './quick-add-dialog';
import { ConnectDialog } from './connect-dialog';
import { RunAnnouncer } from './run-announcer';
import { checkCompatibility } from '@/features/workflows/domain/type-compatibility';
import { exportWorkflowAsBlob } from '@/features/workflows/data/workflow-import-export';
import type { WorkflowNode, WorkflowEdge as WfEdge, NodeRunRecord } from '@/features/workflows/domain/workflow-types';
import type { WorkflowEdgeData } from './workflow-edge';
import { useRunStore } from '@/features/execution/store/run-store';
import { selectNodeRunRecords } from '@/features/execution/store/run-selectors';

// Custom node types
const nodeTypes = {
  workflowNode: WorkflowNodeCard,
};

// Custom edge types
const edgeTypes = {
  workflowEdge: WorkflowEdge,
};

/** Convert a domain WorkflowNode into a React Flow node */
function toReactFlowNode(
  node: WorkflowNode,
  nodeRunRecords: Readonly<Record<string, NodeRunRecord>>,
): WorkflowNodeType {
  const template = getTemplate(node.type);
  const category = template?.category ?? 'utility';
  const inputPorts = (template?.inputs ?? []).map((p) => ({
    key: p.key,
    label: p.label,
  }));
  const outputPorts = (template?.outputs ?? []).map((p) => ({
    key: p.key,
    label: p.label,
  }));

  const runRecord = nodeRunRecords[node.id];

  return {
    id: node.id,
    type: 'workflowNode' as const,
    position: { x: node.position.x, y: node.position.y },
    data: {
      node,
      category,
      inputPorts,
      outputPorts,
      disabled: node.disabled,
      runStatus: runRecord?.status ?? 'idle',
      skipReason: runRecord?.skipReason,
    },
  };
}

/** Convert a domain WorkflowEdge into a React Flow edge with validation data */
function toReactFlowEdge(
  edge: WfEdge,
  nodes: readonly WorkflowNode[],
): WorkflowEdgeType {
  let edgeData: WorkflowEdgeData = {};

  // Compute edge validation status from port types
  const sourceNode = nodes.find((n) => n.id === edge.sourceNodeId);
  const targetNode = nodes.find((n) => n.id === edge.targetNodeId);
  if (sourceNode && targetNode) {
    const sourceTemplate = getTemplate(sourceNode.type);
    const targetTemplate = getTemplate(targetNode.type);
    const sourcePort = sourceTemplate?.outputs.find((p) => p.key === edge.sourcePortKey);
    const targetPort = targetTemplate?.inputs.find((p) => p.key === edge.targetPortKey);
    if (sourcePort && targetPort) {
      const compat = checkCompatibility(sourcePort.dataType, targetPort.dataType);
      if (!compat.compatible) {
        edgeData = { validationStatus: 'invalid' };
      } else if (compat.coercionApplied) {
        edgeData = { validationStatus: 'warning' };
      } else {
        edgeData = { validationStatus: 'valid' };
      }
    }
  }

  return {
    id: edge.id,
    source: edge.sourceNodeId,
    target: edge.targetNodeId,
    sourceHandle: edge.sourcePortKey,
    targetHandle: edge.targetPortKey,
    type: 'workflowEdge' as const,
    data: edgeData,
  };
}

/**
 * CanvasSurfaceInner - The actual React Flow canvas (requires ReactFlowProvider ancestor)
 */
function CanvasSurfaceInner() {
  const document = useWorkflowStore(selectDocument);
  const commitAuthoring = useWorkflowStore((s) => s.commitAuthoring);
  const setSelectedNodeIds = useWorkflowStore((s) => s.setSelectedNodeIds);
  const setSelectedEdgeId = useWorkflowStore((s) => s.setSelectedEdgeId);
  const undo = useWorkflowStore((s) => s.undo);
  const redo = useWorkflowStore((s) => s.redo);
  const setViewport = useWorkflowStore((s) => s.setViewport);

  const nodeRunRecords = useRunStore(selectNodeRunRecords);

  const reactFlowInstance = useReactFlow();
  const wrapperRef = useRef<HTMLDivElement>(null);

  // Derive React Flow nodes/edges from workflow store
  const rfNodes: WorkflowNodeType[] = useMemo(
    () => document.nodes.map((node) => toReactFlowNode(node, nodeRunRecords)),
    [document.nodes, nodeRunRecords],
  );

  const rfEdges: WorkflowEdgeType[] = useMemo(
    () => document.edges.map((edge) => toReactFlowEdge(edge, document.nodes)),
    [document.edges, document.nodes],
  );

  // Handle node position/dimension changes from React Flow
  const onNodesChange = useCallback(
    (changes: NodeChange<WorkflowNodeType>[]) => {
      // Filter out selection changes — we handle those via onSelectionChange
      const positionChanges = changes.filter(
        (c) => c.type === 'position' || c.type === 'dimensions',
      );

      // Handle removal changes via commitAuthoring
      const removeChanges = changes.filter((c) => c.type === 'remove');
      if (removeChanges.length > 0) {
        const removeIds = new Set(
          removeChanges.map((c) => (c as { id: string }).id),
        );
        commitAuthoring((doc) => ({
          ...doc,
          nodes: doc.nodes.filter((n) => !removeIds.has(n.id)),
          edges: doc.edges.filter(
            (e) =>
              !removeIds.has(e.sourceNodeId) && !removeIds.has(e.targetNodeId),
          ),
        }));
      }

      // Handle position changes — apply to React Flow's internal copy,
      // then persist final positions on drag stop
      if (positionChanges.length > 0) {
        const hasFinished = changes.some(
          (c) => c.type === 'position' && !c.dragging,
        );
        if (hasFinished) {
          // Compute the new node positions by applying changes
          const updated = applyNodeChanges(changes, rfNodes);
          const posMap = new Map(updated.map((n) => [n.id, n.position]));
          commitAuthoring((doc) => ({
            ...doc,
            nodes: doc.nodes.map((n) => {
              const pos = posMap.get(n.id);
              if (pos && (pos.x !== n.position.x || pos.y !== n.position.y)) {
                return { ...n, position: { x: pos.x, y: pos.y } };
              }
              return n;
            }),
          }));
        }
      }
    },
    [commitAuthoring, rfNodes],
  );

  // Handle edge changes from React Flow
  const onEdgesChange = useCallback(
    (changes: EdgeChange<WorkflowEdgeType>[]) => {
      const removeChanges = changes.filter((c) => c.type === 'remove');
      if (removeChanges.length > 0) {
        const removeIds = new Set(
          removeChanges.map((c) => (c as { id: string }).id),
        );
        commitAuthoring((doc) => ({
          ...doc,
          edges: doc.edges.filter((e) => !removeIds.has(e.id)),
        }));
      }
    },
    [commitAuthoring],
  );

  // Handle new edge connections
  const onConnect = useCallback(
    (connection: Connection) => {
      if (!connection.source || !connection.target) return;
      const edgeId = `edge-${connection.source}-${connection.sourceHandle ?? 'out'}-${connection.target}-${connection.targetHandle ?? 'in'}`;
      commitAuthoring((doc) => ({
        ...doc,
        edges: [
          ...doc.edges,
          {
            id: edgeId,
            sourceNodeId: connection.source!,
            sourcePortKey: connection.sourceHandle ?? 'output',
            targetNodeId: connection.target!,
            targetPortKey: connection.targetHandle ?? 'input',
          },
        ],
      }));
    },
    [commitAuthoring],
  );

  // Sync selection to workflow store
  const onSelectionChange = useCallback(
    ({ nodes, edges }: OnSelectionChangeParams) => {
      setSelectedNodeIds(nodes.map((n) => n.id));
      setSelectedEdgeId(edges.length === 1 ? edges[0].id : null);
    },
    [setSelectedNodeIds, setSelectedEdgeId],
  );

  // Viewport change
  const onMoveEnd = useCallback(
    (_event: unknown, viewport: { x: number; y: number; zoom: number }) => {
      setViewport(viewport);
    },
    [setViewport],
  );

  // DnD drop handler
  const onDragOver = useCallback((event: React.DragEvent) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'copy';
  }, []);

  const onDrop = useCallback(
    (event: React.DragEvent) => {
      event.preventDefault();
      const raw = event.dataTransfer.getData('application/json');
      if (!raw) return;

      try {
        const dragItem = JSON.parse(raw) as { type: string; templateType: string };
        if (dragItem.type !== 'node') return;

        const template = getTemplate(dragItem.templateType);
        if (!template) return;

        // Convert screen coords to canvas coords
        const position = reactFlowInstance.screenToFlowPosition({
          x: event.clientX,
          y: event.clientY,
        });

        const newNode: WorkflowNode = {
          id: `node-${crypto.randomUUID().slice(0, 8)}`,
          type: template.type,
          label: template.title,
          position: { x: position.x, y: position.y },
          config: template.defaultConfig,
        };

        commitAuthoring((doc) => ({
          ...doc,
          nodes: [...doc.nodes, newNode],
        }));
        // Auto-select dropped node so inspector shows Config tab
        setSelectedNodeIds([newNode.id]);
      } catch {
        // Invalid drag data, ignore
      }
    },
    [reactFlowInstance, commitAuthoring, setSelectedNodeIds],
  );

  // Duplicate selected nodes
  const handleDuplicate = useCallback(() => {
    const selectedIds = useWorkflowStore.getState().selectedNodeIds;
    if (selectedIds.length === 0) return;

    commitAuthoring((doc) => {
      const toDuplicate = doc.nodes.filter((n) => selectedIds.includes(n.id));
      const newNodes = toDuplicate.map((n) => ({
        ...n,
        id: `node-${crypto.randomUUID().slice(0, 8)}`,
        position: { x: n.position.x + 30, y: n.position.y + 30 },
      }));
      return { ...doc, nodes: [...doc.nodes, ...newNodes] };
    });
  }, [commitAuthoring]);

  // Delete selected nodes/edges
  const handleDelete = useCallback(() => {
    const { selectedNodeIds: nodeIds, selectedEdgeId: edgeId } =
      useWorkflowStore.getState();
    if (nodeIds.length === 0 && !edgeId) return;

    const nodeSet = new Set(nodeIds);
    commitAuthoring((doc) => ({
      ...doc,
      nodes: doc.nodes.filter((n) => !nodeSet.has(n.id)),
      edges: doc.edges.filter(
        (e) =>
          e.id !== edgeId &&
          !nodeSet.has(e.sourceNodeId) &&
          !nodeSet.has(e.targetNodeId),
      ),
    }));
    setSelectedNodeIds([]);
    setSelectedEdgeId(null);
  }, [commitAuthoring, setSelectedNodeIds, setSelectedEdgeId]);

  // Fit view
  const handleFitView = useCallback(() => {
    reactFlowInstance.fitView({ padding: 0.2 });
  }, [reactFlowInstance]);

  // Select all
  const handleSelectAll = useCallback(() => {
    const allIds = useWorkflowStore.getState().document.nodes.map((n) => n.id);
    setSelectedNodeIds(allIds);
  }, [setSelectedNodeIds]);

  // Connection validation
  const isValidConnection = useConnectionValidation();

  // Dialog state
  const [quickAddOpen, setQuickAddOpen] = useState(false);
  const [connectDialogOpen, setConnectDialogOpen] = useState(false);

  // Save: trigger committed snapshot to IndexedDB
  const handleSave = useCallback(() => {
    useWorkflowStore.getState().commitAuthoring((doc) => doc);
  }, []);

  // Export: download workflow as JSON
  const handleExport = useCallback(() => {
    const blob = exportWorkflowAsBlob(document);
    const url = URL.createObjectURL(blob);
    const a = globalThis.document.createElement('a');
    a.href = url;
    a.download = `${document.name || 'workflow'}.json`;
    a.click();
    URL.revokeObjectURL(url);
  }, [document]);

  // Quick add: add node from template
  const handleQuickAddSelect = useCallback(
    (templateType: string) => {
      const template = getTemplate(templateType);
      if (!template) return;
      const viewport = reactFlowInstance.getViewport();
      const position = reactFlowInstance.screenToFlowPosition({
        x: globalThis.window.innerWidth / 2,
        y: globalThis.window.innerHeight / 2,
      });
      const newNode: WorkflowNode = {
        id: `node-${crypto.randomUUID().slice(0, 8)}`,
        type: template.type,
        label: template.title,
        position: { x: position.x - viewport.x, y: position.y - viewport.y },
        config: template.defaultConfig,
      };
      commitAuthoring((doc) => ({
        ...doc,
        nodes: [...doc.nodes, newNode],
      }));
    },
    [reactFlowInstance, commitAuthoring],
  );

  // Connect dialog: create edge from selected node
  const handleConnectFromDialog = useCallback(
    (targetNodeId: string, sourcePort: string, targetPort: string) => {
      const sourceId = useWorkflowStore.getState().selectedNodeIds[0];
      if (!sourceId) return;
      const edgeId = `edge-${sourceId}-${sourcePort}-${targetNodeId}-${targetPort}`;
      commitAuthoring((doc) => ({
        ...doc,
        edges: [
          ...doc.edges,
          {
            id: edgeId,
            sourceNodeId: sourceId,
            sourcePortKey: sourcePort,
            targetNodeId: targetNodeId,
            targetPortKey: targetPort,
          },
        ],
      }));
    },
    [commitAuthoring],
  );

  // Escape: close dialogs first, then clear selection
  const handleEscape = useCallback(() => {
    if (quickAddOpen) {
      setQuickAddOpen(false);
      return;
    }
    if (connectDialogOpen) {
      setConnectDialogOpen(false);
      return;
    }
    setSelectedNodeIds([]);
    setSelectedEdgeId(null);
  }, [quickAddOpen, connectDialogOpen, setSelectedNodeIds, setSelectedEdgeId]);

  // Wire up keyboard shortcuts
  useCanvasShortcuts({
    onDuplicate: handleDuplicate,
    onDelete: handleDelete,
    onFitView: handleFitView,
    onSelectAll: handleSelectAll,
    onUndo: undo,
    onRedo: redo,
    onSave: handleSave,
    onExport: handleExport,
    onQuickAdd: () => setQuickAddOpen(true),
    onConnect: () => setConnectDialogOpen(true),
    onEscape: handleEscape,
  });

  const isEmpty = document.nodes.length === 0;

  return (
    <div ref={wrapperRef} className="h-full w-full relative" data-testid="canvas-surface" aria-label="Workflow canvas">
      <ReactFlow<WorkflowNodeType, WorkflowEdgeType>
        nodes={rfNodes}
        edges={rfEdges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        isValidConnection={isValidConnection}
        onSelectionChange={onSelectionChange}
        onMoveEnd={onMoveEnd}
        onDragOver={onDragOver}
        onDrop={onDrop}
        nodeTypes={nodeTypes}
        edgeTypes={edgeTypes}
        fitView
        snapToGrid
        snapGrid={[15, 15]}
        selectionOnDrag
        multiSelectionKeyCode="Shift"
        deleteKeyCode={['Backspace', 'Delete']}
        defaultEdgeOptions={{
          type: 'workflowEdge',
          animated: false,
        }}
        proOptions={{ hideAttribution: true }}
      >
        <Background gap={15} size={1} />
        {!isEmpty && <Controls />}
        {!isEmpty && (
          <MiniMap
            nodeStrokeWidth={3}
            zoomable
            pannable
            className="!bottom-20"
          />
        )}
      </ReactFlow>
      {isEmpty && (
        <div className="absolute inset-0 z-10">
          <CanvasEmptyState onAddNode={() => setQuickAddOpen(true)} />
        </div>
      )}
      <QuickAddDialog
        open={quickAddOpen}
        onClose={() => setQuickAddOpen(false)}
        onSelect={handleQuickAddSelect}
      />
      <ConnectDialog
        open={connectDialogOpen}
        onClose={() => setConnectDialogOpen(false)}
        onConnect={handleConnectFromDialog}
      />
      <RunAnnouncer />
    </div>
  );
}

/**
 * CanvasSurface - React Flow canvas for workflow editing.
 * Wraps the inner canvas in a ReactFlowProvider so useReactFlow() works.
 */
export function CanvasSurface() {
  return (
    <ReactFlowProvider>
      <CanvasSurfaceInner />
    </ReactFlowProvider>
  );
}
