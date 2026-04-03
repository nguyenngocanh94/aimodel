import type { Page } from '@playwright/test';

interface Point {
  x: number;
  y: number;
}

interface PortConnection {
  sourceNodeId: string;
  sourcePortKey: string;
  targetNodeId: string;
  targetPortKey: string;
}

export async function dragNodeToCanvas(
  page: Page,
  nodeLabel: string,
  point: Point
): Promise<void> {
  const searchInput = page.getByTestId('node-search-input');
  await searchInput.fill(nodeLabel);

  const nodeItem = page.getByTestId(`node-library-item-${nodeLabel}`);
  const canvasArea = page.locator('.react-flow__pane');

  await nodeItem.dragTo(canvasArea, {
    targetPosition: { x: point.x, y: point.y },
  });
}

export async function connectPorts(
  page: Page,
  {
    sourceNodeId,
    sourcePortKey,
    targetNodeId,
    targetPortKey,
  }: PortConnection
): Promise<void> {
  const sourcePort = page.getByTestId(
    `node-port-out-${sourceNodeId}-${sourcePortKey}`
  );
  const targetPort = page.getByTestId(
    `node-port-in-${targetNodeId}-${targetPortKey}`
  );

  await sourcePort.dragTo(targetPort);
}
