/**
 * productImageInput Node Template - AiModel-621
 *
 * Purpose: Accepts product images + description as workflow entry for TVC workflows.
 *          Replaces userPrompt for product-focused video generation.
 * Category: input
 *
 * Inputs: None (entry node)
 *
 * Outputs:
 *   - productData (productData) — structured product info with images
 *
 * Config:
 *   - description: text description of the product
 *   - imageUrls: array of product image URLs (for URL-based input)
 *   - source: 'upload' | 'telegram' | 'url' — how images were provided
 *   - productName: optional product name
 *   - productCategory: optional category (fashion, electronics, beauty, etc.)
 */

import { z } from 'zod';
import type { NodeTemplate, NodeFixture } from '../node-registry';
import type { PortDefinition, PortPayload } from '@/features/workflows/domain/workflow-types';

// ============================================================
// Configuration Schema
// ============================================================

export const ProductImageInputConfigSchema = z.object({
  description: z.string()
    .min(1)
    .max(2000)
    .describe('Product description — features, benefits, unique selling points'),
  imageUrls: z.array(z.string().url())
    .min(1)
    .max(10)
    .describe('Product image URLs (1-10 images)'),
  source: z.enum(['upload', 'telegram', 'url'])
    .describe('How the images were provided'),
  productName: z.string()
    .min(1)
    .max(200)
    .optional()
    .describe('Product name (optional)'),
  productCategory: z.enum(['fashion', 'electronics', 'beauty', 'food', 'home', 'sports', 'other'])
    .optional()
    .describe('Product category (optional)'),
});

export type ProductImageInputConfig = z.infer<typeof ProductImageInputConfigSchema>;

// ============================================================
// Type Definitions
// ============================================================

export interface ProductImageData {
  readonly url: string;
  readonly index: number;
  readonly filename: string;
}

export interface ProductDataValue {
  readonly productName?: string;
  readonly productCategory?: string;
  readonly description: string;
  readonly images: readonly ProductImageData[];
  readonly imageCount: number;
  readonly source: string;
  readonly inputAt: string;
}

// ============================================================
// Port Definitions
// ============================================================

const inputs: readonly PortDefinition[] = [];

const outputs: readonly PortDefinition[] = [
  {
    key: 'productData',
    label: 'Product Data',
    direction: 'output',
    dataType: 'productData',
    required: true,
    multiple: false,
    description: 'Structured product data with images and description for ProductAnalyzer',
  },
];

// ============================================================
// Default Configuration
// ============================================================

const defaultConfig: ProductImageInputConfig = {
  description: 'Stylish summer dress with floral pattern, perfect for beach outings and casual events. Made from lightweight breathable fabric.',
  imageUrls: ['placeholder://image/product/sample-dress-1.jpg'],
  source: 'upload',
  productName: 'Summer Floral Dress',
  productCategory: 'fashion',
};

// ============================================================
// Preview Builder
// ============================================================

function buildPreview(args: {
  readonly config: Readonly<ProductImageInputConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
}): Readonly<Record<string, PortPayload>> {
  const { config } = args;

  const images: ProductImageData[] = config.imageUrls.map((url, index) => ({
    url,
    index,
    filename: url.split('/').pop() || `image-${index}.jpg`,
  }));

  const productData: ProductDataValue = {
    productName: config.productName,
    productCategory: config.productCategory,
    description: config.description,
    images,
    imageCount: images.length,
    source: config.source,
    inputAt: new Date().toISOString(),
  };

  const previewText = [
    config.productName,
    config.productCategory,
    `${images.length} image${images.length > 1 ? 's' : ''}`,
    config.source,
    config.description.substring(0, 80) + (config.description.length > 80 ? '...' : ''),
  ].filter(Boolean).join(' · ');

  return {
    productData: {
      value: productData,
      status: 'ready',
      schemaType: 'productData',
      previewText: previewText.substring(0, 200),
      sizeBytesEstimate: JSON.stringify(productData).length * 2,
    } as PortPayload<ProductDataValue>,
  };
}

// ============================================================
// Fixtures
// ============================================================

const fixtures: readonly NodeFixture<ProductImageInputConfig>[] = [
  {
    id: 'fashion-dress',
    label: 'Fashion - Summer Dress',
    config: {
      description: 'Elegant summer dress with floral print. Lightweight cotton fabric, perfect for beach weddings and garden parties. Features adjustable straps and hidden side pockets.',
      imageUrls: [
        'placeholder://image/fashion/dress-front.jpg',
        'placeholder://image/fashion/dress-back.jpg',
        'placeholder://image/fashion/dress-detail.jpg',
      ],
      source: 'upload',
      productName: 'Floral Summer Dress',
      productCategory: 'fashion',
    },
  },
  {
    id: 'electronics-phone',
    label: 'Electronics - Smartphone',
    config: {
      description: 'Flagship smartphone with 6.7" OLED display, triple camera system with 50MP main sensor, 5000mAh battery with 65W fast charging. Titanium frame, ceramic shield front.',
      imageUrls: [
        'placeholder://image/electronics/phone-hero.jpg',
        'placeholder://image/electronics/phone-camera.jpg',
        'placeholder://image/electronics/phone-ports.jpg',
        'placeholder://image/electronics/phone-box.jpg',
      ],
      source: 'telegram',
      productName: 'ProMax Smartphone X1',
      productCategory: 'electronics',
    },
  },
  {
    id: 'beauty-serum',
    label: 'Beauty - Face Serum',
    config: {
      description: 'Anti-aging face serum with hyaluronic acid and vitamin C. Lightweight formula absorbs quickly. Dermatologist tested, suitable for sensitive skin. 30ml glass dropper bottle.',
      imageUrls: [
        'placeholder://image/beauty/serum-bottle.jpg',
        'placeholder://image/beauty/serum-texture.jpg',
      ],
      source: 'url',
      productName: 'Radiance Boost Serum',
      productCategory: 'beauty',
    },
  },
  {
    id: 'food-snack',
    label: 'Food - Gourmet Snack',
    config: {
      description: 'Artisanal truffle popcorn made with real black truffle oil. Small-batch handcrafted. Elegant gift packaging. Perfect for movie nights or corporate gifting.',
      imageUrls: [
        'placeholder://image/food/snack-package.jpg',
        'placeholder://image/food/snack-open.jpg',
        'placeholder://image/food/snack-pour.jpg',
      ],
      source: 'upload',
      productName: 'Luxury Truffle Popcorn',
      productCategory: 'food',
    },
  },
  {
    id: 'minimal-single',
    label: 'Minimal - Single Image',
    config: {
      description: 'Handcrafted leather wallet with RFID blocking. Slim profile fits front pocket. Available in cognac, black, and navy.',
      imageUrls: ['placeholder://image/home/wallet-single.jpg'],
      source: 'upload',
      productName: 'Slim RFID Wallet',
      productCategory: 'other',
    },
  },
];

// ============================================================
// Node Template Definition
// ============================================================

/**
 * productImageInput Node Template
 *
 * Non-executable: input node that produces product data from configuration.
 * No async mockExecute needed — output is deterministic from config.
 */
export const productImageInputTemplate: NodeTemplate<ProductImageInputConfig> = {
  type: 'productImageInput',
  templateVersion: '1.0.0',
  title: 'Product Image Input',
  category: 'input',
  description: 'Entry point for TVC workflows. Accepts product images and description, outputs structured product data for ProductAnalyzer. Supports 1-10 images from upload, Telegram, or URL sources.',
  inputs,
  outputs,
  defaultConfig,
  configSchema: ProductImageInputConfigSchema,
  fixtures,
  executable: false,
  buildPreview,
};
