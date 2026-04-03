import { useEffect, useRef } from 'react';
import { useRunStore } from '@/features/execution/store/run-store';

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
      } else if (status === 'cancelled') {
        messageRef.current.textContent = 'Run cancelled';
      }
    }

    prevRunIdRef.current = runId;
    prevStatusRef.current = status;
  }, [activeRun]);

  return (
    <div
      ref={messageRef}
      aria-live="polite"
      aria-atomic="true"
      className="sr-only"
      data-testid="run-announcer"
    />
  );
}
