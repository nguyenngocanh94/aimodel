import { createRootRoute, createRoute, createRouter, Outlet, redirect } from '@tanstack/react-router'
import { TanStackRouterDevtools } from '@tanstack/react-router-devtools'
import { AppProviders } from './providers'
import { WorkflowListPage } from '@/pages/workflow-list-page'
import { EditorPage } from '@/pages/editor-page'
import { RunHistoryPage } from '@/pages/run-history-page'
import { Toaster } from 'sonner'

/**
 * Root route with layout wrapper
 * Provides AppProviders context to all child routes
 */
export const rootRoute = createRootRoute({
  component: () => (
    <AppProviders>
      <Outlet />
      <Toaster position="bottom-right" richColors />
      <TanStackRouterDevtools />
    </AppProviders>
  ),
})

/**
 * Index route - redirects to /workflows
 */
export const indexRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: '/',
  beforeLoad: () => {
    throw redirect({ to: '/workflows' })
  },
})

/**
 * Workflows list route
 */
export const workflowsRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: '/workflows',
  component: WorkflowListPage,
})

/**
 * Editor route - wraps existing AppShell with workflow context
 */
export const workflowEditorRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: '/workflows/$workflowId',
  component: EditorPage,
})

/**
 * Run history route
 */
export const workflowRunsRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: '/workflows/$workflowId/runs',
  component: RunHistoryPage,
})

/**
 * Route tree
 */
export const routeTree = rootRoute.addChildren([
  indexRoute,
  workflowsRoute,
  workflowEditorRoute,
  workflowRunsRoute,
])

/**
 * Create and configure the router
 */
export const router = createRouter({
  routeTree,
  defaultPreload: 'intent',
})

/**
 * Type-safe route registration
 */
declare module '@tanstack/react-router' {
  interface Register {
    router: typeof router
  }
}
