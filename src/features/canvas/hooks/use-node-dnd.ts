import { useCallback } from 'react';

interface UseNodeDndOptions {
  onNodeAdd?: (nodeType: string, position: { x: number; y: number }) => void;
}

interface DragItem {
  type: string;
  templateType: string;
}

/**
 * useNodeDnd - Hook for drag-and-drop node creation
 *
 * Handles dragging nodes from the library panel onto the canvas.
 */
export function useNodeDnd(options: UseNodeDndOptions = {}) {
  const { onNodeAdd } = options;

  const onDragStart = useCallback((event: React.DragEvent, nodeType: string) => {
    const dragData: DragItem = {
      type: 'node',
      templateType: nodeType,
    };
    event.dataTransfer.setData('application/json', JSON.stringify(dragData));
    event.dataTransfer.effectAllowed = 'copy';
  }, []);

  const onDragOver = useCallback((event: React.DragEvent) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'copy';
  }, []);

  const onDrop = useCallback(
    (
      event: React.DragEvent,
      canvasRect: DOMRect,
      transform: { x: number; y: number; zoom: number },
    ) => {
      event.preventDefault();

      const data = event.dataTransfer.getData('application/json');
      if (!data) return;

      try {
        const dragItem: DragItem = JSON.parse(data);
        if (dragItem.type !== 'node') return;

        // Calculate drop position in canvas coordinates
        const x =
          (event.clientX - canvasRect.left - transform.x) / transform.zoom;
        const y =
          (event.clientY - canvasRect.top - transform.y) / transform.zoom;

        onNodeAdd?.(dragItem.templateType, { x, y });
      } catch {
        // Invalid drag data, ignore
      }
    },
    [onNodeAdd],
  );

  return {
    onDragStart,
    onDragOver,
    onDrop,
  };
}

/**
 * Helper to check if a drag event contains a node
 */
export function isNodeDragEvent(event: React.DragEvent): boolean {
  const data = event.dataTransfer.getData('application/json');
  if (!data) return false;

  try {
    const dragItem: DragItem = JSON.parse(data);
    return dragItem.type === 'node';
  } catch {
    return false;
  }
}
