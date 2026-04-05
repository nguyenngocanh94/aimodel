/**
 * BootGate - AiModel-e0x.3
 * Renders splash screen during boot, fatal error on failure, AppShell when ready.
 * Per plan section 7.3.4
 */

import { Loader2, AlertTriangle } from 'lucide-react'
import { useBootState } from './boot-provider'
import { AppShell } from '@/app/layout/app-shell'

// ============================================================
// Splash screen
// ============================================================

export function AppSplashScreen() {
  return (
    <div className="flex h-screen w-screen flex-col items-center justify-center gap-4 bg-background">
      <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      <p className="text-sm text-muted-foreground">Loading workspace…</p>
    </div>
  )
}

// ============================================================
// Fatal error screen
// ============================================================

interface FatalBootErrorScreenProps {
  readonly message: string
}

export function FatalBootErrorScreen({ message }: FatalBootErrorScreenProps) {
  return (
    <div className="flex h-screen w-screen flex-col items-center justify-center gap-4 bg-background px-6">
      <AlertTriangle className="h-10 w-10 text-destructive" />
      <h1 className="text-lg font-semibold text-foreground">Failed to start</h1>
      <p className="max-w-md text-center text-sm text-muted-foreground">{message}</p>
      <button
        className="mt-2 rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90"
        onClick={() => window.location.reload()}
      >
        Reload
      </button>
    </div>
  )
}

// ============================================================
// Boot gate
// ============================================================

export function BootGate() {
  const boot = useBootState()

  if (boot.status === 'booting') {
    return <AppSplashScreen />
  }

  if (boot.status === 'fatal') {
    return <FatalBootErrorScreen message={boot.message} />
  }

  // 'ready' or 'degraded' — render the full editor
  return <AppShell />
}
