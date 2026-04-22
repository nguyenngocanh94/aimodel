/**
 * storyWriter Node Template - AiModel-624
 *
 * Purpose: Core creative node for Vietnamese GenZ story-driven TVC scripts.
 *          Takes product analysis + trend brief + model roster + optional seed idea.
 *          Writes human story arcs (not product pitches) localized for Vietnamese GenZ.
 *          Outputs: story arc (multi-shot), cast selection, formula, tone, sound direction.
 * Category: script
 *
 * Works with Diverge node for multi-LLM compete pattern.
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture, MockNodeExecutionArgs } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Story Arc Payload - Vietnamese GenZ TVC Story Structure
// ============================================================

export interface StoryShot {
  readonly shotNumber: number;
  readonly duration: number; // seconds
  readonly sceneDescription: string;
  readonly cameraAngle: string;
  readonly action: string;
  readonly dialogue: string;
  readonly productIntegration: string;
  readonly emotionTarget: string;
}

export interface CastSelection {
  readonly modelId: string;
  readonly modelName: string;
  readonly role: 'lead' | 'supporting' | 'extra';
  readonly characterDescription: string;
  readonly wardrobe: string;
  readonly makeup: string;
}

export interface StoryArcPayload {
  readonly title: string;
  readonly theme: string;
  readonly targetDuration: number;
  readonly shots: readonly StoryShot[];
  readonly cast: readonly CastSelection[];
  readonly formula: string;
  readonly toneDirection: string;
  readonly soundDirection: string;
  readonly vietnameseLocalization: {
    readonly culturalReferences: readonly string[];
    readonly genZSlang: readonly string[];
    readonly regionalSetting: string;
  };
}

// ============================================================
// Configuration Schema
// ============================================================

export const StoryWriterConfigSchema = z.object({
  targetDurationSeconds: z.number().int().min(15).max(120)
    .describe('Target TVC duration in seconds (15-120s)'),
  storyFormula: z.enum([
    'hero_journey',
    'problem_agitation_solution',
    'before_after_transformation',
    'day_in_life',
    'social_proof_story',
    'emotional_hook',
  ]).describe('Story formula/framework to use'),
  emotionalTone: z.enum([
    'aspirational',
    'relatable_humor',
    'nostalgic',
    'empowering',
    'fomo_urgency',
    'warm_family',
  ]).describe('Primary emotional tone for the story'),
  productIntegrationStyle: z.enum([
    'subtle_background',
    'natural_use',
    'hero_moment',
    'transformation_reveal',
    'comparison_story',
  ]).describe('How the product appears in the story'),
  genZAuthenticity: z.enum(['low', 'medium', 'high', 'ultra'])
    .describe('Level of GenZ authenticity (slang, references, style)'),
  includeCasting: z.boolean()
    .describe('Whether to include model/casting recommendations'),
  vietnameseDialect: z.enum(['northern', 'central', 'southern', 'neutral'])
    .describe('Vietnamese dialect preference for dialogue'),
  seedIdea: z.string().max(500).optional()
    .describe('Optional seed idea or direction from user'),
});

export type StoryWriterConfig = z.infer<typeof StoryWriterConfigSchema>;

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [
  {
    key: 'productAnalysis',
    label: 'Product Analysis',
    direction: 'input',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'Structured product analysis from ProductAnalyzer node',
  },
  {
    key: 'trendBrief',
    label: 'Trend Brief',
    direction: 'input',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'Trend research and cultural context from TrendResearcher',
  },
  {
    key: 'modelRoster',
    label: 'Model Roster',
    direction: 'input',
    dataType: 'json',
    required: false,
    multiple: false,
    description: 'Available models/talent for casting recommendations',
  },
  {
    key: 'seedIdea',
    label: 'Seed Idea',
    direction: 'input',
    dataType: 'text',
    required: false,
    multiple: false,
    description: 'Optional user-provided creative direction or seed concept',
  },
];

const outputs: readonly PortDefinition[] = [
  {
    key: 'storyArc',
    label: 'Story Arc',
    direction: 'output',
    dataType: 'json',
    required: true,
    multiple: false,
    description: 'Complete story arc with multi-shot breakdown, cast selection, formula, tone and sound direction',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: StoryWriterConfig = {
  targetDurationSeconds: 30,
  storyFormula: 'problem_agitation_solution',
  emotionalTone: 'relatable_humor',
  productIntegrationStyle: 'natural_use',
  genZAuthenticity: 'high',
  includeCasting: true,
  vietnameseDialect: 'neutral',
  seedIdea: '',
};

// ============================================================
// Deterministic Helpers
// ============================================================

function stableHash(input: string): string {
  let h = 5381;
  for (let i = 0; i < input.length; i++) {
    h = Math.imul(31, h) + input.charCodeAt(i);
  }
  return (h >>> 0).toString(16).padStart(8, '0');
}

function pickIndex(seedHex: string, modulo: number, salt: number): number {
  const n = Number.parseInt(seedHex.slice(0, 8), 16) ^ salt;
  return Math.abs(n) % modulo;
}

// ============================================================
// Story Building Data
// ============================================================

const storyFormulas = {
  hero_journey: {
    name: "Hero's Journey",
    structure: ['ordinary_world', 'call_to_adventure', 'mentor_product', 'transformation', 'return_gift'],
    description: 'Classic hero arc where product empowers transformation',
  },
  problem_agitation_solution: {
    name: 'Problem-Agitation-Solution',
    structure: ['pain_point', 'amplify_frustration', 'product_entrance', 'solution_demo', 'relief_payoff'],
    description: 'Agitate a pain point then solve with product',
  },
  before_after_transformation: {
    name: 'Before-After Transformation',
    structure: ['before_state', 'trigger_moment', 'product_use', 'after_reveal', 'social_proof'],
    description: 'Clear transformation story with product as catalyst',
  },
  day_in_life: {
    name: 'Day in the Life',
    structure: ['morning_routine', 'pain_encounter', 'seamless_solution', 'better_day', 'share_moment'],
    description: 'Natural integration into daily routine',
  },
  social_proof_story: {
    name: 'Social Proof Story',
    structure: ['peer_recommendation', 'skepticism', 'try_product', 'surprise_delight', 'advocacy'],
    description: 'FOMO-driven peer influence narrative',
  },
  emotional_hook: {
    name: 'Emotional Hook',
    structure: ['emotional_opening', 'story_unfold', 'connection_build', 'product_embrace', 'feelgood_close'],
    description: 'Emotion-first storytelling',
  },
};

const genZVietnameseSlang = [
  'Xin xo', 'Chill thoi', 'U là trời', 'Gét gô', 'Khóc', 'Thả thính',
  'FYP', 'Cap', 'No cap', 'Vibe check', 'Main character', 'Slay',
  'Mid', 'Fire', 'Iconic', 'Rent free', 'Tea', 'Bop',
  'Gu', 'Trend', 'Xịn', 'Chất', 'Đỉnh', 'Gây bão', 'Viral',
];

const vietnameseCulturalRefs = [
  'Phố cổ Hội An', 'Cà phê sáng', 'Xe máy culture', 'Chợ phiên',
  'Mâm cơm gia đình', 'Tết holiday', 'Mid-Autumn Festival',
  'Bánh mì culture', 'Street food vibes', 'Sài Gòn hustle',
  'Hà Nội autumn', 'Biển Đà Nẵng', 'Đồng bằng sông Cửu Long',
];

const cameraAngles = [
  'Close-up emotional', 'Wide establishing', 'Over-shoulder',
  'POV first-person', 'Low angle empowerment', 'Dutch angle tension',
  'Macro product detail', 'Tracking movement', 'Static lifestyle',
  'Aerial drone wide', 'Handheld authentic', 'Smooth gimbal',
];

const modelPool: readonly CastSelection[] = [
  { modelId: 'model-001', modelName: 'Linh Chi', role: 'lead', characterDescription: 'Confident GenZ professional', wardrobe: 'Smart casual with trendy accessories', makeup: 'Natural glow with bold lip' },
  { modelId: 'model-002', modelName: 'Minh Anh', role: 'lead', characterDescription: 'Relatable every day hero', wardrobe: 'Comfy streetwear', makeup: 'Fresh minimal' },
  { modelId: 'model-003', modelName: 'Huyền Trang', role: 'supporting', characterDescription: 'Best friend energy', wardrobe: 'Casual chic', makeup: 'Playful colors' },
  { modelId: 'model-004', modelName: 'Đức Phát', role: 'lead', characterDescription: 'Young aspirational male', wardrobe: 'Clean athleisure', makeup: 'Groomed natural' },
  { modelId: 'model-005', modelName: 'Khánh Vy', role: 'supporting', characterDescription: 'Fashion-forward friend', wardrobe: 'Statement pieces', makeup: 'Editorial bold' },
];

// ============================================================
// Story Arc Builder
// ============================================================

function buildStoryArc(args: {
  readonly config: Readonly<StoryWriterConfig>;
  readonly productAnalysis: unknown;
  readonly trendBrief: unknown;
  readonly modelRoster: unknown;
  readonly seedIdea: string;
}): StoryArcPayload {
  const { config, productAnalysis, trendBrief, seedIdea } = args;
  
  const seed = stableHash(JSON.stringify({
    config,
    productAnalysis,
    trendBrief,
    seedIdea,
  }));

  const formula = storyFormulas[config.storyFormula];
  const shotCount = Math.max(3, Math.min(8, Math.floor(config.targetDurationSeconds / 5)));
  
  // Build shots based on formula structure
  const shots: StoryShot[] = [];
  const secondsPerShot = Math.floor(config.targetDurationSeconds / shotCount);
  
  for (let i = 0; i < shotCount; i++) {
    const beatPhase = formula.structure[i % formula.structure.length];
    const angle = cameraAngles[pickIndex(seed + i, cameraAngles.length, i * 7)];
    
    shots.push({
      shotNumber: i + 1,
      duration: secondsPerShot,
      sceneDescription: `${beatPhase} scene - ${i === 0 ? 'Opening' : i === shotCount - 1 ? 'Closing' : 'Development'}`,
      cameraAngle: angle,
      action: `Action for ${beatPhase}`,
      dialogue: i % 2 === 0 ? '' : 'GenZ dialogue placeholder',
      productIntegration: config.productIntegrationStyle,
      emotionTarget: config.emotionalTone,
    });
  }

  // Select cast from model roster or default pool
  const castSelections: CastSelection[] = [];
  if (config.includeCasting) {
    const availableModels = Array.isArray(args.modelRoster) && args.modelRoster.length > 0
      ? args.modelRoster as CastSelection[]
      : modelPool;
    
    const leadCount = 1 + pickIndex(seed, 2, 3);
    for (let i = 0; i < Math.min(leadCount + 1, availableModels.length); i++) {
      const model = availableModels[pickIndex(seed, availableModels.length, i * 11)];
      if (model) {
        castSelections.push({
          ...model,
          role: i === 0 ? 'lead' : 'supporting',
        });
      }
    }
  }

  // Select GenZ slang and cultural references
  const slangCount = config.genZAuthenticity === 'ultra' ? 5 : 
                     config.genZAuthenticity === 'high' ? 3 : 
                     config.genZAuthenticity === 'medium' ? 2 : 0;
  
  const selectedSlang: string[] = [];
  for (let i = 0; i < slangCount; i++) {
    selectedSlang.push(genZVietnameseSlang[pickIndex(seed, genZVietnameseSlang.length, i * 13)]);
  }

  const culturalCount = 2 + pickIndex(seed, 3, 5);
  const selectedCultural: string[] = [];
  for (let i = 0; i < culturalCount; i++) {
    selectedCultural.push(vietnameseCulturalRefs[pickIndex(seed, vietnameseCulturalRefs.length, i * 17)]);
  }

  return {
    title: seedIdea || `${formula.name} Story - ${config.emotionalTone}`,
    theme: formula.description,
    targetDuration: config.targetDurationSeconds,
    shots,
    cast: castSelections,
    formula: formula.name,
    toneDirection: config.emotionalTone,
    soundDirection: `${config.emotionalTone} audio with Vietnamese GenZ trending sounds`,
    vietnameseLocalization: {
      culturalReferences: selectedCultural,
      genZSlang: [...new Set(selectedSlang)],
      regionalSetting: config.vietnameseDialect,
    },
  };
}

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<StoryWriterConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config, inputs } = args;
  
  const productAnalysis = inputs.productAnalysis?.value;
  const trendBrief = inputs.trendBrief?.value;
  const modelRoster = inputs.modelRoster?.value;
  const seedIdea = inputs.seedIdea?.value ? String(inputs.seedIdea.value) : config.seedIdea || '';

  if (!productAnalysis || !trendBrief) {
    return {
      storyArc: {
        value: null,
        status: 'idle',
        schemaType: 'json',
        previewText: 'Waiting for product analysis and trend brief...',
      } as PortPayload,
    };
  }

  const storyArc = buildStoryArc({
    config,
    productAnalysis,
    trendBrief,
    modelRoster,
    seedIdea,
  });

  const previewText = [
    `${storyArc.shots.length} shots · ${storyArc.targetDuration}s`,
    storyArc.formula,
    storyArc.toneDirection,
    `${storyArc.cast.length} cast members`,
    storyArc.vietnameseLocalization.genZSlang.slice(0, 2).join(', '),
  ].join(' · ').substring(0, 220);

  return {
    storyArc: {
      value: storyArc,
      status: 'ready',
      schemaType: 'json',
      previewText,
      sizeBytesEstimate: JSON.stringify(storyArc).length * 2,
    } as PortPayload<StoryArcPayload>,
  };
}

// ============================================================
// Mock Execute
// ============================================================

async function mockExecute(
  args: MockNodeExecutionArgs<StoryWriterConfig>,
): Promise<Readonly<Record<string, PortPayload>>> {
  const { config, inputs, signal } = args;

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const productAnalysis = inputs.productAnalysis?.value;
  const trendBrief = inputs.trendBrief?.value;

  if (!productAnalysis || !trendBrief) {
    return {
      storyArc: {
        value: null,
        status: 'error',
        schemaType: 'json',
        errorMessage: 'Missing required inputs: productAnalysis and trendBrief',
      } as PortPayload,
    };
  }

  // Simulate story generation latency
  await new Promise(resolve => setTimeout(resolve, 150));

  if (signal.aborted) {
    throw new Error('Execution cancelled');
  }

  const modelRoster = inputs.modelRoster?.value;
  const seedIdea = inputs.seedIdea?.value 
    ? String(inputs.seedIdea.value) 
    : config.seedIdea || '';

  const storyArc = buildStoryArc({
    config,
    productAnalysis,
    trendBrief,
    modelRoster,
    seedIdea,
  });

  const previewText = [
    `${storyArc.shots.length} shots · ${storyArc.targetDuration}s`,
    storyArc.formula,
    storyArc.toneDirection,
    `${storyArc.cast.length} cast members`,
  ].join(' · ').substring(0, 200);

  return {
    storyArc: {
      value: storyArc,
      status: 'success',
      schemaType: 'json',
      previewText,
      sizeBytesEstimate: JSON.stringify(storyArc).length * 2,
      producedAt: new Date().toISOString(),
    } as PortPayload<StoryArcPayload>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const sampleProductAnalysis: PortPayload = {
  value: {
    productType: 'skincare',
    productName: 'Glow Serum Vitamin C',
    colors: ['amber', 'gold', 'white'],
    materials: ['glass bottle', 'dropper'],
    style: 'luxury minimal',
    sellingPoints: ['Brightening', 'Anti-aging', 'Lightweight texture'],
    targetAudience: {
      age: '18-30',
      gender: 'female',
      occasion: 'daily skincare routine',
      lifestyle: 'urban beauty-conscious',
    },
    pricePositioning: 'mid-range',
    suggestedMood: 'fresh and confident',
  },
  status: 'success',
  schemaType: 'json',
};

const sampleTrendBrief: PortPayload = {
  value: {
    trendingFormats: ['POV skincare routine', 'Get ready with me', 'Before/after glow'],
    trendingHashtags: ['#skincare', '#glowup', '#vietnambeauty', '#skincareroutine'],
    trendingSounds: ['Vietnamese trending audio', 'Soft lo-fi beats'],
    culturalMoments: ['Back to school glow-up', 'Weekend self-care'],
    contentAngles: ['Authentic daily routine', 'Real results no filter'],
    audienceInsights: { primaryAge: '18-24', peakActivity: '20:00-23:00' },
    avoidList: ['Overly polished ads', 'Hard sell approach'],
  },
  status: 'success',
  schemaType: 'json',
};

const sampleModelRoster: PortPayload = {
  value: [
    { modelId: 'mdl-001', modelName: 'Ngọc Trinh', role: 'lead', characterDescription: 'Confident GenZ skincare enthusiast', wardrobe: 'Clean minimal aesthetic', makeup: 'Natural dewy glow' },
    { modelId: 'mdl-002', modelName: 'Bảo Anh', role: 'supporting', characterDescription: 'Relatable friend', wardrobe: 'Casual comfy', makeup: 'Fresh minimal' },
  ],
  status: 'success',
  schemaType: 'json',
};

const fixtures: readonly NodeFixture<StoryWriterConfig>[] = [
  {
    id: 'genz-skincare-tvc',
    label: 'GenZ Skincare TVC',
    config: {
      targetDurationSeconds: 30,
      storyFormula: 'before_after_transformation',
      emotionalTone: 'empowering',
      productIntegrationStyle: 'transformation_reveal',
      genZAuthenticity: 'high',
      includeCasting: true,
      vietnameseDialect: 'neutral',
      seedIdea: 'Morning routine transformation story',
    },
    previewInputs: {
      productAnalysis: sampleProductAnalysis,
      trendBrief: sampleTrendBrief,
      modelRoster: sampleModelRoster,
    },
  },
  {
    id: 'fomo-fashion-flip',
    label: 'FOMO Fashion Story',
    config: {
      targetDurationSeconds: 15,
      storyFormula: 'social_proof_story',
      emotionalTone: 'fomo_urgency',
      productIntegrationStyle: 'hero_moment',
      genZAuthenticity: 'ultra',
      includeCasting: true,
      vietnameseDialect: 'southern',
      seedIdea: 'Friend sees outfit, must have it now',
    },
    previewInputs: {
      productAnalysis: {
        value: {
          productType: 'fashion',
          productName: 'Streetwear Jacket',
          style: 'trendy urban',
        },
        status: 'success',
        schemaType: 'json',
      },
      trendBrief: {
        value: {
          trendingFormats: ['Outfit transitions', 'GRWM', 'Street style'],
          trendingHashtags: ['#ootd', '#fashion', '#vietnamstyle'],
        },
        status: 'success',
        schemaType: 'json',
      },
    },
  },
  {
    id: 'relatable-day-life',
    label: 'Relatable Day-in-Life',
    config: {
      targetDurationSeconds: 45,
      storyFormula: 'day_in_life',
      emotionalTone: 'relatable_humor',
      productIntegrationStyle: 'natural_use',
      genZAuthenticity: 'high',
      includeCasting: true,
      vietnameseDialect: 'northern',
      seedIdea: '',
    },
    previewInputs: {
      productAnalysis: sampleProductAnalysis,
      trendBrief: sampleTrendBrief,
    },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * storyWriter Node Template
 *
 * Executable: Creates Vietnamese GenZ story-driven TVC scripts.
 * Takes product analysis, trend brief, and model roster as inputs.
 * Outputs complete story arc with shots, casting, formula, and localization.
 * Works with Diverge node for multi-LLM compete pattern.
 */
export const storyWriterTemplate: NodeTemplate<StoryWriterConfig> = {
  type: 'storyWriter',
  templateVersion: '1.0.0',
  title: 'Story Writer',
  category: 'script',
  description: 'Creates Vietnamese GenZ story-driven TVC scripts from product analysis and trend brief. Outputs multi-shot story arcs with cast selection, formula, tone and sound direction. Works with Diverge for multi-LLM compete pattern.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: StoryWriterConfigSchema,
  fixtures,
  executable: true,
  buildPreview,
  mockExecute,
};
