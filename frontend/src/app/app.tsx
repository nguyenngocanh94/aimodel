import { AppProviders } from './providers'
import { AppRoutes } from './routes'

/**
 * App - Main application component
 *
 * Implements the three-region app shell per plan section 6.1:
 * - Left: NodeLibraryPanel
 * - Center: CanvasSurface + RunToolbar
 * - Right: InspectorPanel with tabs
 * - Top bar: AppHeader with workflow controls
 * - Bottom: StatusBar with system status
 *
 * All layout components are defined in the features/ directory
 * and composed in routes.tsx and layout/app-shell.tsx
 */
export function App() {
  return (
    <AppProviders>
      <AppRoutes />
    </AppProviders>
  )
}
