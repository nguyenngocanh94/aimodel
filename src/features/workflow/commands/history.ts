import { useWorkflowStore } from '@/features/workflow/store/workflow-store';

/**
 * Undo the last authoring operation.
 */
export function undo(): void {
  useWorkflowStore.getState().undo();
}

/**
 * Redo the last undone operation.
 */
export function redo(): void {
  useWorkflowStore.getState().redo();
}

/**
 * Check if undo is available.
 */
export function canUndo(): boolean {
  return useWorkflowStore.getState().past.length > 0;
}

/**
 * Check if redo is available.
 */
export function canRedo(): boolean {
  return useWorkflowStore.getState().future.length > 0;
}
