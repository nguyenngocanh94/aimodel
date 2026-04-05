import { describe, it, expect } from 'vitest'
import { cn } from './utils'

describe('cn utility', () => {
  it('should merge class names correctly', () => {
    expect(cn('foo', 'bar')).toBe('foo bar')
    const includeBar = false
    expect(cn('foo', includeBar && 'bar', 'baz')).toBe('foo baz')
  })
})
