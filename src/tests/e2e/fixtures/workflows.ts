import type { WorkflowDocument } from '../../../features/workflows/domain/workflow-types';

const now = new Date().toISOString();
const baseFields = {
  schemaVersion: 1,
  tags: [] as string[],
  createdAt: now,
  updatedAt: now,
};

export const emptyWorkflow: WorkflowDocument = {
  ...baseFields,
  id: 'test-empty-workflow',
  name: 'Empty Workflow',
  description: 'Fresh empty canvas for testing',
  nodes: [],
  edges: [],
  viewport: { x: 0, y: 0, zoom: 1 },
};

export const validShortFormWorkflow: WorkflowDocument = {
  ...baseFields,
  id: 'test-valid-short-form',
  name: 'Valid Short Form Workflow',
  description: '6 nodes fully connected for short-form video',
  nodes: [
    {
      id: 'user-prompt-1',
      type: 'user-prompt',
      label: 'User Prompt',
      position: { x: 100, y: 100 },
      config: { prompt: 'Create a viral tech review video about AI assistants' },
    },
    {
      id: 'script-writer-1',
      type: 'script-writer',
      label: 'Script Writer',
      position: { x: 400, y: 100 },
      config: { tone: 'casual', duration: 30 },
    },
    {
      id: 'scene-splitter-1',
      type: 'scene-splitter',
      label: 'Scene Splitter',
      position: { x: 700, y: 100 },
      config: { maxScenes: 5 },
    },
    {
      id: 'image-generator-1',
      type: 'image-generator',
      label: 'Image Generator',
      position: { x: 1000, y: 100 },
      config: { model: 'dall-e-3', aspectRatio: '16:9' },
    },
    {
      id: 'video-composer-1',
      type: 'video-composer',
      label: 'Video Composer',
      position: { x: 1300, y: 100 },
      config: { transition: 'fade', musicTrack: 'upbeat' },
    },
    {
      id: 'final-export-1',
      type: 'final-export',
      label: 'Final Export',
      position: { x: 1600, y: 100 },
      config: { format: 'mp4', resolution: '1080p' },
    },
  ],
  edges: [
    {
      id: 'edge-1',
      sourceNodeId: 'user-prompt-1',
      sourcePortKey: 'output',
      targetNodeId: 'script-writer-1',
      targetPortKey: 'input',
    },
    {
      id: 'edge-2',
      sourceNodeId: 'script-writer-1',
      sourcePortKey: 'output',
      targetNodeId: 'scene-splitter-1',
      targetPortKey: 'input',
    },
    {
      id: 'edge-3',
      sourceNodeId: 'scene-splitter-1',
      sourcePortKey: 'output',
      targetNodeId: 'image-generator-1',
      targetPortKey: 'input',
    },
    {
      id: 'edge-4',
      sourceNodeId: 'image-generator-1',
      sourcePortKey: 'output',
      targetNodeId: 'video-composer-1',
      targetPortKey: 'input',
    },
    {
      id: 'edge-5',
      sourceNodeId: 'video-composer-1',
      sourcePortKey: 'output',
      targetNodeId: 'final-export-1',
      targetPortKey: 'input',
    },
  ],
  viewport: { x: 0, y: 0, zoom: 0.8 },
};

export const savedBrokenConnectionWorkflow: WorkflowDocument = {
  ...baseFields,
  id: 'test-broken-connection',
  name: 'Broken Connection Workflow',
  description: 'Script writer output incorrectly connected to video composer',
  nodes: [
    {
      id: 'user-prompt-1',
      type: 'user-prompt',
      label: 'User Prompt',
      position: { x: 100, y: 100 },
      config: { prompt: 'Test broken connection' },
    },
    {
      id: 'script-writer-1',
      type: 'script-writer',
      label: 'Script Writer',
      position: { x: 400, y: 100 },
      config: { tone: 'professional', duration: 60 },
    },
    {
      id: 'video-composer-1',
      type: 'video-composer',
      label: 'Video Composer',
      position: { x: 700, y: 100 },
      config: { transition: 'cut' },
    },
  ],
  edges: [
    {
      id: 'broken-edge-1',
      sourceNodeId: 'user-prompt-1',
      sourcePortKey: 'output',
      targetNodeId: 'script-writer-1',
      targetPortKey: 'input',
    },
    {
      id: 'broken-edge-2',
      sourceNodeId: 'script-writer-1',
      sourcePortKey: 'output',
      targetNodeId: 'video-composer-1',
      targetPortKey: 'input',
    },
  ],
  viewport: { x: 0, y: 0, zoom: 1 },
};

export const failedSubtitleWorkflow: WorkflowDocument = {
  ...baseFields,
  id: 'test-failed-subtitle',
  name: 'Failed Subtitle Workflow',
  description: 'Subtitle formatter with validation failure',
  nodes: [
    {
      id: 'script-writer-1',
      type: 'script-writer',
      label: 'Script Writer',
      position: { x: 100, y: 100 },
      config: { tone: 'casual', duration: 30 },
    },
    {
      id: 'subtitle-formatter-1',
      type: 'subtitle-formatter',
      label: 'Subtitle Formatter',
      position: { x: 400, y: 100 },
      config: { maxCharsPerLine: 60, style: 'burned-in' },
    },
  ],
  edges: [
    {
      id: 'edge-1',
      sourceNodeId: 'script-writer-1',
      sourcePortKey: 'output',
      targetNodeId: 'subtitle-formatter-1',
      targetPortKey: 'input',
    },
  ],
  viewport: { x: 0, y: 0, zoom: 1 },
};

export const templateNarratedProductTeaser: WorkflowDocument = {
  ...baseFields,
  id: 'template-narrated-product-teaser',
  name: 'Narrated Product Teaser',
  description: '9 pre-connected nodes for product showcase',
  nodes: [
    {
      id: 'user-prompt-1',
      type: 'user-prompt',
      label: 'User Prompt',
      position: { x: 100, y: 100 },
      config: { prompt: 'Product teaser for wireless earbuds' },
    },
    {
      id: 'script-writer-1',
      type: 'script-writer',
      label: 'Script Writer',
      position: { x: 400, y: 100 },
      config: { tone: 'excited', duration: 45 },
    },
    {
      id: 'prompt-refiner-1',
      type: 'prompt-refiner',
      label: 'Prompt Refiner',
      position: { x: 700, y: 100 },
      config: { style: 'cinematic' },
    },
    {
      id: 'scene-splitter-1',
      type: 'scene-splitter',
      label: 'Scene Splitter',
      position: { x: 1000, y: 100 },
      config: { maxScenes: 3 },
    },
    {
      id: 'image-generator-1',
      type: 'image-generator',
      label: 'Image Generator',
      position: { x: 1300, y: 100 },
      config: { model: 'dall-e-3', aspectRatio: '9:16' },
    },
    {
      id: 'image-asset-mapper-1',
      type: 'image-asset-mapper',
      label: 'Image Asset Mapper',
      position: { x: 100, y: 400 },
      config: { naming: 'sequential' },
    },
    {
      id: 'video-composer-1',
      type: 'video-composer',
      label: 'Video Composer',
      position: { x: 400, y: 400 },
      config: { transition: 'slide', musicTrack: 'corporate' },
    },
    {
      id: 'tts-voiceover-1',
      type: 'tts-voiceover-planner',
      label: 'TTS Voiceover',
      position: { x: 700, y: 400 },
      config: { voice: 'alloy', speed: 1.0 },
    },
    {
      id: 'final-export-1',
      type: 'final-export',
      label: 'Final Export',
      position: { x: 1000, y: 400 },
      config: { format: 'mp4', resolution: '1080p', vertical: true },
    },
  ],
  edges: [
    { id: 'e1', sourceNodeId: 'user-prompt-1', sourcePortKey: 'output', targetNodeId: 'script-writer-1', targetPortKey: 'input' },
    { id: 'e2', sourceNodeId: 'script-writer-1', sourcePortKey: 'output', targetNodeId: 'prompt-refiner-1', targetPortKey: 'input' },
    { id: 'e3', sourceNodeId: 'prompt-refiner-1', sourcePortKey: 'output', targetNodeId: 'scene-splitter-1', targetPortKey: 'input' },
    { id: 'e4', sourceNodeId: 'scene-splitter-1', sourcePortKey: 'output', targetNodeId: 'image-generator-1', targetPortKey: 'input' },
    { id: 'e5', sourceNodeId: 'image-generator-1', sourcePortKey: 'output', targetNodeId: 'image-asset-mapper-1', targetPortKey: 'input' },
    { id: 'e6', sourceNodeId: 'image-asset-mapper-1', sourcePortKey: 'output', targetNodeId: 'video-composer-1', targetPortKey: 'input' },
    { id: 'e7', sourceNodeId: 'script-writer-1', sourcePortKey: 'output', targetNodeId: 'tts-voiceover-1', targetPortKey: 'input' },
    { id: 'e8', sourceNodeId: 'video-composer-1', sourcePortKey: 'output', targetNodeId: 'final-export-1', targetPortKey: 'input' },
    { id: 'e9', sourceNodeId: 'tts-voiceover-1', sourcePortKey: 'output', targetNodeId: 'final-export-1', targetPortKey: 'audio-input' },
  ],
  viewport: { x: 0, y: 0, zoom: 0.6 },
};
