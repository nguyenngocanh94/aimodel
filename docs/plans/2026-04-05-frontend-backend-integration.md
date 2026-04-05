# Frontend-Backend Integration Plan

## Summary

Integrate the existing React frontend with the Laravel backend API. Replace IndexedDB (Dexie) persistence with backend REST API. Add three screens: workflow list, canvas editor (existing, rewired), run history. Use TanStack Router + TanStack Query for routing and data fetching.

## Decisions

| Decision | Choice | Reason |
|----------|--------|--------|
| Dexie/IndexedDB | Remove entirely | Backend is local Docker; no offline scenario. Single source of truth. |
| Router | TanStack Router | Same ecosystem as TanStack Query. Type-safe params, Zod search params. |
| Data fetching | TanStack Query | Caching, mutations, optimistic updates. Zustand stays for authoring state only. |
| Saving | Explicit save (Ctrl+S) | Simple, predictable. Dirty indicator already exists. |
| Mock executor | Remove | Backend handles all execution. Clean cut. |
| Migration | Parallel build, then swap | Build new paths alongside old code. Delete old code last. |

---

## Route Structure

```
/                                → Redirect to /workflows
/workflows                       → Workflow list
/workflows/:workflowId           → Canvas editor
/workflows/:workflowId/runs      → Run history
```

---

## Screen 1: Workflow List (`/workflows`)

Full-page list. No sidebars.

**Components:**
- Header bar — app title, "New Workflow" button
- Search + filter bar — text search, tag filter chips
- Workflow cards grid — name, description, tags, updated at, node/edge count, last run status
- Empty state — CTA to create first workflow
- Card actions — click → editor, kebab menu for duplicate/delete

**Data flow:**
- List: `GET /api/workflows?search=&tags=` via TanStack Query
- Create: `POST /api/workflows` → navigate to `/workflows/:newId`
- Delete: `DELETE /api/workflows/:id` + confirmation → invalidate query

---

## Screen 2: Canvas Editor (`/workflows/:workflowId`)

Existing AppShell layout. Changes are in data flow only.

**Loading:**
- Route loader fetches `GET /api/workflows/:id`
- Hydrates workflow Zustand store via `loadDocument()`

**Saving:**
- Ctrl+S / Save button → `PUT /api/workflows/:id` with current document
- On success: `dirty: false`. On error: toast, stay dirty.

**Running:**
- Run toolbar → `POST /api/workflows/:id/runs { trigger, targetNodeId? }`
- Subscribe to `GET /api/runs/:runId/stream` (SSE)
- SSE events update run Zustand store (same `NodeRunRecord` shape)

**Review:**
- `node.status` with `awaitingReview` → show review UI
- Decision → `POST /api/runs/:id/review { nodeId, decision, notes }`

**Cancel:**
- Cancel button → `POST /api/runs/:id/cancel`
- SSE delivers cancellation events

**Unchanged:** Canvas UI, node cards, edges, inspector, node library, undo/redo, selection, viewport.

---

## Screen 3: Run History (`/workflows/:workflowId/runs`)

Same header as editor. Two tabs in header: "Editor" / "Runs".

**Components:**
- Run list table — status badge, trigger type, started at, duration, node counts
- Run detail panel — click to expand:
  - Metadata (trigger, status, timestamps, termination reason)
  - Node execution timeline (ordered, with status/duration/cache indicator)
  - Click node → input/output payload viewer (reuse data-inspector components)
  - Artifacts — download links (`GET /api/artifacts/:id`)
  - Error details for failed nodes

**Data flow:**
- List: `GET /api/workflows/:id/runs` (new endpoint needed)
- Detail: `GET /api/runs/:runId`
- Read-only screen, no mutations

---

## New Backend Endpoint

The existing API plan is missing a list-runs-for-workflow endpoint:

```
GET /api/workflows/:id/runs    → Paginated list of runs for a workflow
```

---

## Code Changes

### Removed
- `features/workflows/data/workflow-db.ts` — Dexie database
- `features/workflows/data/workflow-repository.ts` — IndexedDB repository
- `features/workflows/data/crash-recovery.ts` — Backend is source of truth
- `features/workflows/data/multi-tab-safety.ts` — Backend handles concurrency
- `features/workflows/data/retention-gc.ts` — Backend manages GC
- `features/persistence/components/recovery-dialog.tsx` — No crash recovery
- `app/boot/boot-gate.tsx` — No IndexedDB init
- `app/boot/boot-provider.tsx` — No boot state machine
- `features/execution/domain/mock-executor.ts` — Backend executes
- `features/execution/domain/run-planner.ts` — Backend plans
- `features/execution/domain/run-cache.ts` — Backend caches

### Replaced
- `features/workflows/data/workflow-import-export.ts` — Keep export, import goes through `POST /api/workflows`
- `app/routes.tsx` — Rewritten with TanStack Router
- `app/app.tsx` — Add TanStack Query + Router providers

### Kept As-Is
- Workflow Zustand store (authoring: undo/redo, selection, viewport, dirty)
- Run Zustand store (active run visualization on canvas)
- All canvas components, node cards, edges, inspector, node library
- Node registry / templates (rendering, config forms)
- Graph validator, type compatibility, preview engine
- All shared UI components

### New Code
- `shared/api/client.ts` — Fetch wrapper, base URL config
- `shared/api/queries/` — TanStack Query hooks: `useWorkflows`, `useWorkflow`, `useWorkflowRuns`, `useRun`
- `shared/api/mutations/` — Mutation hooks: `useSaveWorkflow`, `useCreateWorkflow`, `useDeleteWorkflow`, `useTriggerRun`, `useSubmitReview`, `useCancelRun`
- `shared/api/sse.ts` — SSE client for run streaming → feeds run store
- `shared/api/schemas.ts` — Zod schemas for API response validation (dev safety)
- `features/workflow-list/` — List screen components
- `features/run-history/` — Run history screen components
- Router setup with TanStack Router

---

## Migration Safety

1. **Parallel build** — New API paths built alongside old Dexie code. Old code compiles until manually removed.
2. **Store unchanged** — `loadDocument()` and run store actions accept same shapes. Only callers change.
3. **Type safety** — Zod schemas validate API responses at runtime during dev. Shape mismatches throw immediately.
4. **Feature flag** — `USE_BACKEND` constant. Flip to `false` to restore old Dexie path during transition.
5. **Delete last** — Old code removed only after all new paths verified working.

---

## Implementation Order

1. Add TanStack Router + Query dependencies, set up router shell with placeholder pages
2. Build API client + query/mutation hooks + Zod schemas
3. Build workflow list screen
4. Rewire canvas editor: loading from API, saving to API
5. Build SSE client + rewire run execution
6. Build run history screen
7. Remove old code (Dexie, boot gate, mock executor)
8. Polish: loading states, error handling, toasts, empty states
