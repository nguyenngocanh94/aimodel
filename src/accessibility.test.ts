/**
 * Accessibility pass tests - AiModel-537.4
 * Per plan section 6.8
 */

import { describe, it, expect } from 'vitest'
import { readFileSync } from 'fs'
import { resolve } from 'path'

function readSrc(relPath: string): string {
  return readFileSync(resolve(__dirname, relPath), 'utf-8')
}

describe('reduced motion', () => {
  it('should have prefers-reduced-motion media query in CSS', () => {
    const css = readSrc('./index.css')
    expect(css).toContain('prefers-reduced-motion: reduce')
    expect(css).toContain('animation-duration: 0.01ms')
    expect(css).toContain('transition-duration: 0.01ms')
  })
})

describe('landmark roles', () => {
  it('app-shell should have main landmark', () => {
    const shell = readSrc('./app/layout/app-shell.tsx')
    expect(shell).toContain('aria-label="Workflow editor"')
    expect(shell).toContain('<main')
  })

  it('app-shell should have nav landmark for node library', () => {
    const shell = readSrc('./app/layout/app-shell.tsx')
    expect(shell).toContain('aria-label="Node library"')
    expect(shell).toContain('<nav')
  })

  it('app-shell should have section landmark for inspector', () => {
    const shell = readSrc('./app/layout/app-shell.tsx')
    expect(shell).toContain('aria-label="Inspector"')
    expect(shell).toContain('<section')
  })

  it('status bar should have role="status"', () => {
    const shell = readSrc('./app/layout/app-shell.tsx')
    expect(shell).toContain('role="status"')
  })
})

describe('node library a11y', () => {
  it('search input should have aria-label', () => {
    const panel = readSrc('./features/node-library/components/node-library-panel.tsx')
    expect(panel).toContain('aria-label="Search nodes"')
  })

  it('category buttons should have aria-expanded', () => {
    const panel = readSrc('./features/node-library/components/node-library-panel.tsx')
    expect(panel).toContain('aria-expanded={expanded}')
  })

  it('display mode toggle should have aria-pressed', () => {
    const panel = readSrc('./features/node-library/components/node-library-panel.tsx')
    expect(panel).toContain('aria-pressed={isCompact}')
  })

  it('library items should have role="button" and tabIndex', () => {
    const item = readSrc('./features/node-library/components/node-library-item.tsx')
    expect(item).toContain('role="button"')
    expect(item).toContain('tabIndex={0}')
  })

  it('library items should have focus-visible styles', () => {
    const item = readSrc('./features/node-library/components/node-library-item.tsx')
    expect(item).toContain('focus-visible:ring-2')
  })
})

describe('workflow node card a11y', () => {
  it('should have role and aria-selected', () => {
    const card = readSrc('./features/canvas/components/workflow-node-card.tsx')
    expect(card).toContain('role="button"')
    expect(card).toContain('aria-selected={selected}')
  })

  it('should have aria-label with node info', () => {
    const card = readSrc('./features/canvas/components/workflow-node-card.tsx')
    expect(card).toContain('aria-label=')
    expect(card).toContain('node.label')
  })

  it('run status badges should have aria-label', () => {
    const card = readSrc('./features/canvas/components/workflow-node-card.tsx')
    expect(card).toContain('aria-label={entry.title}')
  })

  it('validation badge should have aria-label', () => {
    const card = readSrc('./features/canvas/components/workflow-node-card.tsx')
    expect(card).toContain('validation')
    expect(card).toContain("aria-label={`${validationIssues}")
  })
})

describe('form field a11y', () => {
  it('should have aria-invalid on error', () => {
    const fields = readSrc('./features/inspector/components/zod-form-fields.tsx')
    expect(fields).toContain('aria-invalid={!!error}')
  })

  it('should have aria-describedby linking to error', () => {
    const fields = readSrc('./features/inspector/components/zod-form-fields.tsx')
    expect(fields).toContain('aria-describedby=')
    expect(fields).toContain('-error')
  })

  it('error messages should have role="alert"', () => {
    const fields = readSrc('./features/inspector/components/zod-form-fields.tsx')
    expect(fields).toContain('role="alert"')
  })

  it('checkboxes should have focus-visible ring', () => {
    const fields = readSrc('./features/inspector/components/zod-form-fields.tsx')
    expect(fields).toMatch(/checkbox[\s\S]*focus-visible:ring/)
  })
})

describe('empty state a11y', () => {
  it('template buttons should have focus-visible styles', () => {
    const empty = readSrc('./features/canvas/components/canvas-empty-state.tsx')
    expect(empty).toContain('focus-visible:ring-2')
  })

  it('template buttons should have aria-label', () => {
    const empty = readSrc('./features/canvas/components/canvas-empty-state.tsx')
    expect(empty).toContain('aria-label={`Start from template:')
  })
})
