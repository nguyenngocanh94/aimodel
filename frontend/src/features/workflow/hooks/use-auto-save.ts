import { useEffect, useRef } from 'react'
import { toast } from 'sonner'

import { useWorkflowStore } from '@/features/workflow/store/workflow-store'
import { useSaveWorkflow } from '@/shared/api/mutations'

const AUTO_SAVE_DEBOUNCE_MS = 2_000

/**
 * useAutoSave - Debounced auto-save of workflow document to backend API.
 *
 * Subscribes to the workflow store's `document` and `dirty` state.
 * After 2 seconds of inactivity following a change, PUTs the document
 * to the backend via `useSaveWorkflow`. Shows toast on failure.
 */
export function useAutoSave(workflowId: string) {
  const saveMutation = useSaveWorkflow(workflowId)
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const isSavingRef = useRef(false)

  useEffect(() => {
    const unsubscribe = useWorkflowStore.subscribe((state, prevState) => {
      // Only trigger save when document actually changes and is dirty
      if (!state.dirty || state.document === prevState.document) {
        return
      }

      // Clear previous debounce timer
      if (timerRef.current) {
        clearTimeout(timerRef.current)
      }

      timerRef.current = setTimeout(() => {
        const { document, markSaved } = useWorkflowStore.getState()

        // Avoid overlapping saves
        if (isSavingRef.current) {
          return
        }

        isSavingRef.current = true

        saveMutation.mutate(
          {
            name: document.name,
            description: document.description,
            document,
          },
          {
            onSuccess: () => {
              markSaved()
              isSavingRef.current = false
            },
            onError: (error) => {
              isSavingRef.current = false
              toast.error('Failed to save workflow', {
                description: error instanceof Error ? error.message : 'Unknown error',
              })
            },
          },
        )
      }, AUTO_SAVE_DEBOUNCE_MS)
    })

    return () => {
      unsubscribe()
      if (timerRef.current) {
        clearTimeout(timerRef.current)
      }
    }
  }, [workflowId, saveMutation])
}
