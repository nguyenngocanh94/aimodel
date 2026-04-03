import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ReactFlowProvider } from '@xyflow/react'

import { AppShell } from './app-shell'

function ShellWithProviders() {
  return (
    <ReactFlowProvider>
      <AppShell />
    </ReactFlowProvider>
  )
}

describe('AppShell', () => {
  it('renders header, three regions, run toolbar, and status row', () => {
    render(<ShellWithProviders />)

    // Header shows workflow name; inspector also shows it
    expect(screen.getAllByText('Untitled workflow').length).toBeGreaterThanOrEqual(1)
    // Node library and inspector both show "Nodes"
    expect(screen.getAllByText('Nodes').length).toBeGreaterThanOrEqual(1)
    expect(screen.getByTestId('canvas-surface')).toBeInTheDocument()
    expect(screen.getByTestId('inspector-panel-content')).toBeInTheDocument()
    expect(screen.getByTestId('run-toolbar')).toBeInTheDocument()
    expect(screen.getByText('No selection')).toBeInTheDocument()
  })
})
