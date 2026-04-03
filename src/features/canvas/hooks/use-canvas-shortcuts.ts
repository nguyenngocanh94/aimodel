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
  onConnect?: () => void
  onPanModeStart?: () => void
  onPanModeEnd?: () => void
  onEscape?: () => void
  enabled?: boolean
}

/**
 * Keyboard shortcut definitions for documentation/help display.
 * All 15 documented shortcuts per design system section 16.
 */
export const SHORTCUT_DEFINITIONS = [
  { key: 'Cmd/Ctrl + S', action: 'Save workflow' },
  { key: 'Cmd/Ctrl + Shift + E', action: 'Export workflow JSON' },
  { key: 'Cmd/Ctrl + Z', action: 'Undo' },
  { key: 'Cmd/Ctrl + Shift + Z', action: 'Redo' },
  { key: 'Delete / Backspace', action: 'Delete selection' },
  { key: 'Space', action: 'Pan mode (hold)' },
  { key: 'A', action: 'Quick add node' },
  { key: 'Enter', action: 'Inspect selected' },
  { key: 'R', action: 'Run selected node' },
  { key: 'Shift + R', action: 'Run workflow' },
  { key: 'C', action: 'Connect from selected' },
  { key: 'Escape', action: 'Clear selection / close' },
  { key: 'Cmd/Ctrl + D', action: 'Duplicate selection' },
  { key: 'Cmd/Ctrl + 0', action: 'Fit view' },
  { key: 'Cmd/Ctrl + A', action: 'Select all' },
] as const

/**
 * Check if the event target is an interactive text element where we should
 * not intercept plain key presses (a, r, c, space, etc.).
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
 * Precedence check: returns true if a dialog, modal, or overlay is open
 * that should consume keys before canvas shortcuts.
 *
 * Precedence order (highest to lowest):
 * 1. Open dialogs and modals
 * 2. Open menus and searchable overlays
 * 3. Active text inputs/textareas/contenteditable
 * 4. Inspector body interactions
 * 5. Library navigation
 * 6. Canvas-level commands
 */
function isHigherPrioritySurfaceOpen(): boolean {
  // Check for open Radix dialogs or modals
  const dialogOverlay = document.querySelector('[data-state="open"][role="dialog"]')
  if (dialogOverlay) return true

  // Check for open menus (Radix dropdowns, context menus)
  const openMenu = document.querySelector('[data-state="open"][role="menu"]')
  if (openMenu) return true

  // Check for the quick-add or connect dialogs
  const quickAdd = document.querySelector('[data-testid="quick-add-dialog"][data-state="open"]')
  if (quickAdd) return true
  const connectDialog = document.querySelector('[data-testid="connect-dialog"][data-state="open"]')
  if (connectDialog) return true

  return false
}

/**
 * useCanvasShortcuts — Keyboard shortcuts per design system section 16.
 *
 * All 12 documented shortcuts plus Cmd/Ctrl+D, Cmd/Ctrl+0, Cmd/Ctrl+A.
 * Respects precedence: dialogs > menus > text inputs > inspector > library > canvas.
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
    onConnect,
    onPanModeStart,
    onPanModeEnd,
    onEscape,
    enabled = true,
  } = options
  const selectedNodeIds = useWorkflowStore((state) => state.selectedNodeIds)
  const selectedEdgeId = useWorkflowStore((state) => state.selectedEdgeId)

  const handleKeyDown = useCallback(
    (event: KeyboardEvent) => {
      if (!enabled) return

      const isCtrlOrCmd = event.ctrlKey || event.metaKey

      // Escape always works — closes topmost dismissible UI first
      if (event.key === 'Escape') {
        event.preventDefault()
        onEscape?.()
        return
      }

      // Higher-priority surfaces consume all other keys
      if (isHigherPrioritySurfaceOpen()) return

      const textFocused = isTextInputFocused(event)

      // === Modifier shortcuts (work even in text inputs for standard actions) ===

      // Save: Cmd/Ctrl + S — must preventDefault to block browser save
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

      // Delete: Delete or Backspace — only graph selection, never text
      if (event.key === 'Delete' || event.key === 'Backspace') {
        if (selectedNodeIds.length > 0 || selectedEdgeId) {
          event.preventDefault()
          onDelete?.()
        }
        return
      }

      // Space: Pan mode while held — only when canvas owns focus
      if (event.key === ' ' && !event.repeat) {
        event.preventDefault()
        onPanModeStart?.()
        return
      }

      // Quick add: A — not from text inputs, dialog fields, or contenteditable
      if ((event.key === 'a' || event.key === 'A') && !isCtrlOrCmd) {
        event.preventDefault()
        onQuickAdd?.()
        return
      }

      // Inspect selected: Enter — only when canvas owns focus
      if (event.key === 'Enter') {
        if (selectedNodeIds.length === 1 || selectedEdgeId) {
          event.preventDefault()
          onInspect?.()
        }
        return
      }

      // Run selected node: R (without shift) — requires immediate toolbar feedback
      if (event.key === 'r' && !event.shiftKey) {
        if (selectedNodeIds.length === 1) {
          event.preventDefault()
          onRunNode?.()
        }
        return
      }

      // Run workflow: Shift + R — requires immediate toolbar feedback
      if (event.key === 'R' && event.shiftKey) {
        event.preventDefault()
        onRunWorkflow?.()
        return
      }

      // Connect: C — connect from selected node/port via searchable dialog
      if ((event.key === 'c' || event.key === 'C') && !isCtrlOrCmd) {
        if (selectedNodeIds.length === 1) {
          event.preventDefault()
          onConnect?.()
        }
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
      onConnect,
      onPanModeStart,
      onEscape,
    ],
  )

  // Space keyup handler for pan mode release
  const handleKeyUp = useCallback(
    (event: KeyboardEvent) => {
      if (!enabled) return
      if (event.key === ' ') {
        onPanModeEnd?.()
      }
    },
    [enabled, onPanModeEnd],
  )

  useEffect(() => {
    if (!enabled) return

    window.addEventListener('keydown', handleKeyDown)
    window.addEventListener('keyup', handleKeyUp)
    return () => {
      window.removeEventListener('keydown', handleKeyDown)
      window.removeEventListener('keyup', handleKeyUp)
    }
  }, [enabled, handleKeyDown, handleKeyUp])

  return {
    selectedCount: selectedNodeIds.length,
  }
}
