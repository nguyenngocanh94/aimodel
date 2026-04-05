import { describe, it, expect } from 'vitest'
import { getLastOpenedWorkflowId, setLastOpenedWorkflowId, clearLastOpenedWorkflowId } from './boot-provider'

describe('boot-provider localStorage helpers', () => {
  it('should return null when no last workflow is stored', () => {
    localStorage.removeItem('aimodel:lastOpenedWorkflowId')
    expect(getLastOpenedWorkflowId()).toBeNull()
  })

  it('should store and retrieve last-opened workflow id', () => {
    setLastOpenedWorkflowId('wf-123')
    expect(getLastOpenedWorkflowId()).toBe('wf-123')
  })

  it('should clear last-opened workflow id', () => {
    setLastOpenedWorkflowId('wf-123')
    clearLastOpenedWorkflowId()
    expect(getLastOpenedWorkflowId()).toBeNull()
  })
})
