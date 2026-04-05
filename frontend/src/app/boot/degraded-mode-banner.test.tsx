import { render, screen } from '@testing-library/react'
import { describe, it, expect, vi } from 'vitest'

const mockUseBootState = vi.fn()
vi.mock('./boot-provider', () => ({
  useBootState: () => mockUseBootState(),
}))

import { DegradedModeBanner } from './degraded-mode-banner'

describe('DegradedModeBanner', () => {
  it('should not render when boot is ready', () => {
    mockUseBootState.mockReturnValue({ status: 'ready' })
    const { container } = render(<DegradedModeBanner />)
    expect(container.innerHTML).toBe('')
  })

  it('should render warning when degraded', () => {
    mockUseBootState.mockReturnValue({
      status: 'degraded',
      reason: 'IndexedDB unavailable',
    })
    render(<DegradedModeBanner />)
    expect(screen.getByTestId('degraded-mode-banner')).toBeInTheDocument()
    expect(screen.getByText(/Degraded mode/)).toBeInTheDocument()
    expect(screen.getByText(/IndexedDB unavailable/)).toBeInTheDocument()
  })

  it('should show export button when handler provided', () => {
    mockUseBootState.mockReturnValue({
      status: 'degraded',
      reason: 'test',
    })
    const onExport = vi.fn()
    render(<DegradedModeBanner onExport={onExport} />)
    expect(screen.getByText('Export')).toBeInTheDocument()
  })

  it('should show retry button when handler provided', () => {
    mockUseBootState.mockReturnValue({
      status: 'degraded',
      reason: 'test',
    })
    const onRetry = vi.fn()
    render(<DegradedModeBanner onRetry={onRetry} />)
    expect(screen.getByText('Retry')).toBeInTheDocument()
  })

  it('should not render during booting', () => {
    mockUseBootState.mockReturnValue({ status: 'booting' })
    const { container } = render(<DegradedModeBanner />)
    expect(container.innerHTML).toBe('')
  })
})
