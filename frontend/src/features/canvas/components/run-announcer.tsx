import { useEffect, useRef, useState } from 'react';
import { useRunStore } from '@/features/execution/store/run-store';
import { XCircle } from 'lucide-react';

/**
 * RunAnnouncer — ARIA live region for run events.
 * Announces major execution events to screen readers:
 * 'Run started', 'Node X completed', 'Workflow failed', 'Run cancelled'.
 * Design system section 17: polite live region for major run events.
 */
export function RunAnnouncer() {
  const activeRun = useRunStore((s) => s.activeRun);
  const messageRef = useRef<HTMLDivElement>(null);
  const prevRunIdRef = useRef<string | null>(null);
  const prevStatusRef = useRef<string | null>(null);
  const [showError, setShowError] = useState(false);

  useEffect(() => {
    if (!messageRef.current) return;

    const runId = activeRun?.id ?? null;
    const status = activeRun?.status ?? null;

    // Run started
    if (runId && runId !== prevRunIdRef.current && status === 'running') {
      messageRef.current.textContent = 'Run started';
    }

    // Run completed
    if (runId === prevRunIdRef.current && status !== prevStatusRef.current) {
      if (status === 'success') {
        messageRef.current.textContent = 'Workflow completed successfully';
      } else if (status === 'error') {
        messageRef.current.textContent = 'Workflow failed';
        setShowError(true);
        setTimeout(() => setShowError(false), 5000);
      } else if (status === 'cancelled') {
        messageRef.current.textContent = 'Run cancelled';
      }
    }

    prevRunIdRef.current = runId;
    prevStatusRef.current = status;
  }, [activeRun]);

  return (
    <>
      <div
        ref={messageRef}
        aria-live="polite"
        aria-atomic="true"
        className="sr-only"
        data-testid="run-announcer"
      />
      {showError && (
        <div
          role="alert"
          data-testid="toast-run-error"
          className="fixed bottom-4 right-4 z-toasts flex items-center gap-2 rounded-lg border border-destructive/30 bg-card px-4 py-3 text-sm text-destructive shadow-lg"
        >
          <XCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
          Workflow run failed
        </div>
      )}
    </>
  );
}
