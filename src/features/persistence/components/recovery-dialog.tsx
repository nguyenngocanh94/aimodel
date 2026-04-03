import { RotateCcw } from 'lucide-react'

import { Button } from '@/shared/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/ui/dialog'

interface RecoveryDialogProps {
  readonly open: boolean
  readonly onRecover: () => void
  readonly onDiscard: () => void
  readonly onStartFresh: () => void
  readonly snapshotMeta: {
    readonly workflowName: string
    readonly savedAt: string
    readonly nodeCount: number
    readonly status: 'dirty' | 'clean'
  }
}

function formatTimestamp(iso: string): string {
  try {
    const date = new Date(iso)
    return date.toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return iso
  }
}

export function RecoveryDialog({
  open,
  onRecover,
  onDiscard,
  onStartFresh,
  snapshotMeta,
}: RecoveryDialogProps) {
  return (
    <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onDiscard()}>
      <DialogContent
        data-testid="recovery-dialog"
        className="w-[480px] bg-card rounded-xl shadow-[0_0_40px_rgba(0,0,0,0.5)] border border-border p-0 gap-0"
      >
        <DialogHeader className="space-y-0 p-6 pb-0">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-warning/10">
              <RotateCcw className="h-5 w-5 text-warning" />
            </div>
            <div className="flex flex-col gap-1">
              <DialogTitle className="text-base font-semibold text-foreground">
                Recover unsaved work?
              </DialogTitle>
              <DialogDescription className="text-sm text-muted-foreground">
                We found a local snapshot from your last session.
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        {/* Metadata table */}
        <div className="px-6 py-4">
          <div className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 rounded-md bg-muted p-3">
            <span className="text-[11px] font-medium text-muted-foreground">
              Workflow
            </span>
            <span className="text-[11px] font-mono text-foreground">
              {snapshotMeta.workflowName}
            </span>

            <span className="text-[11px] font-medium text-muted-foreground">
              Saved at
            </span>
            <span className="text-[11px] font-mono text-foreground">
              {formatTimestamp(snapshotMeta.savedAt)}
            </span>

            <span className="text-[11px] font-medium text-muted-foreground">
              Nodes
            </span>
            <span className="text-[11px] font-mono text-foreground">
              {snapshotMeta.nodeCount}
            </span>

            <span className="text-[11px] font-medium text-muted-foreground">
              Status
            </span>
            <span className="text-[11px] text-foreground">
              <span
                className={
                  snapshotMeta.status === 'dirty'
                    ? 'inline-flex items-center rounded-full border border-warning/30 bg-warning/10 px-2 py-0.5 text-[10px] font-semibold text-warning'
                    : 'inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-400'
                }
              >
                {snapshotMeta.status === 'dirty' ? 'Unsaved changes' : 'Clean'}
              </span>
            </span>
          </div>
        </div>

        {/* Action buttons */}
        <div className="flex items-center justify-end gap-2 border-t border-border px-6 py-4">
          <Button variant="secondary" onClick={onStartFresh}>
            Start fresh
          </Button>
          <Button
            variant="outline"
            className="text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive"
            onClick={onDiscard}
          >
            Discard
          </Button>
          <Button onClick={onRecover}>Recover snapshot</Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
