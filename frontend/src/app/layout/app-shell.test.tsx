import { describe, it, expect, beforeEach } from 'vitest'
import { render, screen, fireEvent, act } from '@testing-library/react'
import { ReactFlowProvider } from '@xyflow/react'

import { AppShell } from './app-shell'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'

function ShellWithProviders() {
  return (
    <ReactFlowProvider>
      <AppShell />
    </ReactFlowProvider>
  )
}

describe('AppShell', () => {
  beforeEach(() => {
    // Reset store before each test
    const store = useWorkflowStore.getState()
    store.resetDocument({
      id: 'test-doc',
      schemaVersion: 1,
      name: 'Untitled workflow',
      description: '',
      tags: [],
      nodes: [],
      edges: [],
      viewport: { x: 0, y: 0, zoom: 1 },
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    })
  })

  it('renders header, canvas, run toolbar, and status row', () => {
    render(<ShellWithProviders />)

    // Header shows workflow name
    expect(screen.getAllByText('Untitled workflow').length).toBeGreaterThanOrEqual(1)
    // Node library is hidden by default
    expect(screen.queryByTestId('node-library-panel')).not.toBeInTheDocument()
    // Inspector is hidden when no node is selected
    expect(screen.queryByTestId('inspector')).not.toBeInTheDocument()
    expect(screen.getByTestId('canvas-surface')).toBeInTheDocument()
    expect(screen.getByTestId('run-toolbar')).toBeInTheDocument()
    // Toggle button is present to show/hide library
    expect(screen.getByTestId('toggle-node-library-btn')).toBeInTheDocument()
  })

  it('toggles node library visibility when button is clicked', () => {
    render(<ShellWithProviders />)

    // Initially hidden (no node selected)
    expect(screen.queryByTestId('node-library-panel')).not.toBeInTheDocument()

    // Click toggle to show library
    const toggleBtn = screen.getByTestId('toggle-node-library-btn')
    fireEvent.click(toggleBtn)

    // Now library is visible
    expect(screen.getByTestId('node-library-panel')).toBeInTheDocument()
    // Library panel shows "Nodes" title
    expect(screen.getByText('Nodes')).toBeInTheDocument()

    // Click toggle to hide library
    fireEvent.click(toggleBtn)

    // Library is hidden again
    expect(screen.queryByTestId('node-library-panel')).not.toBeInTheDocument()
  })

  it('does not show node library when a node is selected (only via header toggle)', async () => {
    render(<ShellWithProviders />)

    // Simulate selecting a node by updating the store
    await act(async () => {
      const store = useWorkflowStore.getState()
      store.addNode({
        id: 'test-node-1',
        type: 'userPrompt',
        label: 'Test Node',
        position: { x: 100, y: 100 },
        config: {},
      })
      store.setSelectedNodeIds(['test-node-1'])
    })

    // Library should NOT be visible — only toggled via header button
    expect(screen.queryByTestId('node-library-panel')).not.toBeInTheDocument()
  })

  it('shows inspector when a node is selected', async () => {
    render(<ShellWithProviders />)

    // Inspector hidden when no node selected
    expect(screen.queryByTestId('inspector')).not.toBeInTheDocument()

    // Select a node
    await act(async () => {
      const store = useWorkflowStore.getState()
      store.addNode({
        id: 'test-node-1',
        type: 'userPrompt',
        label: 'Test Node',
        position: { x: 100, y: 100 },
        config: {},
      })
      store.setSelectedNodeIds(['test-node-1'])
    })

    // Inspector should now be visible
    expect(screen.getByTestId('inspector')).toBeInTheDocument()
  })
})
