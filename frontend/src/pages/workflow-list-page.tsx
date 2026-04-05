import { useState } from 'react'
import { useNavigate } from '@tanstack/react-router'
import { Plus, Search, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/shared/ui/button'
import { Badge } from '@/shared/ui/badge'
import { useWorkflows } from '@/shared/api/queries'
import { useCreateWorkflow, useDeleteWorkflow } from '@/shared/api/mutations'

interface WorkflowItem {
  readonly id: string
  readonly name: string
  readonly description?: string
  readonly tags?: readonly string[]
  readonly updatedAt?: string
  readonly document?: {
    readonly nodes?: readonly unknown[]
    readonly edges?: readonly unknown[]
  }
}

function formatRelativeTime(dateStr?: string): string {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)
  if (diffMins < 1) return 'just now'
  if (diffMins < 60) return `${diffMins}m ago`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours}h ago`
  const diffDays = Math.floor(diffHours / 24)
  return `${diffDays}d ago`
}

export function WorkflowListPage() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')

  const { data, isLoading, error } = useWorkflows(search ? { search } : undefined)
  const createWorkflow = useCreateWorkflow()
  const deleteWorkflow = useDeleteWorkflow()

  const workflows = (data as { data?: WorkflowItem[] })?.data ?? []

  const handleCreate = async () => {
    try {
      const result = await createWorkflow.mutateAsync({
        name: 'New Workflow',
        description: '',
        document: { nodes: [], edges: [] },
      })
      const newWorkflow = (result as { data?: { id?: string } })?.data
      if (newWorkflow?.id) {
        navigate({ to: '/workflows/$workflowId', params: { workflowId: newWorkflow.id } })
      }
      toast.success('Workflow created')
    } catch {
      toast.error('Failed to create workflow')
    }
  }

  const handleDelete = async (workflowId: string, name: string) => {
    if (!confirm(`Delete "${name}"? This cannot be undone.`)) return
    try {
      await deleteWorkflow.mutateAsync(workflowId)
      toast.success('Workflow deleted')
    } catch {
      toast.error('Failed to delete workflow')
    }
  }

  return (
    <div className="flex h-screen w-screen flex-col bg-background">
      {/* Header */}
      <header className="flex h-14 shrink-0 items-center justify-between border-b bg-card px-6">
        <h1 className="text-lg font-semibold">AI Video Workflows</h1>
        <Button onClick={handleCreate} disabled={createWorkflow.isPending} size="sm">
          <Plus className="mr-1 h-4 w-4" />
          New Workflow
        </Button>
      </header>

      {/* Search */}
      <div className="border-b px-6 py-3">
        <div className="relative max-w-md">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input
            type="text"
            placeholder="Search workflows..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full rounded-md border bg-background py-2 pl-9 pr-3 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            data-testid="workflow-search"
          />
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 overflow-auto p-6">
        {isLoading && (
          <div className="flex items-center justify-center py-20">
            <div className="text-sm text-muted-foreground">Loading workflows...</div>
          </div>
        )}

        {error && (
          <div className="flex items-center justify-center py-20">
            <div className="text-sm text-destructive">Failed to load workflows</div>
          </div>
        )}

        {!isLoading && !error && workflows.length === 0 && (
          <div className="flex flex-col items-center justify-center gap-4 py-20" data-testid="empty-state">
            <h2 className="text-xl font-semibold text-foreground">No workflows yet</h2>
            <p className="text-muted-foreground">Create your first AI video workflow to get started.</p>
            <Button onClick={handleCreate} disabled={createWorkflow.isPending}>
              <Plus className="mr-1 h-4 w-4" />
              Create your first workflow
            </Button>
          </div>
        )}

        {!isLoading && workflows.length > 0 && (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="workflow-grid">
            {workflows.map((workflow) => (
              <div
                key={workflow.id}
                className="group relative cursor-pointer rounded-lg border bg-card p-4 transition-colors hover:bg-accent"
                onClick={() => navigate({ to: '/workflows/$workflowId', params: { workflowId: workflow.id } })}
                data-testid="workflow-card"
              >
                <div className="flex items-start justify-between">
                  <div className="min-w-0 flex-1">
                    <h3 className="truncate text-sm font-semibold">{workflow.name}</h3>
                    {workflow.description && (
                      <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                        {workflow.description}
                      </p>
                    )}
                  </div>
                  <button
                    onClick={(e) => {
                      e.stopPropagation()
                      handleDelete(workflow.id, workflow.name)
                    }}
                    className="opacity-0 group-hover:opacity-100 transition-opacity rounded p-1 hover:bg-destructive/10"
                    aria-label="Delete workflow"
                  >
                    <Trash2 className="h-3.5 w-3.5 text-destructive" />
                  </button>
                </div>

                <div className="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                  <span>{workflow.document?.nodes?.length ?? 0} nodes</span>
                  <span>&middot;</span>
                  <span>{workflow.document?.edges?.length ?? 0} edges</span>
                  {workflow.updatedAt && (
                    <>
                      <span>&middot;</span>
                      <span>{formatRelativeTime(workflow.updatedAt)}</span>
                    </>
                  )}
                </div>

                {workflow.tags && workflow.tags.length > 0 && (
                  <div className="mt-2 flex flex-wrap gap-1">
                    {workflow.tags.map((tag) => (
                      <Badge key={tag} variant="secondary" className="text-[10px]">
                        {tag}
                      </Badge>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
