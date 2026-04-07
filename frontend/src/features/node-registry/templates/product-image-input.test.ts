import { describe, it, expect } from 'vitest';
import {
  productImageInputTemplate,
  ProductImageInputConfigSchema,
  type ProductImageInputConfig,
  type ProductDataValue,
} from './product-image-input';
import type { PortPayload } from '@/features/workflows/domain/workflow-types';

describe('productImageInput Node Template', () => {
  it('should have correct type and category', () => {
    expect(productImageInputTemplate.type).toBe('productImageInput');
    expect(productImageInputTemplate.category).toBe('input');
    expect(productImageInputTemplate.title).toBe('Product Image Input');
    expect(productImageInputTemplate.executable).toBe(false);
  });

  it('should have no inputs', () => {
    expect(productImageInputTemplate.inputs).toHaveLength(0);
  });

  it('should define correct output port', () => {
    expect(productImageInputTemplate.outputs).toHaveLength(1);
    expect(productImageInputTemplate.outputs[0].key).toBe('productData');
    expect(productImageInputTemplate.outputs[0].dataType).toBe('productData');
    expect(productImageInputTemplate.outputs[0].required).toBe(true);
  });

  it('should have correct default config values', () => {
    const cfg = productImageInputTemplate.defaultConfig;
    expect(cfg.description).toContain('summer dress');
    expect(cfg.imageUrls).toHaveLength(1);
    expect(cfg.source).toBe('upload');
    expect(cfg.productName).toBe('Summer Floral Dress');
    expect(cfg.productCategory).toBe('fashion');
  });

  describe('config schema validation', () => {
    it('should validate a valid config', () => {
      const cfg: ProductImageInputConfig = ProductImageInputConfigSchema.parse({
        description: 'Test product description',
        imageUrls: ['https://example.com/image1.jpg', 'https://example.com/image2.jpg'],
        source: 'url',
        productName: 'Test Product',
        productCategory: 'electronics',
      });
      expect(cfg.description).toBe('Test product description');
      expect(cfg.imageUrls).toHaveLength(2);
      expect(cfg.source).toBe('url');
    });

    it('should require at least 1 image URL', () => {
      expect(() =>
        ProductImageInputConfigSchema.parse({
          description: 'Test',
          imageUrls: [],
          source: 'upload',
        }),
      ).toThrow();
    });

    it('should reject more than 10 image URLs', () => {
      expect(() =>
        ProductImageInputConfigSchema.parse({
          description: 'Test',
          imageUrls: Array(11).fill('https://example.com/image.jpg'),
          source: 'upload',
        }),
      ).toThrow();
    });

    it('should reject invalid URLs', () => {
      expect(() =>
        ProductImageInputConfigSchema.parse({
          description: 'Test',
          imageUrls: ['not-a-valid-url'],
          source: 'upload',
        }),
      ).toThrow();
    });

    it('should require description', () => {
      expect(() =>
        ProductImageInputConfigSchema.parse({
          description: '',
          imageUrls: ['https://example.com/image.jpg'],
          source: 'upload',
        }),
      ).toThrow();
    });

    it('should accept minimal valid config', () => {
      const cfg = ProductImageInputConfigSchema.parse({
        description: 'Minimal description',
        imageUrls: ['https://example.com/image.jpg'],
        source: 'telegram',
      });
      expect(cfg.productName).toBeUndefined();
      expect(cfg.productCategory).toBeUndefined();
    });

    it('should accept all valid product categories', () => {
      const categories = ['fashion', 'electronics', 'beauty', 'food', 'home', 'sports', 'other'];
      categories.forEach((category) => {
        const cfg = ProductImageInputConfigSchema.parse({
          description: 'Test',
          imageUrls: ['https://example.com/image.jpg'],
          source: 'upload',
          productCategory: category,
        });
        expect(cfg.productCategory).toBe(category);
      });
    });

    it('should reject invalid product category', () => {
      expect(() =>
        ProductImageInputConfigSchema.parse({
          description: 'Test',
          imageUrls: ['https://example.com/image.jpg'],
          source: 'upload',
          productCategory: 'invalid' as unknown as 'fashion',
        }),
      ).toThrow();
    });
  });

  describe('buildPreview', () => {
    it('should produce product data payload', () => {
      const out = productImageInputTemplate.buildPreview({
        config: productImageInputTemplate.defaultConfig,
        inputs: {},
      });
      expect(out.productData.status).toBe('ready');
      const data = out.productData.value as ProductDataValue;
      expect(data.description).toContain('summer dress');
      expect(data.images).toHaveLength(1);
      expect(data.imageCount).toBe(1);
      expect(data.source).toBe('upload');
    });

    it('should include all images in payload', () => {
      const cfg: ProductImageInputConfig = {
        description: 'Multi-image product',
        imageUrls: [
          'https://example.com/img1.jpg',
          'https://example.com/img2.jpg',
          'https://example.com/img3.jpg',
        ],
        source: 'upload',
      };
      const out = productImageInputTemplate.buildPreview({
        config: cfg,
        inputs: {},
      });
      const data = out.productData.value as ProductDataValue;
      expect(data.images).toHaveLength(3);
      expect(data.imageCount).toBe(3);
      expect(data.images[0].index).toBe(0);
      expect(data.images[1].index).toBe(1);
      expect(data.images[2].index).toBe(2);
    });

    it('should extract filenames from URLs', () => {
      const cfg: ProductImageInputConfig = {
        description: 'Test',
        imageUrls: ['https://example.com/path/to/my-image.jpg'],
        source: 'url',
      };
      const out = productImageInputTemplate.buildPreview({
        config: cfg,
        inputs: {},
      });
      const data = out.productData.value as ProductDataValue;
      expect(data.images[0].filename).toBe('my-image.jpg');
    });

    it('should generate fallback filename for URLs without path', () => {
      const cfg: ProductImageInputConfig = {
        description: 'Test',
        imageUrls: ['https://example.com/'],
        source: 'url',
      };
      const out = productImageInputTemplate.buildPreview({
        config: cfg,
        inputs: {},
      });
      const data = out.productData.value as ProductDataValue;
      expect(data.images[0].filename).toBe('image-0.jpg');
    });

    it('should include product name and category when provided', () => {
      const cfg: ProductImageInputConfig = {
        description: 'Smartphone with great camera',
        imageUrls: ['https://example.com/phone.jpg'],
        source: 'telegram',
        productName: 'ProPhone X',
        productCategory: 'electronics',
      };
      const out = productImageInputTemplate.buildPreview({
        config: cfg,
        inputs: {},
      });
      const data = out.productData.value as ProductDataValue;
      expect(data.productName).toBe('ProPhone X');
      expect(data.productCategory).toBe('electronics');
    });

    it('should include previewText with key details', () => {
      const out = productImageInputTemplate.buildPreview({
        config: productImageInputTemplate.defaultConfig,
        inputs: {},
      });
      expect(out.productData.previewText).toContain('Summer Floral Dress');
      expect(out.productData.previewText).toContain('fashion');
      expect(out.productData.previewText).toContain('1 image');
      expect(out.productData.previewText).toContain('upload');
    });

    it('should include timestamp in payload', () => {
      const before = new Date().toISOString();
      const out = productImageInputTemplate.buildPreview({
        config: productImageInputTemplate.defaultConfig,
        inputs: {},
      });
      const after = new Date().toISOString();
      const data = out.productData.value as ProductDataValue;
      expect(data.inputAt).toBeDefined();
      expect(data.inputAt >= before).toBe(true);
      expect(data.inputAt <= after).toBe(true);
    });

    it('should handle source types correctly', () => {
      const sources: ProductImageInputConfig['source'][] = ['upload', 'telegram', 'url'];
      sources.forEach((source) => {
        const cfg: ProductImageInputConfig = {
          description: 'Test',
          imageUrls: ['https://example.com/image.jpg'],
          source,
        };
        const out = productImageInputTemplate.buildPreview({
          config: cfg,
          inputs: {},
        });
        const data = out.productData.value as ProductDataValue;
        expect(data.source).toBe(source);
      });
    });

    it('should truncate long descriptions in previewText', () => {
      const cfg: ProductImageInputConfig = {
        description: 'A'.repeat(200),
        imageUrls: ['https://example.com/image.jpg'],
        source: 'upload',
      };
      const out = productImageInputTemplate.buildPreview({
        config: cfg,
        inputs: {},
      });
      expect(out.productData.previewText!.length).toBeLessThan(200);
      expect(out.productData.previewText).toContain('...');
    });
  });

  describe('fixtures', () => {
    it('should have at least 4 fixtures', () => {
      expect(productImageInputTemplate.fixtures.length).toBeGreaterThanOrEqual(4);
    });

    it('should have fashion-dress fixture', () => {
      const f = productImageInputTemplate.fixtures.find((fx) => fx.id === 'fashion-dress');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Fashion - Summer Dress');
      expect(f!.config.productCategory).toBe('fashion');
      expect(f!.config.imageUrls).toHaveLength(3);
    });

    it('should have electronics-phone fixture', () => {
      const f = productImageInputTemplate.fixtures.find((fx) => fx.id === 'electronics-phone');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Electronics - Smartphone');
      expect(f!.config.source).toBe('telegram');
      expect(f!.config.productCategory).toBe('electronics');
    });

    it('should have beauty-serum fixture', () => {
      const f = productImageInputTemplate.fixtures.find((fx) => fx.id === 'beauty-serum');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Beauty - Face Serum');
      expect(f!.config.source).toBe('url');
      expect(f!.config.imageUrls).toHaveLength(2);
    });

    it('should have food-snack fixture', () => {
      const f = productImageInputTemplate.fixtures.find((fx) => fx.id === 'food-snack');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Food - Gourmet Snack');
      expect(f!.config.productCategory).toBe('food');
    });

    it('should have minimal-single fixture with single image', () => {
      const f = productImageInputTemplate.fixtures.find((fx) => fx.id === 'minimal-single');
      expect(f).toBeDefined();
      expect(f?.label).toBe('Minimal - Single Image');
      expect(f!.config.imageUrls).toHaveLength(1);
      expect(f!.config.productCategory).toBe('other');
    });
  });
});
