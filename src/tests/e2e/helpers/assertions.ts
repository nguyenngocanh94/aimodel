import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

type NodeState = 'selected' | 'running' | 'invalid' | 'stale';
type EdgeState = 'selected' | 'invalid';
type MediaKind = 'image' | 'video' | 'audio';

export async function verifyNodeState(
  page: Page,
  nodeId: string,
  state: NodeState
): Promise<void> {
  const nodeCard = page.getByTestId(`node-card-${nodeId}`);
  
  switch (state) {
    case 'selected':
      await expect(nodeCard).toHaveAttribute('data-selected', 'true');
      break;
    case 'running':
      await expect(nodeCard).toHaveAttribute('data-running', 'true');
      break;
    case 'invalid':
      await expect(nodeCard).toHaveAttribute('data-invalid', 'true');
      break;
    case 'stale':
      await expect(nodeCard).toHaveAttribute('data-stale', 'true');
      break;
  }
}

export async function verifyMediaPreview(
  page: Page,
  nodeId: string,
  kind: MediaKind
): Promise<void> {
  const nodeCard = page.getByTestId(`node-card-${nodeId}`);
  
  switch (kind) {
    case 'image': {
      const thumbnailGrid = nodeCard.locator('[data-testid="image-thumbnail-grid"]');
      await expect(thumbnailGrid).toBeVisible();
      break;
    }
    case 'video': {
      const videoPoster = nodeCard.locator('[data-testid="video-poster"]');
      const playButton = nodeCard.locator('[data-testid="video-play-button"]');
      await expect(videoPoster).toBeVisible();
      await expect(playButton).toBeVisible();
      break;
    }
    case 'audio': {
      const audioPlayer = nodeCard.locator('[data-testid="audio-player"]');
      await expect(audioPlayer).toBeVisible();
      break;
    }
  }
}

export async function verifyEdgeState(
  page: Page,
  edgeId: string,
  state: EdgeState
): Promise<void> {
  const edge = page.getByTestId(`edge-${edgeId}`);
  
  switch (state) {
    case 'selected':
      await expect(edge).toHaveAttribute('data-selected', 'true');
      break;
    case 'invalid':
      await expect(edge).toHaveAttribute('data-invalid', 'true');
      break;
  }
}
