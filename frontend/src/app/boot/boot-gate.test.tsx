import { render, screen } from '@testing-library/react'
import { ReactFlowProvider } from '@xyflow/react'
import { describe, it, expect, vi } from 'vitest'

// Mock useBootState to control boot state in tests
const mockUseBootState = vi.fn()
vi.mock('./boot-provider', () => ({
  useBootState: () => mockUseBootState(),
}))

import { BootGate, AppSplashScreen, FatalBootErrorScreen } from './boot-gate'

describe('AppSplashScreen', () => {
  it('should render loading text', () => {
    render(<AppSplashScreen />)
    expect(screen.getByText(/Loading workspace/)).toBeInTheDocument()
  })
})

describe('FatalBootErrorScreen', () => {
  it('should render error message and reload button', () => {
    render(<FatalBootErrorScreen message="Database corrupted" />)
    expect(screen.getByText('Failed to start')).toBeInTheDocument()
    expect(screen.getByText('Database corrupted')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Reload/ })).toBeInTheDocument()
  })
})

describe('BootGate', () => {
  it('should show splash during booting', () => {
    mockUseBootState.mockReturnValue({ status: 'booting' })
    render(<BootGate />)
    expect(screen.getByText(/Loading workspace/)).toBeInTheDocument()
  })

  it('should show fatal error screen', () => {
    mockUseBootState.mockReturnValue({ status: 'fatal', message: 'Something broke' })
    render(<BootGate />)
    expect(screen.getByText('Something broke')).toBeInTheDocument()
  })

  it('should render AppShell when ready', () => {
    mockUseBootState.mockReturnValue({ status: 'ready' })
    render(
      <ReactFlowProvider>
        <BootGate />
      </ReactFlowProvider>,
    )
    expect(screen.getByTestId('canvas-surface')).toBeInTheDocument()
  })

  it('should render AppShell when degraded', () => {
    mockUseBootState.mockReturnValue({
      status: 'degraded',
      reason: 'Memory fallback',
    })
    render(
      <ReactFlowProvider>
        <BootGate />
      </ReactFlowProvider>,
    )
    expect(screen.getByTestId('canvas-surface')).toBeInTheDocument()
  })
})
