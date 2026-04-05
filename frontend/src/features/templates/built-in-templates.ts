/**
 * Built-in workflow templates - AiModel-537.1
 * Per plan section 17
 */

import type { WorkflowDocument, WorkflowNode, WorkflowEdge } from '@/features/workflows/domain/workflow-types'
import { getTemplate } from '@/features/node-registry/node-registry'
import { CURRENT_SCHEMA_VERSION } from '@/features/workflows/data/workflow-migrations'

// ============================================================
// Template interface
// ============================================================

export interface WorkflowTemplate {
  readonly id: string
  readonly name: string
  readonly description: string
  readonly tags: readonly string[]
  readonly templateVersion: string
  readonly registryVersion: string
  readonly minimumWorkflowSchemaVersion: number
  readonly nodes: readonly WorkflowNode[]
  readonly edges: readonly WorkflowEdge[]
}

export interface TemplateValidationResult {
  readonly valid: boolean
  readonly errors: readonly string[]
}

// ============================================================
// Template validation
// ============================================================

export function validateTemplate(template: WorkflowTemplate): TemplateValidationResult {
  const errors: string[] = []

  // Check all node types exist in registry
  for (const node of template.nodes) {
    const tmpl = getTemplate(node.type)
    if (!tmpl) {
      errors.push(`Node "${node.id}" references unknown type "${node.type}"`)
    }
  }

  // Check all edge references are valid
  const nodeIds = new Set(template.nodes.map((n) => n.id))
  for (const edge of template.edges) {
    if (!nodeIds.has(edge.sourceNodeId)) {
      errors.push(`Edge "${edge.id}" references non-existent source "${edge.sourceNodeId}"`)
    }
    if (!nodeIds.has(edge.targetNodeId)) {
      errors.push(`Edge "${edge.id}" references non-existent target "${edge.targetNodeId}"`)
    }
  }

  // Check schema version
  if (template.minimumWorkflowSchemaVersion > CURRENT_SCHEMA_VERSION) {
    errors.push(
      `Template requires schema v${template.minimumWorkflowSchemaVersion}, ` +
      `current is v${CURRENT_SCHEMA_VERSION}`,
    )
  }

  return { valid: errors.length === 0, errors }
}

// ============================================================
// Template instantiation
// ============================================================

let idCounter = 0

function generateId(prefix: string): string {
  idCounter++
  return `${prefix}-${Date.now()}-${idCounter}`
}

/** Reset counter for testing */
export function resetIdCounter(): void {
  idCounter = 0
}

export function instantiateTemplate(template: WorkflowTemplate): WorkflowDocument {
  const validation = validateTemplate(template)
  if (!validation.valid) {
    throw new Error(`Template validation failed: ${validation.errors.join('; ')}`)
  }

  const now = new Date().toISOString()
  return {
    id: generateId('wf'),
    schemaVersion: CURRENT_SCHEMA_VERSION,
    name: template.name,
    description: template.description,
    tags: [...template.tags],
    nodes: template.nodes.map((n) => ({ ...n })),
    edges: template.edges.map((e) => ({ ...e })),
    viewport: { x: 0, y: 0, zoom: 1 },
    createdAt: now,
    updatedAt: now,
    basedOnTemplateId: template.id,
    basedOnTemplateVersion: template.templateVersion,
  }
}

// ============================================================
// NarratedStoryVideo template
// ============================================================

export const narratedStoryVideoTemplate: WorkflowTemplate = {
  id: 'tpl-narrated-story-video',
  name: 'Narrated Story Video',
  description: 'Full pipeline: prompt → script → scenes → visuals → voiceover → subtitles → composed video → export.',
  tags: ['story', 'narrated', 'full-pipeline'],
  templateVersion: '1.0.0',
  registryVersion: '1.0.0',
  minimumWorkflowSchemaVersion: 1,
  nodes: [
    {
      id: 'tpl-nsv-prompt',
      type: 'userPrompt',
      label: 'Creative Brief',
      position: { x: 0, y: 200 },
      config: {
        topic: 'The Journey of a Drop of Water',
        goal: 'Tell a compelling visual story about the water cycle',
        audience: 'General audience, ages 12+',
        tone: 'cinematic',
        durationSeconds: 90,
      },
    },
    {
      id: 'tpl-nsv-script',
      type: 'scriptWriter',
      label: 'Script Writer',
      position: { x: 300, y: 200 },
      config: {
        style: 'Evocative, visual narration with poetic language',
        structure: 'three_act',
        includeHook: true,
        includeCTA: false,
        targetDurationSeconds: 90,
      },
    },
    {
      id: 'tpl-nsv-scenes',
      type: 'sceneSplitter',
      label: 'Scene Splitter',
      position: { x: 600, y: 200 },
      config: {
        sceneCountTarget: 6,
        maxSceneDurationSeconds: 20,
        includeShotIntent: true,
        includeVisualPromptHints: true,
      },
    },
    {
      id: 'tpl-nsv-images',
      type: 'imageGenerator',
      label: 'Image Generator',
      position: { x: 900, y: 100 },
      config: {
        inputMode: 'scenes',
        outputMode: 'frames',
        stylePreset: 'cinematic',
        resolution: '1024x1024',
        seedStrategy: 'deterministic',
      },
    },
    {
      id: 'tpl-nsv-mapper',
      type: 'imageAssetMapper',
      label: 'Asset Mapper',
      position: { x: 1200, y: 100 },
      config: {
        assetRole: 'auto',
        namingPattern: 'scene-{index}-asset',
      },
    },
    {
      id: 'tpl-nsv-tts',
      type: 'ttsVoiceoverPlanner',
      label: 'Voiceover Planner',
      position: { x: 900, y: 300 },
      config: {
        voiceStyle: 'warm',
        pace: 'normal',
        genderStyle: 'neutral',
        includePauses: true,
      },
    },
    {
      id: 'tpl-nsv-subs',
      type: 'subtitleFormatter',
      label: 'Subtitle Formatter',
      position: { x: 1200, y: 400 },
      config: {
        maxCharsPerLine: 42,
        linesPerCard: 2,
        stylePreset: 'default',
        burnMode: 'soft',
      },
    },
    {
      id: 'tpl-nsv-composer',
      type: 'videoComposer',
      label: 'Video Composer',
      position: { x: 1500, y: 200 },
      config: {
        aspectRatio: '16:9',
        transitionStyle: 'fade',
        fps: 30,
        includeTitleCard: true,
        musicBed: 'none',
      },
    },
    {
      id: 'tpl-nsv-export',
      type: 'finalExport',
      label: 'Final Export',
      position: { x: 1800, y: 200 },
      config: {
        fileNamePattern: '{name}-{date}-{resolution}',
        includeMetadata: true,
        includeWorkflowSpecReference: false,
      },
    },
  ],
  edges: [
    { id: 'tpl-nsv-e1', sourceNodeId: 'tpl-nsv-prompt', sourcePortKey: 'prompt', targetNodeId: 'tpl-nsv-script', targetPortKey: 'prompt' },
    { id: 'tpl-nsv-e2', sourceNodeId: 'tpl-nsv-script', sourcePortKey: 'script', targetNodeId: 'tpl-nsv-scenes', targetPortKey: 'script' },
    { id: 'tpl-nsv-e3', sourceNodeId: 'tpl-nsv-scenes', sourcePortKey: 'scenes', targetNodeId: 'tpl-nsv-images', targetPortKey: 'sceneList' },
    { id: 'tpl-nsv-e4', sourceNodeId: 'tpl-nsv-images', sourcePortKey: 'imageFrameList', targetNodeId: 'tpl-nsv-mapper', targetPortKey: 'imageFrameList' },
    { id: 'tpl-nsv-e5', sourceNodeId: 'tpl-nsv-mapper', sourcePortKey: 'imageAssetList', targetNodeId: 'tpl-nsv-composer', targetPortKey: 'visualAssets' },
    { id: 'tpl-nsv-e6', sourceNodeId: 'tpl-nsv-script', sourcePortKey: 'script', targetNodeId: 'tpl-nsv-tts', targetPortKey: 'script' },
    { id: 'tpl-nsv-e7', sourceNodeId: 'tpl-nsv-tts', sourcePortKey: 'audioPlan', targetNodeId: 'tpl-nsv-composer', targetPortKey: 'audioPlan' },
    { id: 'tpl-nsv-e8', sourceNodeId: 'tpl-nsv-script', sourcePortKey: 'script', targetNodeId: 'tpl-nsv-subs', targetPortKey: 'script' },
    { id: 'tpl-nsv-e9', sourceNodeId: 'tpl-nsv-tts', sourcePortKey: 'audioPlan', targetNodeId: 'tpl-nsv-subs', targetPortKey: 'audioPlan' },
    { id: 'tpl-nsv-e10', sourceNodeId: 'tpl-nsv-subs', sourcePortKey: 'subtitleAsset', targetNodeId: 'tpl-nsv-composer', targetPortKey: 'subtitleAsset' },
    { id: 'tpl-nsv-e11', sourceNodeId: 'tpl-nsv-composer', sourcePortKey: 'videoAsset', targetNodeId: 'tpl-nsv-export', targetPortKey: 'videoAsset' },
  ],
}

// ============================================================
// ProductLaunchTeaser template
// ============================================================

export const productLaunchTeaserTemplate: WorkflowTemplate = {
  id: 'tpl-product-launch-teaser',
  name: 'Product Launch Teaser',
  description: 'Short teaser: prompt → script → scenes → refined prompts → images → video → export.',
  tags: ['product', 'teaser', 'marketing'],
  templateVersion: '1.0.0',
  registryVersion: '1.0.0',
  minimumWorkflowSchemaVersion: 1,
  nodes: [
    {
      id: 'tpl-plt-prompt',
      type: 'userPrompt',
      label: 'Product Brief',
      position: { x: 0, y: 200 },
      config: {
        topic: 'Smart Home Energy Monitor - EcoTrack Pro',
        goal: 'Create excitement for product launch with sleek visuals',
        audience: 'Tech-savvy homeowners, 25-45',
        tone: 'dramatic',
        durationSeconds: 30,
      },
    },
    {
      id: 'tpl-plt-script',
      type: 'scriptWriter',
      label: 'Teaser Script',
      position: { x: 300, y: 200 },
      config: {
        style: 'Punchy, high-energy product reveal with bold statements',
        structure: 'hook_only',
        includeHook: true,
        includeCTA: true,
        targetDurationSeconds: 30,
      },
    },
    {
      id: 'tpl-plt-scenes',
      type: 'sceneSplitter',
      label: 'Shot List',
      position: { x: 600, y: 200 },
      config: {
        sceneCountTarget: 4,
        maxSceneDurationSeconds: 10,
        includeShotIntent: true,
        includeVisualPromptHints: true,
      },
    },
    {
      id: 'tpl-plt-refiner',
      type: 'promptRefiner',
      label: 'Visual Refiner',
      position: { x: 900, y: 100 },
      config: {
        visualStyle: 'cinematic',
        cameraLanguage: 'standard',
        aspectRatio: '16:9',
        consistencyNotes: 'Sleek, modern tech aesthetic. Cool blue tones.',
        negativePromptEnabled: true,
      },
    },
    {
      id: 'tpl-plt-images',
      type: 'imageGenerator',
      label: 'Image Generator',
      position: { x: 1200, y: 100 },
      config: {
        inputMode: 'prompts',
        outputMode: 'assets',
        stylePreset: 'cinematic',
        resolution: '1024x1024',
        seedStrategy: 'deterministic',
      },
    },
    {
      id: 'tpl-plt-composer',
      type: 'videoComposer',
      label: 'Video Composer',
      position: { x: 1500, y: 200 },
      config: {
        aspectRatio: '16:9',
        transitionStyle: 'cut',
        fps: 30,
        includeTitleCard: true,
        musicBed: 'none',
      },
    },
    {
      id: 'tpl-plt-export',
      type: 'finalExport',
      label: 'Export',
      position: { x: 1800, y: 200 },
      config: {
        fileNamePattern: '{name}-teaser-{date}',
        includeMetadata: true,
        includeWorkflowSpecReference: false,
      },
    },
  ],
  edges: [
    { id: 'tpl-plt-e1', sourceNodeId: 'tpl-plt-prompt', sourcePortKey: 'prompt', targetNodeId: 'tpl-plt-script', targetPortKey: 'prompt' },
    { id: 'tpl-plt-e2', sourceNodeId: 'tpl-plt-script', sourcePortKey: 'script', targetNodeId: 'tpl-plt-scenes', targetPortKey: 'script' },
    { id: 'tpl-plt-e3', sourceNodeId: 'tpl-plt-scenes', sourcePortKey: 'scenes', targetNodeId: 'tpl-plt-refiner', targetPortKey: 'sceneList' },
    { id: 'tpl-plt-e4', sourceNodeId: 'tpl-plt-refiner', sourcePortKey: 'promptList', targetNodeId: 'tpl-plt-images', targetPortKey: 'promptList' },
    { id: 'tpl-plt-e5', sourceNodeId: 'tpl-plt-images', sourcePortKey: 'imageAssetList', targetNodeId: 'tpl-plt-composer', targetPortKey: 'visualAssets' },
    { id: 'tpl-plt-e6', sourceNodeId: 'tpl-plt-composer', sourcePortKey: 'videoAsset', targetNodeId: 'tpl-plt-export', targetPortKey: 'videoAsset' },
  ],
}

// ============================================================
// EducationalExplainer template
// ============================================================

export const educationalExplainerTemplate: WorkflowTemplate = {
  id: 'tpl-educational-explainer',
  name: 'Educational Explainer',
  description: 'Educational content: prompt → script → scenes → images → voiceover → subtitles → review → video → export.',
  tags: ['educational', 'explainer', 'tutorial'],
  templateVersion: '1.0.0',
  registryVersion: '1.0.0',
  minimumWorkflowSchemaVersion: 1,
  nodes: [
    {
      id: 'tpl-ee-prompt',
      type: 'userPrompt',
      label: 'Topic Input',
      position: { x: 0, y: 250 },
      config: {
        topic: 'How Photosynthesis Works',
        goal: 'Explain the process clearly with vivid analogies',
        audience: 'Middle school students, ages 11-14',
        tone: 'educational',
        durationSeconds: 120,
      },
    },
    {
      id: 'tpl-ee-script',
      type: 'scriptWriter',
      label: 'Lesson Script',
      position: { x: 300, y: 250 },
      config: {
        style: 'Friendly, approachable teacher voice with analogies and examples',
        structure: 'three_act',
        includeHook: true,
        includeCTA: false,
        targetDurationSeconds: 120,
      },
    },
    {
      id: 'tpl-ee-scenes',
      type: 'sceneSplitter',
      label: 'Scene Planner',
      position: { x: 600, y: 250 },
      config: {
        sceneCountTarget: 8,
        maxSceneDurationSeconds: 20,
        includeShotIntent: true,
        includeVisualPromptHints: true,
      },
    },
    {
      id: 'tpl-ee-images',
      type: 'imageGenerator',
      label: 'Illustration Gen',
      position: { x: 900, y: 150 },
      config: {
        inputMode: 'scenes',
        outputMode: 'frames',
        stylePreset: 'illustration',
        resolution: '1024x1024',
        seedStrategy: 'deterministic',
      },
    },
    {
      id: 'tpl-ee-mapper',
      type: 'imageAssetMapper',
      label: 'Asset Mapper',
      position: { x: 1200, y: 150 },
      config: {
        assetRole: 'auto',
        namingPattern: 'lesson-{index}',
      },
    },
    {
      id: 'tpl-ee-tts',
      type: 'ttsVoiceoverPlanner',
      label: 'Voiceover Plan',
      position: { x: 900, y: 350 },
      config: {
        voiceStyle: 'warm',
        pace: 'slow',
        genderStyle: 'neutral',
        includePauses: true,
      },
    },
    {
      id: 'tpl-ee-subs',
      type: 'subtitleFormatter',
      label: 'Subtitles',
      position: { x: 1200, y: 450 },
      config: {
        maxCharsPerLine: 38,
        linesPerCard: 2,
        stylePreset: 'default',
        burnMode: 'soft',
      },
    },
    {
      id: 'tpl-ee-review',
      type: 'reviewCheckpoint',
      label: 'Content Review',
      position: { x: 1500, y: 250 },
      config: {
        reviewLabel: 'Review Educational Content',
        instructions: 'Verify accuracy, age-appropriateness, and visual clarity before final composition.',
        blocking: true,
        reviewType: 'script',
      },
    },
    {
      id: 'tpl-ee-composer',
      type: 'videoComposer',
      label: 'Video Composer',
      position: { x: 1800, y: 250 },
      config: {
        aspectRatio: '16:9',
        transitionStyle: 'fade',
        fps: 30,
        includeTitleCard: true,
        musicBed: 'none',
      },
    },
    {
      id: 'tpl-ee-export',
      type: 'finalExport',
      label: 'Export',
      position: { x: 2100, y: 250 },
      config: {
        fileNamePattern: '{name}-explainer-{date}',
        includeMetadata: true,
        includeWorkflowSpecReference: true,
      },
    },
  ],
  edges: [
    { id: 'tpl-ee-e1', sourceNodeId: 'tpl-ee-prompt', sourcePortKey: 'prompt', targetNodeId: 'tpl-ee-script', targetPortKey: 'prompt' },
    { id: 'tpl-ee-e2', sourceNodeId: 'tpl-ee-script', sourcePortKey: 'script', targetNodeId: 'tpl-ee-scenes', targetPortKey: 'script' },
    { id: 'tpl-ee-e3', sourceNodeId: 'tpl-ee-scenes', sourcePortKey: 'scenes', targetNodeId: 'tpl-ee-images', targetPortKey: 'sceneList' },
    { id: 'tpl-ee-e4', sourceNodeId: 'tpl-ee-images', sourcePortKey: 'imageFrameList', targetNodeId: 'tpl-ee-mapper', targetPortKey: 'imageFrameList' },
    { id: 'tpl-ee-e5', sourceNodeId: 'tpl-ee-mapper', sourcePortKey: 'imageAssetList', targetNodeId: 'tpl-ee-composer', targetPortKey: 'visualAssets' },
    { id: 'tpl-ee-e6', sourceNodeId: 'tpl-ee-script', sourcePortKey: 'script', targetNodeId: 'tpl-ee-tts', targetPortKey: 'script' },
    { id: 'tpl-ee-e7', sourceNodeId: 'tpl-ee-tts', sourcePortKey: 'audioPlan', targetNodeId: 'tpl-ee-composer', targetPortKey: 'audioPlan' },
    { id: 'tpl-ee-e8', sourceNodeId: 'tpl-ee-script', sourcePortKey: 'script', targetNodeId: 'tpl-ee-subs', targetPortKey: 'script' },
    { id: 'tpl-ee-e9', sourceNodeId: 'tpl-ee-tts', sourcePortKey: 'audioPlan', targetNodeId: 'tpl-ee-subs', targetPortKey: 'audioPlan' },
    { id: 'tpl-ee-e10', sourceNodeId: 'tpl-ee-subs', sourcePortKey: 'subtitleAsset', targetNodeId: 'tpl-ee-composer', targetPortKey: 'subtitleAsset' },
    // Review checkpoint receives script for review
    { id: 'tpl-ee-e11', sourceNodeId: 'tpl-ee-script', sourcePortKey: 'script', targetNodeId: 'tpl-ee-review', targetPortKey: 'script' },
    { id: 'tpl-ee-e12', sourceNodeId: 'tpl-ee-composer', sourcePortKey: 'videoAsset', targetNodeId: 'tpl-ee-export', targetPortKey: 'videoAsset' },
  ],
}

// ============================================================
// Registry
// ============================================================

export const builtInTemplates: readonly WorkflowTemplate[] = [
  narratedStoryVideoTemplate,
  productLaunchTeaserTemplate,
  educationalExplainerTemplate,
]

export function getBuiltInTemplate(id: string): WorkflowTemplate | undefined {
  return builtInTemplates.find((t) => t.id === id)
}
