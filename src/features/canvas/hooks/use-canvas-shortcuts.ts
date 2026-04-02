import { useEffect, useCallback } from 'react'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'

export interface CanvasShortcutsOptions {
  onDuplicate?: () => void
  onDelete?: () => void
  onFitView?: () => void
  onSelectAll?: () => void
  onUndo?: () => void
  onRedo?: () => void
  onSave?: () => void
  onExport?: () => void
  onQuickAdd?: () => void
  onInspect?: () => void
  onRunNode?: () => void
  onRunWorkflow?: () => void
  onEscape?: () => void
  enabled?: boolean
}

/**
 * Keyboard shortcut definitions for documentation/help display.
 */
export const SHORTCUT_DEFINITIONS = [
  { key: 'Cmd/Ctrl + S', action: 'Save workflow' },
  { key: 'Cmd/Ctrl + Shift + E', action: 'Export workflow JSON' },
  { key: 'Cmd/Ctrl + Z', action: 'Undo' },
  { key: 'Cmd/Ctrl + Shift + Z', action: 'Redo' },
  { key: 'Delete / Backspace', action: 'Delete selection' },
  { key: 'A', action: 'Quick add node' },
  { key: 'Enter', action: 'Inspect selected' },
  { key: 'R', action: 'Run selected node' },
  { key: 'Shift + R', action: 'Run workflow' },
  { key: 'Escape', action: 'Clear selection' },
  { key: 'Cmd/Ctrl + D', action: 'Duplicate selection' },
  { key: 'Cmd/Ctrl + 0', action: 'Fit view' },
  { key: 'Cmd/Ctrl + A', action: 'Select all' },
] as const

/**
 * Check if the event target is an interactive text element where we should
 * not intercept plain key presses (a, r, etc.).
 */
function isTextInputFocused(event: KeyboardEvent): boolean {
  const target = event.target
  if (target instanceof HTMLInputElement) {
    const type = target.type
    return type === 'text' || type === 'search' || type === 'url' || type === 'email' || type === 'number' || type === 'password' || type === 'tel'
  }
  if (target instanceof HTMLTextAreaElement) return true
  if (target instanceof HTMLElement && target.isContentEditable) return true
  return false
}

/**
 * useCanvasShortcuts - Keyboard shortcuts per plan section 6.9
 *
 * Modifier shortcuts (Cmd/Ctrl + key) work everywhere except text inputs.
 * Plain key shortcuts (A, R, Enter, Escape) are disabled in text inputs.
 */
export function useCanvasShortcuts(options: CanvasShortcutsOptions = {}) {
  const {
    onDuplicate,
    onDelete,
    onFitView,
    onSelectAll,
    onUndo,
    onRedo,
    onSave,
    onExport,
    onQuickAdd,
    onInspect,
    onRunNode,
    onRunWorkflow,
    onEscape,
    enabled = true,
  } = options
  const selectedNodeIds = useWorkflowStore((state) => state.selectedNodeIds)
  const selectedEdgeId = useWorkflowStore((state) => state.selectedEdgeId)

  const handleKeyDown = useCallback(
    (event: KeyboardEvent) => {
      if (!enabled) return

      const textFocused = isTextInputFocused(event)
      const isCtrlOrCmd = event.ctrlKey || event.metaKey

      // === Modifier shortcuts (work even in text inputs for standard actions) ===

      // Save: Cmd/Ctrl + S
      if (isCtrlOrCmd && event.key === 's' && !event.shiftKey) {
        event.preventDefault()
        onSave?.()
        return
      }

      // Export: Cmd/Ctrl + Shift + E
      if (isCtrlOrCmd && event.key === 'e' && event.shiftKey) {
        event.preventDefault()
        onExport?.()
        return
      }

      // Undo: Cmd/Ctrl + Z (without Shift)
      if (isCtrlOrCmd && event.key === 'z' && !event.shiftKey) {
        event.preventDefault()
        onUndo?.()
        return
      }

      // Redo: Cmd/Ctrl + Shift + Z
      if (isCtrlOrCmd && event.key === 'z' && event.shiftKey) {
        event.preventDefault()
        onRedo?.()
        return
      }

      // Duplicate: Cmd/Ctrl + D
      if (isCtrlOrCmd && event.key === 'd') {
        event.preventDefault()
        if (selectedNodeIds.length > 0) {
          onDuplicate?.()
        }
        return
      }

      // Fit view: Cmd/Ctrl + 0
      if (isCtrlOrCmd && event.key === '0') {
        event.preventDefault()
        onFitView?.()
        return
      }

      // Select all: Cmd/Ctrl + A
      if (isCtrlOrCmd && event.key === 'a') {
        event.preventDefault()
        onSelectAll?.()
        return
      }

      // === Plain key shortcuts (disabled in text inputs) ===
      if (textFocused) return

      // Delete: Delete or Backspace
      if (event.key === 'Delete' || event.key === 'Backspace') {
        if (selectedNodeIds.length > 0 || selectedEdgeId) {
          event.preventDefault()
          onDelete?.()
        }
        return
      }

      // Quick add: A
      if (event.key === 'a' || event.key === 'A') {
        if (!isCtrlOrCmd) {
          event.preventDefault()
          onQuickAdd?.()
        }
        return
      }

      // Inspect selected: Enter
      if (event.key === 'Enter') {
        if (selectedNodeIds.length === 1 || selectedEdgeId) {
          event.preventDefault()
          onInspect?.()
        }
        return
      }

      // Run selected node: R (without shift)
      if (event.key === 'r' && !event.shiftKey) {
        if (selectedNodeIds.length === 1) {
          event.preventDefault()
          onRunNode?.()
        }
        return
      }

      // Run workflow: Shift + R
      if (event.key === 'R' && event.shiftKey) {
        event.preventDefault()
        onRunWorkflow?.()
        return
      }

      // Escape: clear selection / close menus
      if (event.key === 'Escape') {
        event.preventDefault()
        onEscape?.()
        return
      }
    },
    [
      enabled,
      selectedNodeIds,
      selectedEdgeId,
      onDuplicate,
      onDelete,
      onFitView,
      onSelectAll,
      onUndo,
      onRedo,
      onSave,
      onExport,
      onQuickAdd,
      onInspect,
      onRunNode,
      onRunWorkflow,
      onEscape,
    ],
  )

  useEffect(() => {
    if (!enabled) return

    window.addEventListener('keydown', handleKeyDown)
    return () => {
      window.removeEventListener('keydown', handleKeyDown)
    }
  }, [enabled, handleKeyDown])

  return {
    selectedCount: selectedNodeIds.length,
  }
}
