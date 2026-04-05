import { RouterProvider } from '@tanstack/react-router'
import { router } from './router'

/**
 * App - Main application component with TanStack Router
 *
 * Per plan section 8.1:
 * - Uses RouterProvider for routing
 * - AppProviders (in router.tsx) provides QueryClient, Persistence, Boot, ReactFlow contexts
 * - Routes:
 *   / → redirect to /workflows
 *   /workflows → WorkflowListPage
 *   /workflows/$workflowId → EditorPage (wraps AppShell)
 *   /workflows/$workflowId/runs → RunHistoryPage
 *
 * TODO: Remove old single-route setup (routes.tsx, AppRoutes import)
 */
export function App() {
  return <RouterProvider router={router} />
}
