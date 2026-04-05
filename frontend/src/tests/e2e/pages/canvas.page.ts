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

export class CanvasPage {
  constructor(private readonly page: Page) {}

  async dragNode(label: string, point: Point): Promise<void> {
    const searchInput = this.page.getByTestId('node-search-input');
    await searchInput.fill(label);
    
    const nodeItem = this.page.getByTestId(`node-library-item-${label}`);
    const canvasArea = this.page.locator('.react-flow__pane');
    
    await nodeItem.dragTo(canvasArea, {
      targetPosition: { x: point.x, y: point.y },
    });
  }

  async selectNode(nodeId: string): Promise<void> {
    const nodeCard = this.page.getByTestId(`node-card-${nodeId}`);
    await nodeCard.click();
  }

  async connectPorts({
    sourceNodeId,
    sourcePortKey,
    targetNodeId,
    targetPortKey,
  }: PortConnection): Promise<void> {
    const sourcePort = this.page.getByTestId(
      `node-port-out-${sourceNodeId}-${sourcePortKey}`
    );
    const targetPort = this.page.getByTestId(
      `node-port-in-${targetNodeId}-${targetPortKey}`
    );

    await sourcePort.dragTo(targetPort);
  }

  async deleteSelection(): Promise<void> {
    await this.page.keyboard.press('Delete');
  }

  async selectEdge(edgeId: string): Promise<void> {
    const edge = this.page.getByTestId(`edge-${edgeId}`);
    await edge.click();
  }
}
