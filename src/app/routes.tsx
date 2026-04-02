import { BootGate } from './boot/boot-gate'

/**
 * V1 primary route: `/` → BootGate.
 * BootGate shows splash screen during boot, fatal error on failure, AppShell when ready.
 * Per plan section 7.3.4
 */
export function AppRoutes() {
  return <BootGate />
}
