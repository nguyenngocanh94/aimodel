import { describe, it, expect, beforeEach } from 'vitest'
import {
  builtInTemplates,
  validateTemplate,
  instantiateTemplate,
  getBuiltInTemplate,
  resetIdCounter,
} from './built-in-templates'
import { importWorkflow, exportWorkflow } from '@/features/workflows/data/workflow-import-export'
import { CURRENT_SCHEMA_VERSION } from '@/features/workflows/data/workflow-migrations'

beforeEach(() => {
  resetIdCounter()
})

describe('built-in templates', () => {
  it('should have 3 templates', () => {
    expect(builtInTemplates).toHaveLength(3)
  })

  it('should find template by ID', () => {
    expect(getBuiltInTemplate('tpl-narrated-story-video')).toBeDefined()
    expect(getBuiltInTemplate('tpl-product-launch-teaser')).toBeDefined()
    expect(getBuiltInTemplate('tpl-educational-explainer')).toBeDefined()
    expect(getBuiltInTemplate('nonexistent')).toBeUndefined()
  })
})

describe('validateTemplate', () => {
  it.each(builtInTemplates.map((t) => [t.name, t]))(
    '%s should validate cleanly',
    (_name, template) => {
      const result = validateTemplate(template)
      expect(result.valid).toBe(true)
      expect(result.errors).toHaveLength(0)
    },
  )

  it('should detect unknown node types', () => {
    const bad = {
      ...builtInTemplates[0],
      nodes: [{ id: 'n1', type: 'unknownType', label: 'X', position: { x: 0, y: 0 }, config: {} }],
      edges: [],
    }
    const result = validateTemplate(bad)
    expect(result.valid).toBe(false)
    expect(result.errors[0]).toContain('unknown type')
  })

  it('should detect invalid edge references', () => {
    const bad = {
      ...builtInTemplates[0],
      edges: [{ id: 'e1', sourceNodeId: 'nonexistent', sourcePortKey: 'x', targetNodeId: 'also-nonexistent', targetPortKey: 'y' }],
    }
    const result = validateTemplate(bad)
    expect(result.valid).toBe(false)
    expect(result.errors.length).toBeGreaterThanOrEqual(1)
  })
})

describe('instantiateTemplate', () => {
  it.each(builtInTemplates.map((t) => [t.name, t]))(
    '%s should instantiate a valid WorkflowDocument',
    (_name, template) => {
      const doc = instantiateTemplate(template)
      expect(doc.id).toBeTruthy()
      expect(doc.schemaVersion).toBe(CURRENT_SCHEMA_VERSION)
      expect(doc.name).toBe(template.name)
      expect(doc.basedOnTemplateId).toBe(template.id)
      expect(doc.nodes).toHaveLength(template.nodes.length)
      expect(doc.edges).toHaveLength(template.edges.length)
    },
  )

  it('should throw for invalid template', () => {
    const bad = {
      ...builtInTemplates[0],
      nodes: [{ id: 'n1', type: 'unknownType', label: 'X', position: { x: 0, y: 0 }, config: {} }],
      edges: [],
    }
    expect(() => instantiateTemplate(bad)).toThrow('Template validation failed')
  })

  it('should generate unique document IDs', () => {
    const doc1 = instantiateTemplate(builtInTemplates[0])
    const doc2 = instantiateTemplate(builtInTemplates[0])
    expect(doc1.id).not.toBe(doc2.id)
  })
})

describe('template round-trip through import/export', () => {
  it.each(builtInTemplates.map((t) => [t.name, t]))(
    '%s should survive export/import round-trip',
    (_name, template) => {
      const doc = instantiateTemplate(template)
      const json = exportWorkflow(doc)
      const result = importWorkflow(json)

      // Should import without errors (warnings are acceptable for node configs)
      expect(result.status).not.toBe('errors')
      expect(result.document).not.toBeNull()
      expect(result.document!.nodes).toHaveLength(template.nodes.length)
      expect(result.document!.edges).toHaveLength(template.edges.length)
    },
  )
})
