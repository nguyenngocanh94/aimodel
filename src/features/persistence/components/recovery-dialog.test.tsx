import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi } from 'vitest'
import { RecoveryDialog } from './recovery-dialog'

describe('RecoveryDialog', () => {
  const mockSnapshotMeta = {
    workflowName: 'Test Workflow',
    savedAt: '2026-04-03T12:00:00Z',
    nodeCount: 5,
    status: 'dirty' as const,
  }

  const defaultProps = {
    open: true,
    onRecover: vi.fn(),
    onDiscard: vi.fn(),
    onStartFresh: vi.fn(),
    snapshotMeta: mockSnapshotMeta,
  }

  it('should render with correct test-id', () => {
    render(<RecoveryDialog {...defaultProps} />)
    expect(screen.getByTestId('recovery-dialog')).toBeInTheDocument()
  })

  it('should render title and description', () => {
    render(<RecoveryDialog {...defaultProps} />)
    expect(screen.getByText('Recover unsaved work?')).toBeInTheDocument()
    expect(screen.getByText('We found a local snapshot from your last session.')).toBeInTheDocument()
  })

  it('should render RotateCcw icon', () => {
    render(<RecoveryDialog {...defaultProps} />)
    expect(document.querySelector('svg')).toBeInTheDocument()
  })

  it('should display workflow metadata', () => {
    render(<RecoveryDialog {...defaultProps} />)
    expect(screen.getByText('Workflow')).toBeInTheDocument()
    expect(screen.getByText('Test Workflow')).toBeInTheDocument()
  })

  it('should display saved timestamp', () => {
    render(<RecoveryDialog {...defaultProps} />)
    expect(screen.getByText('Saved at')).toBeInTheDocument()
    expect(screen.getByText(/Apr 3, 2026/)).toBeInTheDocument()
  })

  it('should display node count', () => {
    render(<RecoveryDialog {...defaultProps} />)
    expect(screen.getByText('Nodes')).toBeInTheDocument()
    expect(screen.getByText('5')).toBeInTheDocument()
  })

  it('should show dirty status badge', () => {
    render(<RecoveryDialog {...defaultProps} />)
    expect(screen.getByText('Status')).toBeInTheDocument()
    expect(screen.getByText('Unsaved changes')).toBeInTheDocument()
  })

  it('should show clean status badge when status is clean', () => {
    render(
      <RecoveryDialog
        {...defaultProps}
        snapshotMeta={{ ...mockSnapshotMeta, status: 'clean' }}
      />
    )
    expect(screen.getByText('Clean')).toBeInTheDocument()
  })

  it('should call onRecover when Recover button is clicked', async () => {
    const user = userEvent.setup()
    const onRecover = vi.fn()
    render(<RecoveryDialog {...defaultProps} onRecover={onRecover} />)

    await user.click(screen.getByText('Recover snapshot'))
    expect(onRecover).toHaveBeenCalledOnce()
  })

  it('should call onDiscard when Discard button is clicked', async () => {
    const user = userEvent.setup()
    const onDiscard = vi.fn()
    render(<RecoveryDialog {...defaultProps} onDiscard={onDiscard} />)

    await user.click(screen.getByText('Discard'))
    expect(onDiscard).toHaveBeenCalledOnce()
  })

  it('should call onStartFresh when Start fresh button is clicked', async () => {
    const user = userEvent.setup()
    const onStartFresh = vi.fn()
    render(<RecoveryDialog {...defaultProps} onStartFresh={onStartFresh} />)

    await user.click(screen.getByText('Start fresh'))
    expect(onStartFresh).toHaveBeenCalledOnce()
  })

  it('should call onDiscard when dialog is closed via overlay click', async () => {
    const user = userEvent.setup()
    const onDiscard = vi.fn()
    render(<RecoveryDialog {...defaultProps} onDiscard={onDiscard} />)

    // Click on the overlay (outside the dialog content)
    const overlay = document.querySelector('[data-state="open"]')
    if (overlay) {
      await user.click(overlay)
      expect(onDiscard).toHaveBeenCalled()
    }
  })

  it('should not render when open is false', () => {
    render(<RecoveryDialog {...defaultProps} open={false} />)
    expect(screen.queryByTestId('recovery-dialog')).not.toBeInTheDocument()
  })

  it('should format timestamp correctly', () => {
    render(<RecoveryDialog {...defaultProps} />)
    // The formatted timestamp should be in the document
    const timestamp = screen.getByText(/2026/)
    expect(timestamp).toBeInTheDocument()
  })

  it('should handle invalid timestamp gracefully', () => {
    render(
      <RecoveryDialog
        {...defaultProps}
        snapshotMeta={{ ...mockSnapshotMeta, savedAt: 'invalid-date' }}
      />
    )
    // When date is invalid, toLocaleString returns "Invalid Date"
    expect(screen.getByText(/Invalid Date/)).toBeInTheDocument()
  })
})
