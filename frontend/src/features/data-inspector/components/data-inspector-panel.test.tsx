import { render, screen } from '@testing-library/react'
import { describe, it, expect, beforeEach } from 'vitest'
import { ReactFlowProvider } from '@xyflow/react'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'
import { useRunStore } from '@/features/execution/store/run-store'
import { DataInspectorPanel } from './data-inspector-panel'
import type { WorkflowDocument, EdgePayloadSnapshot } from '@/features/workflows/domain/workflow-types'

function makeDocument(overrides: Partial<WorkflowDocument> = {}): WorkflowDocument {
  return {
    id: 'wf-1',
    schemaVersion: 1,
    name: 'Test',
    description: '',
    tags: [],
    nodes: [],
    edges: [],
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: '2025-01-01T00:00:00Z',
    updatedAt: '2025-01-01T00:00:00Z',
    ...overrides,
  }
}

function Wrapper({ children }: { children: React.ReactNode }) {
  return <ReactFlowProvider>{children}</ReactFlowProvider>
}

describe('DataInspectorPanel', () => {
  beforeEach(() => {
    useWorkflowStore.setState({
      document: makeDocument(),
      selectedNodeIds: [],
      selectedEdgeId: null,
    })
    useRunStore.setState({
      activeRun: null,
      recentRuns: [],
      nodeRunRecords: {},
      edgePayloadSnapshots: {},
    })
  })

  it('should show empty state instructions when nothing selected', () => {
    render(<DataInspectorPanel />, { wrapper: Wrapper })
    expect(screen.getByTestId('data-inspector-empty')).toBeInTheDocument()
    expect(screen.getByText(/Select a node/)).toBeInTheDocument()
  })

  it('should show node data when node is selected', () => {
    useWorkflowStore.setState({
      document: makeDocument({
        nodes: [{
          id: 'n1',
          type: 'userPrompt',
          label: 'My Prompt',
          position: { x: 0, y: 0 },
          config: {},
        }],
      }),
      selectedNodeIds: ['n1'],
    })

    render(<DataInspectorPanel />, { wrapper: Wrapper })
    expect(screen.getByTestId('data-inspector-node')).toBeInTheDocument()
    expect(screen.getByText('My Prompt')).toBeInTheDocument()
  })

  it('should show edge data when edge is selected', () => {
    useWorkflowStore.setState({
      document: makeDocument({
        nodes: [
          { id: 'n1', type: 'userPrompt', label: 'Prompt', position: { x: 0, y: 0 }, config: {} },
          { id: 'n2', type: 'scriptWriter', label: 'Script', position: { x: 200, y: 0 }, config: {} },
        ],
        edges: [{
          id: 'e1',
          sourceNodeId: 'n1',
          sourcePortKey: 'prompt',
          targetNodeId: 'n2',
          targetPortKey: 'prompt',
        }],
      }),
      selectedEdgeId: 'e1',
    })

    render(<DataInspectorPanel />, { wrapper: Wrapper })
    expect(screen.getByTestId('data-inspector-edge')).toBeInTheDocument()
  })

  it('should show run summary when runs exist', () => {
    useRunStore.setState({
      recentRuns: [{
        id: 'run-1',
        workflowId: 'wf-1',
        mode: 'mock',
        trigger: 'runWorkflow',
        plannedNodeIds: ['n1'],
        status: 'success',
        startedAt: '2025-06-01T00:00:00Z',
        completedAt: '2025-06-01T00:00:01Z',
        documentHash: 'hash',
        nodeConfigHashes: {},
      }],
      nodeRunRecords: {
        n1: {
          runId: 'run-1',
          nodeId: 'n1',
          status: 'success',
          inputPayloads: {},
          outputPayloads: {},
          usedCache: false,
        },
      },
    })

    render(<DataInspectorPanel />, { wrapper: Wrapper })
    expect(screen.getByTestId('data-inspector-summary')).toBeInTheDocument()
    expect(screen.getByText('Run Summary')).toBeInTheDocument()
  })

  it('should show node outputs from run when available', () => {
    useWorkflowStore.setState({
      document: makeDocument({
        nodes: [{
          id: 'n1',
          type: 'userPrompt',
          label: 'Prompt',
          position: { x: 0, y: 0 },
          config: {},
        }],
      }),
      selectedNodeIds: ['n1'],
    })

    useRunStore.setState({
      nodeRunRecords: {
        n1: {
          runId: 'run-1',
          nodeId: 'n1',
          status: 'success',
          inputPayloads: {},
          outputPayloads: {
            prompt: {
              value: { text: 'Generated prompt' },
              status: 'success',
              schemaType: 'prompt',
              producedAt: '2025-06-01T00:00:00Z',
              sourceNodeId: 'n1',
              sourcePortKey: 'prompt',
            },
          },
          usedCache: false,
        },
      },
    })

    render(<DataInspectorPanel />, { wrapper: Wrapper })
    expect(screen.getByTestId('payload-prompt')).toBeInTheDocument()
  })

  it('should show edge payload snapshot with coercion info', () => {
    useWorkflowStore.setState({
      document: makeDocument({
        nodes: [
          { id: 'n1', type: 'userPrompt', label: 'Prompt', position: { x: 0, y: 0 }, config: {} },
          { id: 'n2', type: 'scriptWriter', label: 'Script', position: { x: 200, y: 0 }, config: {} },
        ],
        edges: [{
          id: 'e1',
          sourceNodeId: 'n1',
          sourcePortKey: 'prompt',
          targetNodeId: 'n2',
          targetPortKey: 'prompt',
        }],
      }),
      selectedEdgeId: 'e1',
    })

    const snapshot: EdgePayloadSnapshot = {
      edgeId: 'e1',
      sourcePayload: {
        value: { text: 'Hello' },
        status: 'success',
        schemaType: 'prompt',
      },
      transportedPayload: {
        value: { text: 'Hello' },
        status: 'success',
        schemaType: 'prompt',
      },
      coercionApplied: 'text → prompt',
    }
    useRunStore.setState({ edgePayloadSnapshots: { e1: snapshot } })

    render(<DataInspectorPanel />, { wrapper: Wrapper })
    expect(screen.getByText(/Coercion: text → prompt/)).toBeInTheDocument()
  })
})
