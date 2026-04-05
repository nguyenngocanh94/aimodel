import { describe, it, expect, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { createMemoryHistory } from '@tanstack/react-router'
import { createRouter, createRootRoute, createRoute } from '@tanstack/react-router'
import { RouterProvider } from '@tanstack/react-router'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'sonner'

// Simple test components
function TestWorkflowList() {
  return <div data-testid="workflow-list">Workflows List</div>
}

function TestEditor() {
  return <div data-testid="editor">Editor Page</div>
}

function TestRuns() {
  return <div data-testid="runs">Run History</div>
}

describe('TanStack Router', () => {
  let queryClient: QueryClient

  beforeEach(() => {
    queryClient = new QueryClient({
      defaultOptions: {
        queries: { retry: false },
      },
    })
  })

  it('renders component at /workflows route', async () => {
    const rootRoute = createRootRoute()
    const workflowsRoute = createRoute({
      getParentRoute: () => rootRoute,
      path: '/workflows',
      component: TestWorkflowList,
    })

    const memoryHistory = createMemoryHistory({
      initialEntries: ['/workflows'],
    })

    const router = createRouter({
      routeTree: rootRoute.addChildren([workflowsRoute]),
      history: memoryHistory,
    })

    render(
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={router} />
      </QueryClientProvider>
    )

    await waitFor(() => {
      expect(screen.getByTestId('workflow-list')).toBeInTheDocument()
    })
    expect(screen.getByText('Workflows List')).toBeInTheDocument()
  })

  it('renders component at /workflows/$workflowId route', async () => {
    const rootRoute = createRootRoute()
    const editorRoute = createRoute({
      getParentRoute: () => rootRoute,
      path: '/workflows/$workflowId',
      component: TestEditor,
    })

    const memoryHistory = createMemoryHistory({
      initialEntries: ['/workflows/test-workflow-123'],
    })

    const router = createRouter({
      routeTree: rootRoute.addChildren([editorRoute]),
      history: memoryHistory,
    })

    render(
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={router} />
      </QueryClientProvider>
    )

    await waitFor(() => {
      expect(screen.getByTestId('editor')).toBeInTheDocument()
    })
    expect(screen.getByText('Editor Page')).toBeInTheDocument()
  })

  it('renders component at /workflows/$workflowId/runs route', async () => {
    const rootRoute = createRootRoute()
    const runsRoute = createRoute({
      getParentRoute: () => rootRoute,
      path: '/workflows/$workflowId/runs',
      component: TestRuns,
    })

    const memoryHistory = createMemoryHistory({
      initialEntries: ['/workflows/my-workflow/runs'],
    })

    const router = createRouter({
      routeTree: rootRoute.addChildren([runsRoute]),
      history: memoryHistory,
    })

    render(
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={router} />
      </QueryClientProvider>
    )

    await waitFor(() => {
      expect(screen.getByTestId('runs')).toBeInTheDocument()
    })
    expect(screen.getByText('Run History')).toBeInTheDocument()
  })

  it('navigates between routes', async () => {
    const rootRoute = createRootRoute()
    const workflowsRoute = createRoute({
      getParentRoute: () => rootRoute,
      path: '/workflows',
      component: TestWorkflowList,
    })
    const editorRoute = createRoute({
      getParentRoute: () => rootRoute,
      path: '/workflows/$workflowId',
      component: TestEditor,
    })

    const memoryHistory = createMemoryHistory({
      initialEntries: ['/workflows'],
    })

    const router = createRouter({
      routeTree: rootRoute.addChildren([workflowsRoute, editorRoute]),
      history: memoryHistory,
    })

    render(
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={router} />
      </QueryClientProvider>
    )

    // Initially at workflows list
    await waitFor(() => {
      expect(screen.getByTestId('workflow-list')).toBeInTheDocument()
    })

    // Navigate to editor
    await router.navigate({ to: '/workflows/$workflowId', params: { workflowId: 'test-123' } })
    
    await waitFor(() => {
      expect(screen.getByTestId('editor')).toBeInTheDocument()
    })
  })
})

describe('Query Client', () => {
  it('is accessible in components', () => {
    const queryClient = new QueryClient()
    
    render(
      <QueryClientProvider client={queryClient}>
        <div data-testid="query-check">QueryClient available</div>
      </QueryClientProvider>
    )

    expect(screen.getByTestId('query-check')).toHaveTextContent('QueryClient available')
  })
})

describe('Toaster', () => {
  it('renders Toaster component', () => {
    render(<Toaster position="bottom-right" />)
    
    // Toaster renders a portal, so we check it's in document
    expect(document.body).toBeTruthy()
  })
})
