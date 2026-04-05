import { useEffect, useRef } from 'react'
import { useParams } from '@tanstack/react-router'
import { Loader2, AlertTriangle } from 'lucide-react'
import { Link } from '@tanstack/react-router'

import { AppHeader } from '@/app/layout/app-header'
import { BootGate } from '@/app/boot/boot-gate'
import { useWorkflow } from '@/shared/api/queries'
import { useWorkflowStore } from '@/features/workflow/store/workflow-store'
import { useAutoSave } from '@/features/workflow/hooks/use-auto-save'
import type { Workflow } from '@/shared/api/schemas'

/**
 * EditorPage - Loads workflow from backend API, hydrates store, renders canvas.
 *
 * Route: /workflows/$workflowId
 */
export function EditorPage() {
  const { workflowId } = useParams({ from: '/workflows/$workflowId' })
  const { data, isLoading, isError, error } = useWorkflow(workflowId)
  const hydrateFromApi = useWorkflowStore((s) => s.hydrateFromApi)
  const dirty = useWorkflowStore((s) => s.dirty)
  const documentName = useWorkflowStore((s) => s.document.name)
  const hydratedIdRef = useRef<string | null>(null)

  // Hydrate store when API data arrives
  useEffect(() => {
    if (!data) return

    const workflow = (data as { data: Workflow }).data
    if (!workflow?.document) return

    // Only hydrate once per workflow ID (avoid re-hydrating on query refetch
    // when user has unsaved local changes)
    if (hydratedIdRef.current === workflowId) return
    hydratedIdRef.current = workflowId

    // Merge workflow-level fields into document for the store
    // API document only has nodes/edges; store expects full WorkflowDocument
    hydrateFromApi({
      ...workflow.document,
      id: workflow.id,
      name: workflow.name,
      description: workflow.description ?? '',
      schemaVersion: workflow.schemaVersion ?? 1,
      tags: workflow.tags ?? [],
      createdAt: workflow.createdAt,
      updatedAt: workflow.updatedAt,
    } as Parameters<typeof hydrateFromApi>[0])
  }, [data, workflowId, hydrateFromApi])

  // Auto-save to backend
  useAutoSave(workflowId)

  // Loading state
  if (isLoading) {
    return (
      <div className="flex h-screen w-screen flex-col items-center justify-center gap-4 bg-background">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        <p className="text-sm text-muted-foreground">Loading workflow...</p>
      </div>
    )
  }

  // Error state
  if (isError) {
    return (
      <div className="flex h-screen w-screen flex-col items-center justify-center gap-4 bg-background px-6">
        <AlertTriangle className="h-10 w-10 text-destructive" />
        <h1 className="text-lg font-semibold text-foreground">Workflow not found</h1>
        <p className="max-w-md text-center text-sm text-muted-foreground">
          {error instanceof Error ? error.message : 'Could not load this workflow.'}
        </p>
        <Link
          to="/workflows"
          className="mt-2 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90"
        >
          Back to workflows
        </Link>
      </div>
    )
  }

  const saveLabel = dirty ? 'Unsaved changes' : 'Saved'

  return (
    <div className="flex h-screen w-screen flex-col bg-background">
      <AppHeader
        workflowId={workflowId}
        workflowName={documentName}
        saveLabel={saveLabel}
      />
      <div className="flex-1 overflow-hidden">
        <BootGate />
      </div>
    </div>
  )
}
