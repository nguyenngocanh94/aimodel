import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, it, expect, vi } from 'vitest'
import { CanvasEmptyState } from './canvas-empty-state'
import { builtInTemplates } from '@/features/templates/built-in-templates'
import { SHORTCUT_DEFINITIONS } from '@/features/canvas/hooks/use-canvas-shortcuts'

describe('CanvasEmptyState', () => {
  it('should render the header', () => {
    render(<CanvasEmptyState />)
    expect(screen.getByText('Create your first workflow')).toBeInTheDocument()
  })

  it('should render all built-in templates', () => {
    render(<CanvasEmptyState />)
    for (const template of builtInTemplates) {
      expect(screen.getByTestId(`template-${template.id}`)).toBeInTheDocument()
      expect(screen.getByText(template.name)).toBeInTheDocument()
    }
  })

  it('should call onSelectTemplate when a template is clicked', async () => {
    const user = userEvent.setup()
    const onSelectTemplate = vi.fn()
    render(<CanvasEmptyState onSelectTemplate={onSelectTemplate} />)

    const firstTemplate = builtInTemplates[0]
    await user.click(screen.getByTestId(`template-${firstTemplate.id}`))
    expect(onSelectTemplate).toHaveBeenCalledWith(firstTemplate)
  })

  it('should render "Add first node" button', () => {
    render(<CanvasEmptyState />)
    expect(screen.getByText('Add first node')).toBeInTheDocument()
  })

  it('should call onAddNode when button is clicked', async () => {
    const user = userEvent.setup()
    const onAddNode = vi.fn()
    render(<CanvasEmptyState onAddNode={onAddNode} />)

    await user.click(screen.getByText('Add first node'))
    expect(onAddNode).toHaveBeenCalledOnce()
  })

  it('should render keyboard shortcuts (up to 8)', () => {
    render(<CanvasEmptyState />)
    const shown = SHORTCUT_DEFINITIONS.slice(0, 8)
    for (const { action } of shown) {
      expect(screen.getByText(action)).toBeInTheDocument()
    }
  })

  it('should render template tags', () => {
    render(<CanvasEmptyState />)
    const firstTemplate = builtInTemplates[0]
    const templateEl = screen.getByTestId(`template-${firstTemplate.id}`)
    for (const tag of firstTemplate.tags) {
      expect(within(templateEl).getByText(tag)).toBeInTheDocument()
    }
  })

  it('should have correct test-id on root', () => {
    render(<CanvasEmptyState />)
    expect(screen.getByTestId('canvas-empty-state')).toBeInTheDocument()
  })
})
