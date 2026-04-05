import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  ReactFlow,
  Background,
  Controls,
  MiniMap,
  applyNodeChanges,
  type Connection,
  type NodeChange,
  type EdgeChange,
  type OnSelectionChangeParams,
  useReactFlow,
  ReactFlowProvider,
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
import { planExecution } from '@/features/execution/domain/run-planner';
import { executeMockRun } from '@/features/execution/domain/mock-executor';
import { runCache } from '@/features/execution/domain/run-cache';
import type { WorkflowNode, WorkflowEdge as WfEdge, NodeRunRecord } from '@/features/workflows/domain/workflow-types';
import type { WorkflowEdgeData } from './workflow-edge';
import { useRunStore } from '@/features/execution/store/run-store';
import { selectNodeRunRecords, selectIsRunning } from '@/features/execution/store/run-selectors';

import { ActiveNodeContext } from './active-node-context';

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
  connectedPorts: ReadonlySet<string>,
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
      connectedPorts,
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
        edgeData = { validationStatus: 'invalid', sourceDataType: sourcePort.dataType };
      } else if (compat.coercionApplied) {
        edgeData = { validationStatus: 'warning', sourceDataType: sourcePort.dataType };
      } else {
        edgeData = { validationStatus: 'valid', sourceDataType: sourcePort.dataType };
      }
    } else if (sourcePort) {
      edgeData = { sourceDataType: sourcePort.dataType };
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
  const markSaved = useWorkflowStore((s) => s.markSaved);
  const setInspectorTab = useWorkflowStore((s) => s.setInspectorTab);

  const nodeRunRecords = useRunStore(selectNodeRunRecords);
  const isRunning = useRunStore(selectIsRunning);

  const reactFlowInstance = useReactFlow();
  const wrapperRef = useRef<HTMLDivElement>(null);

  // Track the "active" node — double-clicked to open inspector
  const [activeNodeId, setActiveNodeId] = useState<string | null>(null);

  // Derive React Flow nodes from workflow store, kept in local state
  // so we can apply drag position changes immediately for smooth visual feedback.
  const storeNodes: WorkflowNodeType[] = useMemo(
    () => {
      // Build per-node set of connected port keys (format: "in:portKey" or "out:portKey")
      const connectedByNode = new Map<string, Set<string>>();
      for (const edge of document.edges) {
        // Source node's output port
        let srcSet = connectedByNode.get(edge.sourceNodeId);
        if (!srcSet) { srcSet = new Set(); connectedByNode.set(edge.sourceNodeId, srcSet); }
        srcSet.add(`out:${edge.sourcePortKey}`);
        // Target node's input port
        let tgtSet = connectedByNode.get(edge.targetNodeId);
        if (!tgtSet) { tgtSet = new Set(); connectedByNode.set(edge.targetNodeId, tgtSet); }
        tgtSet.add(`in:${edge.targetPortKey}`);
      }
      const emptySet = new Set<string>();
      return document.nodes.map((node) =>
        toReactFlowNode(node, nodeRunRecords, connectedByNode.get(node.id) ?? emptySet),
      );
    },
    [document.nodes, document.edges, nodeRunRecords],
  );
  const [rfNodes, setRfNodes] = useState<WorkflowNodeType[]>(storeNodes);

  // Sync local RF nodes whenever the store-derived nodes change
  useEffect(() => {
    setRfNodes(storeNodes);
  }, [storeNodes]);

  const rfEdges: WorkflowEdgeType[] = useMemo(
    () => document.edges.map((edge) => toReactFlowEdge(edge, document.nodes)),
    [document.edges, document.nodes],
  );

  // Handle node position/dimension changes from React Flow
  const onNodesChange = useCallback(
    (changes: NodeChange<WorkflowNodeType>[]) => {
      // Apply ALL changes to local state immediately for smooth visual feedback
      setRfNodes((prev) => applyNodeChanges(changes, prev));

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

      // Persist position changes to store only when drag finishes
      const finishedPositions = changes.filter(
        (c): c is { type: 'position'; id: string; position: { x: number; y: number }; dragging?: boolean } =>
          c.type === 'position' && !c.dragging && 'position' in c && c.position != null,
      );
      if (finishedPositions.length > 0) {
        const posMap = new Map<string, { x: number; y: number }>();
        finishedPositions.forEach((change) => {
          posMap.set(change.id, change.position);
        });

        if (posMap.size > 0) {
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
    [commitAuthoring],
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
      // Clear active state if the active node was deselected
      if (activeNodeId && !nodes.some((n) => n.id === activeNodeId)) {
        setActiveNodeId(null);
      }
    },
    [setSelectedNodeIds, setSelectedEdgeId, activeNodeId],
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
        // Auto-select dropped node and switch to Config tab (design system section 15)
        setSelectedNodeIds([newNode.id]);
        setInspectorTab('config');
      } catch {
        // Invalid drag data, ignore
      }
    },
    [reactFlowInstance, commitAuthoring, setSelectedNodeIds, setInspectorTab],
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

  // Save: mark current document as saved
  const handleSave = useCallback(() => {
    markSaved();
  }, [markSaved]);

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

  // Quick add: add node from template at canvas center
  const handleQuickAddSelect = useCallback(
    (templateType: string) => {
      const template = getTemplate(templateType);
      if (!template) return;
      // screenToFlowPosition already accounts for viewport pan/zoom
      const position = reactFlowInstance.screenToFlowPosition({
        x: globalThis.window.innerWidth / 2,
        y: globalThis.window.innerHeight / 2,
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
      // Select the new node and switch to Config tab
      setSelectedNodeIds([newNode.id]);
      setInspectorTab('config');
    },
    [reactFlowInstance, commitAuthoring, setSelectedNodeIds, setInspectorTab],
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

  // Double-click a node to activate it (open inspector)
  const onNodeDoubleClick = useCallback(
    (_event: React.MouseEvent, node: WorkflowNodeType) => {
      setActiveNodeId(node.id);
      setInspectorTab('config');
    },
    [setInspectorTab],
  );

  // Inspect: focus inspector on selected node's config tab
  const handleInspect = useCallback(() => {
    const selectedId = useWorkflowStore.getState().selectedNodeIds[0];
    if (selectedId) setActiveNodeId(selectedId);
    setInspectorTab('config');
  }, [setInspectorTab]);

  // Run selected node
  const handleRunNode = useCallback(() => {
    if (isRunning) return;
    const selectedId = useWorkflowStore.getState().selectedNodeIds[0];
    if (!selectedId) return;
    const plan = planExecution({
      workflow: document,
      trigger: 'runNode',
      targetNodeId: selectedId,
    });
    const controller = new AbortController();
    executeMockRun({ workflow: document, plan, runCache, signal: controller.signal });
  }, [document, isRunning]);

  // Run entire workflow
  const handleRunWorkflow = useCallback(() => {
    if (isRunning) return;
    const plan = planExecution({
      workflow: document,
      trigger: 'runWorkflow',
      targetNodeId: undefined,
    });
    const controller = new AbortController();
    executeMockRun({ workflow: document, plan, runCache, signal: controller.signal });
  }, [document, isRunning]);

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
    setActiveNodeId(null);
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
    onInspect: handleInspect,
    onRunNode: handleRunNode,
    onRunWorkflow: handleRunWorkflow,
    onConnect: () => setConnectDialogOpen(true),
    onEscape: handleEscape,
  });

  const isEmpty = document.nodes.length === 0;

  return (
    <ActiveNodeContext.Provider value={activeNodeId}>
    <div ref={wrapperRef} className="h-full w-full relative" data-testid="canvas-surface" aria-label="Workflow canvas">
      <ReactFlow<WorkflowNodeType, WorkflowEdgeType>
        nodes={rfNodes}
        edges={rfEdges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        isValidConnection={isValidConnection}
        onSelectionChange={onSelectionChange}
        onNodeDoubleClick={onNodeDoubleClick}
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
        <Background gap={15} size={1.5} color="#1E2536" />
        {!isEmpty && (
          <Controls
            className="!bg-card !border !border-border !rounded-md !shadow-sm [&>button]:!bg-card [&>button]:!text-foreground [&>button]:!border-border [&>button:hover]:!bg-accent"
          />
        )}
        {!isEmpty && (
          <MiniMap
            nodeStrokeWidth={3}
            zoomable
            pannable
            className="!bottom-20 !bg-background !border !border-border !rounded-md"
            nodeColor={(node) => {
              const cat = (node.data as Record<string, unknown>)?.category as string | undefined;
              const catColors: Record<string, string> = {
                input: '#94A3B8',
                script: '#60A5FA',
                visuals: '#A78BFA',
                audio: '#2DD4BF',
                video: '#FBBF24',
                utility: '#9CA3AF',
                output: '#34D399',
              };
              return catColors[cat ?? ''] ?? '#252B36';
            }}
            maskColor="rgba(11, 13, 18, 0.7)"
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
    </ActiveNodeContext.Provider>
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
