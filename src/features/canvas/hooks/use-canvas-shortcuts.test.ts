import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook } from '@testing-library/react'
import { useCanvasShortcuts, SHORTCUT_DEFINITIONS, type CanvasShortcutsOptions } from './use-canvas-shortcuts'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'

function fireKey(key: string, opts: Partial<KeyboardEventInit> = {}) {
  window.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, ...opts }))
}

function fireKeyUp(key: string, opts: Partial<KeyboardEventInit> = {}) {
  window.dispatchEvent(new KeyboardEvent('keyup', { key, bubbles: true, ...opts }))
}

function renderShortcuts(options: Partial<CanvasShortcutsOptions> = {}) {
  return renderHook(() =>
    useCanvasShortcuts({
      onDuplicate: vi.fn(),
      onDelete: vi.fn(),
      onFitView: vi.fn(),
      onSelectAll: vi.fn(),
      onUndo: vi.fn(),
      onRedo: vi.fn(),
      onSave: vi.fn(),
      onExport: vi.fn(),
      onQuickAdd: vi.fn(),
      onInspect: vi.fn(),
      onRunNode: vi.fn(),
      onRunWorkflow: vi.fn(),
      onConnect: vi.fn(),
      onPanModeStart: vi.fn(),
      onPanModeEnd: vi.fn(),
      onEscape: vi.fn(),
      ...options,
    }),
  )
}

describe('useCanvasShortcuts', () => {
  beforeEach(() => {
    useWorkflowStore.setState({
      selectedNodeIds: ['n1'],
      selectedEdgeId: null,
    })
  })

  it('should call onSave on Cmd+S', () => {
    const onSave = vi.fn()
    renderShortcuts({ onSave })
    fireKey('s', { metaKey: true })
    expect(onSave).toHaveBeenCalledOnce()
  })

  it('should call onExport on Cmd+Shift+E', () => {
    const onExport = vi.fn()
    renderShortcuts({ onExport })
    fireKey('e', { metaKey: true, shiftKey: true })
    expect(onExport).toHaveBeenCalledOnce()
  })

  it('should call onUndo on Cmd+Z', () => {
    const onUndo = vi.fn()
    renderShortcuts({ onUndo })
    fireKey('z', { metaKey: true })
    expect(onUndo).toHaveBeenCalledOnce()
  })

  it('should call onRedo on Cmd+Shift+Z', () => {
    const onRedo = vi.fn()
    renderShortcuts({ onRedo })
    fireKey('z', { metaKey: true, shiftKey: true })
    expect(onRedo).toHaveBeenCalledOnce()
  })

  it('should call onDelete on Backspace', () => {
    const onDelete = vi.fn()
    renderShortcuts({ onDelete })
    fireKey('Backspace')
    expect(onDelete).toHaveBeenCalledOnce()
  })

  it('should call onDelete on Delete', () => {
    const onDelete = vi.fn()
    renderShortcuts({ onDelete })
    fireKey('Delete')
    expect(onDelete).toHaveBeenCalledOnce()
  })

  it('should not call onDelete when nothing selected', () => {
    useWorkflowStore.setState({ selectedNodeIds: [], selectedEdgeId: null })
    const onDelete = vi.fn()
    renderShortcuts({ onDelete })
    fireKey('Backspace')
    expect(onDelete).not.toHaveBeenCalled()
  })

  it('should call onPanModeStart on Space', () => {
    const onPanModeStart = vi.fn()
    renderShortcuts({ onPanModeStart })
    fireKey(' ')
    expect(onPanModeStart).toHaveBeenCalledOnce()
  })

  it('should call onPanModeEnd on Space keyup', () => {
    const onPanModeEnd = vi.fn()
    renderShortcuts({ onPanModeEnd })
    fireKeyUp(' ')
    expect(onPanModeEnd).toHaveBeenCalledOnce()
  })

  it('should call onQuickAdd on A', () => {
    const onQuickAdd = vi.fn()
    renderShortcuts({ onQuickAdd })
    fireKey('a')
    expect(onQuickAdd).toHaveBeenCalledOnce()
  })

  it('should call onInspect on Enter when node selected', () => {
    const onInspect = vi.fn()
    renderShortcuts({ onInspect })
    fireKey('Enter')
    expect(onInspect).toHaveBeenCalledOnce()
  })

  it('should call onRunNode on R when single node selected', () => {
    const onRunNode = vi.fn()
    renderShortcuts({ onRunNode })
    fireKey('r')
    expect(onRunNode).toHaveBeenCalledOnce()
  })

  it('should not call onRunNode when no node selected', () => {
    useWorkflowStore.setState({ selectedNodeIds: [] })
    const onRunNode = vi.fn()
    renderShortcuts({ onRunNode })
    fireKey('r')
    expect(onRunNode).not.toHaveBeenCalled()
  })

  it('should call onRunWorkflow on Shift+R', () => {
    const onRunWorkflow = vi.fn()
    renderShortcuts({ onRunWorkflow })
    fireKey('R', { shiftKey: true })
    expect(onRunWorkflow).toHaveBeenCalledOnce()
  })

  it('should call onConnect on C when node selected', () => {
    const onConnect = vi.fn()
    renderShortcuts({ onConnect })
    fireKey('c')
    expect(onConnect).toHaveBeenCalledOnce()
  })

  it('should not call onConnect when no node selected', () => {
    useWorkflowStore.setState({ selectedNodeIds: [] })
    const onConnect = vi.fn()
    renderShortcuts({ onConnect })
    fireKey('c')
    expect(onConnect).not.toHaveBeenCalled()
  })

  it('should call onEscape on Escape', () => {
    const onEscape = vi.fn()
    renderShortcuts({ onEscape })
    fireKey('Escape')
    expect(onEscape).toHaveBeenCalledOnce()
  })

  it('should call onDuplicate on Cmd+D', () => {
    const onDuplicate = vi.fn()
    renderShortcuts({ onDuplicate })
    fireKey('d', { metaKey: true })
    expect(onDuplicate).toHaveBeenCalledOnce()
  })

  it('should not fire plain-key shortcuts when disabled', () => {
    const onQuickAdd = vi.fn()
    renderShortcuts({ onQuickAdd, enabled: false })
    fireKey('a')
    expect(onQuickAdd).not.toHaveBeenCalled()
  })

  it('should have 15 documented shortcuts', () => {
    expect(SHORTCUT_DEFINITIONS).toHaveLength(15)
  })
})
