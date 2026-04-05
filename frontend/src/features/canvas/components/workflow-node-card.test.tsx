import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ReactFlowProvider } from '@xyflow/react'
import { ActiveNodeContext } from './active-node-context'
import { WorkflowNodeCard, type WorkflowNodeData } from './workflow-node-card'

/** Minimal wrapper so ReactFlow handles are happy */
function renderNodeCard(
  overrides: Partial<WorkflowNodeData> = {},
  opts: { selected?: boolean; activeNodeId?: string | null } = {},
) {
  const baseData: WorkflowNodeData = {
    node: {
      id: 'node-1',
      type: 'scriptWriter',
      label: 'Script Writer',
      position: { x: 0, y: 0 },
      config: {},
    },
    category: 'script',
    inputPorts: [{ key: 'prompt', label: 'Prompt' }],
    outputPorts: [{ key: 'script', label: 'Script' }],
    ...overrides,
  }

  return render(
    <ReactFlowProvider>
      <ActiveNodeContext.Provider value={opts.activeNodeId ?? null}>
        <WorkflowNodeCard
          id="node-1"
          type="workflowNode"
          data={baseData}
          selected={opts.selected ?? false}
          dragging={false}
          positionAbsoluteX={0}
          positionAbsoluteY={0}
          zIndex={0}
          isConnectable
          sourcePosition={undefined as never}
          targetPosition={undefined as never}
          dragHandle={undefined}
          parentId={undefined}
          draggable
          selectable
          deletable
        />
      </ActiveNodeContext.Provider>
    </ReactFlowProvider>,
  )
}

describe('WorkflowNodeCard', () => {
  it('renders the node label', () => {
    renderNodeCard()
    expect(screen.getByText('Script Writer')).toBeInTheDocument()
  })

  it('renders input and output ports', () => {
    renderNodeCard()
    expect(screen.getByText('Prompt')).toBeInTheDocument()
    expect(screen.getByText('Script')).toBeInTheDocument()
  })

  it('applies selected style when selected', () => {
    renderNodeCard({}, { selected: true })
    const card = screen.getByTestId('node-card-node-1')
    expect(card.dataset.selected).toBe('true')
  })

  it('does NOT show active state when context has different node id', () => {
    renderNodeCard({}, { activeNodeId: 'node-other', selected: true })
    const card = screen.getByTestId('node-card-node-1')
    expect(card.dataset.active).toBeUndefined()
  })

  it('shows active state when context activeNodeId matches this node', () => {
    renderNodeCard({}, { activeNodeId: 'node-1', selected: true })
    const card = screen.getByTestId('node-card-node-1')
    expect(card.dataset.active).toBe('true')
  })

  it('shows active style classes when active', () => {
    renderNodeCard({}, { activeNodeId: 'node-1', selected: true })
    const card = screen.getByTestId('node-card-node-1')
    // Active state should have the stronger primary border
    expect(card.className).toContain('border-primary')
    expect(card.className).toContain('ring-2')
  })

  it('shows selected but not active style when only selected', () => {
    renderNodeCard({}, { selected: true, activeNodeId: null })
    const card = screen.getByTestId('node-card-node-1')
    expect(card.dataset.active).toBeUndefined()
    expect(card.dataset.selected).toBe('true')
    // Selected style has ring-[1.5px], not ring-2
    expect(card.className).toContain('ring-[1.5px]')
    expect(card.className).not.toContain('ring-2')
  })

  it('shows filled port handles when connected', () => {
    const connectedPorts = new Set(['in:prompt', 'out:script'])
    renderNodeCard({ connectedPorts }, {})
    // Connected input port should have bg-primary class
    const inputPort = screen.getByTestId('node-port-in-node-1-prompt')
    const inputHandle = inputPort.querySelector('.react-flow__handle')
    expect(inputHandle?.className).toContain('!bg-primary')
  })

  it('shows hollow port handles when not connected', () => {
    renderNodeCard({ connectedPorts: new Set() }, {})
    const inputPort = screen.getByTestId('node-port-in-node-1-prompt')
    const inputHandle = inputPort.querySelector('.react-flow__handle')
    expect(inputHandle?.className).toContain('!bg-background')
  })

  it('shows disabled style', () => {
    renderNodeCard({ disabled: true })
    const card = screen.getByTestId('node-card-node-1')
    expect(card.dataset.disabled).toBe('true')
    expect(card.className).toContain('opacity-55')
  })
})
