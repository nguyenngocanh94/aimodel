import { z } from 'zod';
import type {
  PortDefinition,
  PortPayload,
} from '@/features/workflows/domain/workflow-types';

// Import all node templates for registration
import {
  userPromptTemplate,
  scriptWriterTemplate,
  sceneSplitterTemplate,
  promptRefinerTemplate,
  imageGeneratorTemplate,
  imageAssetMapperTemplate,
  ttsVoiceoverPlannerTemplate,
  subtitleFormatterTemplate,
  videoComposerTemplate,
  reviewCheckpointTemplate,
  humanGateTemplate,
  finalExportTemplate,
  wanR2VTemplate,
  wanI2VTemplate,
  wanImageEditTemplate,
  wanVideoEditTemplate,
  productImageInputTemplate,
  divergeTemplate,
  productAnalyzerTemplate,
  trendResearcherTemplate,
  storyWriterTemplate,
  telegramTriggerTemplate,
  telegramDeliverTemplate,
} from './templates';

/**
 * Node Template Contract - AiModel-9wx.2
 * Defines the discriminated union type for node templates
 * with executable vs non-executable variants
 */

// ============================================================
// Node Fixture Definition
// ============================================================

export interface NodeFixture<TConfig> {
  readonly id: string;
  readonly label: string;
  readonly config?: Partial<TConfig>;
  readonly previewInputs?: Readonly<Record<string, PortPayload>>;
  readonly executionInputs?: Readonly<Record<string, PortPayload>>;
}

// ============================================================
// Mock Execution Arguments
// ============================================================

export interface MockNodeExecutionArgs<TConfig> {
  readonly nodeId: string;
  readonly config: Readonly<TConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
  readonly signal: AbortSignal;
  readonly runId: string;
}

// ============================================================
// Node Template Base (shared properties)
// ============================================================

interface NodeTemplateBase<TConfig> {
  readonly type: string;
  readonly templateVersion: string;
  readonly title: string;
  readonly category:
    | 'input'
    | 'script'
    | 'visuals'
    | 'audio'
    | 'video'
    | 'utility'
    | 'output';
  readonly description: string;
  readonly inputs: readonly PortDefinition[];
  readonly outputs: readonly PortDefinition[];
  readonly defaultConfig: Readonly<TConfig>;
  /** Parses/validates config output `TConfig`; input may include undefined for `.default()` fields. */
  readonly configSchema: z.ZodType<TConfig, z.ZodTypeDef, unknown>;
  readonly fixtures: readonly NodeFixture<TConfig>[];
  readonly buildPreview: (args: {
    readonly config: Readonly<TConfig>;
    readonly inputs: Readonly<Record<string, PortPayload>>;
  }) => Readonly<Record<string, PortPayload>>;
}

// ============================================================
// Discriminated Union: Non-executable vs Executable
// ============================================================

export type NodeTemplate<TConfig> =
  | (NodeTemplateBase<TConfig> & {
      readonly executable: false;
      readonly mockExecute?: never;
    })
  | (NodeTemplateBase<TConfig> & {
      readonly executable: true;
      readonly mockExecute: (
        args: MockNodeExecutionArgs<TConfig>,
      ) => Promise<Readonly<Record<string, PortPayload>>>;
    });

// ============================================================
// Type Guards
// ============================================================

export function isExecutableNode<TConfig>(
  template: NodeTemplate<TConfig>
): template is NodeTemplate<TConfig> & { executable: true; mockExecute: (args: MockNodeExecutionArgs<TConfig>) => Promise<Readonly<Record<string, PortPayload>>> } {
  return template.executable === true;
}

export function isNonExecutableNode<TConfig>(
  template: NodeTemplate<TConfig>
): template is NodeTemplate<TConfig> & { executable: false; mockExecute?: never } {
  return template.executable === false;
}

// ============================================================
// Node Registry — AiModel-9wx.15 (singleton + class implementation)
// ============================================================

/**
 * Template metadata for library rendering
 */
export interface TemplateMetadata {
  readonly type: string;
  readonly title: string;
  readonly description: string;
  readonly category: NodeTemplate<unknown>['category'];
  readonly inputs: readonly PortDefinition[];
  readonly outputs: readonly PortDefinition[];
  readonly executable: boolean;
  readonly fixtureCount: number;
}

/**
 * Mutable registry of node templates. One instance is created at module load;
 * all templates are registered in {@link registerAllNodeTemplates}.
 */
export class NodeTemplateRegistry {
  private readonly templates = new Map<string, NodeTemplate<unknown>>();

  register<TConfig>(template: NodeTemplate<TConfig>): void {
    this.templates.set(template.type, template as NodeTemplate<unknown>);
  }

  get<TConfig = unknown>(type: string): NodeTemplate<TConfig> | undefined {
    return this.templates.get(type) as NodeTemplate<TConfig> | undefined;
  }

  getAll(): readonly NodeTemplate<unknown>[] {
    return Array.from(this.templates.values());
  }

  getByCategory(
    category: NodeTemplate<unknown>['category'],
  ): readonly NodeTemplate<unknown>[] {
    return this.getAll().filter((template) => template.category === category);
  }

  getMetadata(): readonly TemplateMetadata[] {
    return this.getAll().map((template) => ({
      type: template.type,
      title: template.title,
      description: template.description,
      category: template.category,
      inputs: template.inputs,
      outputs: template.outputs,
      executable: template.executable,
      fixtureCount: template.fixtures.length,
    }));
  }

  has(type: string): boolean {
    return this.templates.has(type);
  }

  get size(): number {
    return this.templates.size;
  }
}

const nodeTemplateRegistry = new NodeTemplateRegistry();

function registerAllNodeTemplates(registry: NodeTemplateRegistry): void {
  registry.register(userPromptTemplate);
  registry.register(scriptWriterTemplate);
  registry.register(sceneSplitterTemplate);
  registry.register(promptRefinerTemplate);
  registry.register(imageGeneratorTemplate);
  registry.register(imageAssetMapperTemplate);
  registry.register(ttsVoiceoverPlannerTemplate);
  registry.register(subtitleFormatterTemplate);
  registry.register(videoComposerTemplate);
  registry.register(reviewCheckpointTemplate);
  registry.register(humanGateTemplate);
  registry.register(finalExportTemplate);
  registry.register(wanR2VTemplate);
  registry.register(wanI2VTemplate);
  registry.register(wanImageEditTemplate);
  registry.register(wanVideoEditTemplate);
  registry.register(productImageInputTemplate);
  registry.register(divergeTemplate);
  registry.register(productAnalyzerTemplate);
  registry.register(trendResearcherTemplate);
  registry.register(storyWriterTemplate);
  registry.register(telegramTriggerTemplate);
  registry.register(telegramDeliverTemplate);
}

registerAllNodeTemplates(nodeTemplateRegistry);

/**
 * Singleton registry instance (auto-populated on import).
 */
export { nodeTemplateRegistry };

/**
 * Get a template by its type identifier
 * @param type - The template type string
 * @returns The template or undefined if not found
 */
export function getTemplate<TConfig = unknown>(type: string): NodeTemplate<TConfig> | undefined {
  return nodeTemplateRegistry.get<TConfig>(type);
}

/**
 * Get all registered templates
 * @returns Array of all templates
 */
export function getAllTemplates(): readonly NodeTemplate<unknown>[] {
  return nodeTemplateRegistry.getAll();
}

/**
 * Get templates filtered by category
 * @param category - The category to filter by
 * @returns Array of templates in the category
 */
export function getTemplatesByCategory(
  category: NodeTemplate<unknown>['category'],
): readonly NodeTemplate<unknown>[] {
  return nodeTemplateRegistry.getByCategory(category);
}

/**
 * Get metadata for all templates (for library rendering)
 * @returns Array of template metadata
 */
export function getTemplateMetadata(): readonly TemplateMetadata[] {
  return nodeTemplateRegistry.getMetadata();
}

/**
 * Check if a template type is registered
 * @param type - The template type string
 * @returns True if registered
 */
export function hasTemplate(type: string): boolean {
  return nodeTemplateRegistry.has(type);
}

/**
 * Get count of registered templates
 * @returns Number of templates
 */
export function getTemplateCount(): number {
  return nodeTemplateRegistry.size;
}
