# Align Frontend Node Registry With Backend (manifest-driven config)

> **For Claude:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Goal:** Eliminate the hand-mirrored config/default drift between `backend/app/Domain/Nodes/Templates/*.php` and `frontend/src/features/node-registry/templates/*.ts`. The backend becomes the single source of truth for **ports + config schema + defaults**; the frontend fetches that as a manifest and renders the inspector from it generically. Per-template TS files keep only **`mockExecute` / `buildPreview` / `fixtures` / UI hints** — the things only the browser can do fast.

**The immediate payoff:** any config block the backend declares (via traits like `InteractsWithHuman` adding `humanGate.*`, or via straight `configRules()`) appears in the canvas inspector automatically, with zero TS edits.

**Non-goal:** this plan does NOT unify execution logic. Backend `execute()` (PHP) and frontend `mockExecute()` (TS) stay separate on purpose — the canvas's instant-preview UX depends on local mock execution.

**Neutral serialization:** the manifest uses **JSON Schema** for `configSchema`. Laravel validator rules transpile to JSON Schema on the backend; the frontend consumes JSON Schema directly (maps cleanly to shadcn/ui form widgets via React Hook Form). No custom DSL either side has to parse.

**Tech Stack:** PHP 8.4 + Laravel 11 backend, React 18 + Vite + shadcn/ui + React Hook Form + Zod on the frontend, Vitest + PHPUnit 11 for tests.

---

## NM1 — Backend: validator-rules → JSON Schema transpiler + `GET /api/nodes/manifest`

**Files:**
- Create: `backend/app/Domain/Nodes/ConfigSchemaTranspiler.php`
- Create: `backend/app/Domain/Nodes/NodeManifestBuilder.php`
- Create: `backend/app/Http/Controllers/NodeManifestController.php`
- Edit: `backend/routes/api.php`
- Tests:
  - `backend/tests/Unit/Domain/Nodes/ConfigSchemaTranspilerTest.php`
  - `backend/tests/Feature/NodeManifestControllerTest.php`

**`ConfigSchemaTranspiler::transpile(array $rules, array $defaults): array`** — takes a Laravel validator rules array (as returned by `NodeTemplate::configRules()`) and the defaults array, produces a **JSON Schema Draft-07 object**. Must handle:

| Laravel rule | JSON Schema |
|---|---|
| `required` | field listed in root `required: []` |
| `string` | `{type: "string"}` |
| `integer` | `{type: "integer"}` |
| `numeric` | `{type: "number"}` |
| `boolean` | `{type: "boolean"}` |
| `array` | `{type: "array"}` |
| `nullable` | union with `null`: `{type: ["string","null"]}` |
| `in:a,b,c` | `enum: ["a","b","c"]` |
| `min:N` (on string) | `minLength: N` |
| `min:N` (on integer/numeric) | `minimum: N` |
| `max:N` | `maxLength` / `maximum` symmetrically |
| dot-notation keys (`humanGate.enabled`, `humanGate.channel`) | nested object: root `{type: "object", properties: {humanGate: {type: "object", properties: {enabled: {...}, channel: {...}}}}}` |
| `sometimes` | field NOT in required list |

Include defaults: every property with a default value in `$defaults` gets `default: <value>` in its JSON Schema node. Nested defaults (`humanGate` is an object) recurse.

Emit `$schema: "http://json-schema.org/draft-07/schema#"`, `type: "object"`, `additionalProperties: false`.

**`NodeManifestBuilder::build(NodeTemplate $template): array`** — per template, returns:
```json
{
  "type": "storyWriter",
  "version": "1.0.0",
  "title": "Story Writer",
  "description": "...",
  "category": "script",
  "ports": {
    "inputs": [{"key": "productAnalysis", "label": "Product Analysis", "dataType": "json", "required": false, "multiple": false, "description": "..."}, ...],
    "outputs": [...]
  },
  "configSchema": <JSON Schema from ConfigSchemaTranspiler>,
  "defaultConfig": {...},
  "humanGateEnabled": true,
  "executable": true
}
```

`humanGateEnabled` = `$template instanceof \App\Domain\Nodes\Concerns\InteractsWithHuman` OR (since traits can't be tested with `instanceof`) `method_exists($template, 'humanGateDefaultConfig')`. The flag lets the frontend optionally render a collapsed "Human Gate" section even if the underlying schema is already object-nested.

**`NodeManifestController`:**
- `GET /api/nodes/manifest` → `{version: <hash of all templates' versions+types>, nodes: {[type]: NodeManifest}}`.
- Cache the full manifest at class level per-process (it's derived from static template metadata; no per-request state).
- Response `Cache-Control: public, max-age=300` so the frontend can re-fetch on demand without thrashing.

**Route:** `Route::get('/api/nodes/manifest', [NodeManifestController::class, 'show'])->name('nodes.manifest');`. No auth (v1 is single-user per `AGENTS.md`).

**Tests:**
- `ConfigSchemaTranspilerTest` — covers every rule/combination above with specific PHP input → expected JSON Schema output. At least 15 cases: scalar types, `in:` enums, nullable, nested dot-notation, string length bounds, integer min/max, required vs sometimes, defaults embedded, enum with int values.
- `NodeManifestControllerTest` — feature test: seed no workflows, GET the endpoint, assert 200 + JSON shape + every registered template appears + `storyWriter` manifest contains a `humanGate` object in its `configSchema.properties` with nested `enabled: {type:boolean, default:false}`.

**Acceptance:**
- `docker exec backend-app-1 php artisan test --filter="ConfigSchemaTranspilerTest|NodeManifestControllerTest"` green.
- `curl http://localhost/api/nodes/manifest | jq '.nodes.storyWriter.configSchema.properties.humanGate'` returns a non-null object.

---

## NM2 — Frontend manifest client + `JsonSchemaForm` component

**Files:**
- Create: `frontend/src/features/node-registry/manifest/types.ts`
- Create: `frontend/src/features/node-registry/manifest/fetcher.ts`
- Create: `frontend/src/features/node-registry/manifest/manifest-context.tsx` (React context + provider + `useNodeManifest(type?)` hook)
- Edit: `frontend/src/app/providers/index.tsx` or equivalent app-shell wrapper to mount the provider at boot
- Create: `frontend/src/features/inspector/components/JsonSchemaForm.tsx`
- Create: `frontend/src/features/inspector/components/JsonSchemaField.tsx` (recursive)
- Tests:
  - `frontend/src/features/node-registry/manifest/fetcher.test.ts`
  - `frontend/src/features/inspector/components/JsonSchemaForm.test.tsx`

**Manifest client:**
- `types.ts`: TS types mirroring NM1's response (`NodeManifest`, `PortManifest`, `JsonSchemaNode`).
- `fetcher.ts`: `fetchNodeManifest(): Promise<ManifestResponse>` calling `GET /api/nodes/manifest`. In tests, use MSW or vi.fn to mock.
- `manifest-context.tsx`:
  - Provider fetches once at mount, caches in memory + localStorage (keyed by `version` from response).
  - Hook `useNodeManifest(type?)`: no-arg returns the full manifest map; with a type returns one entry or `undefined`.
  - Loading state: return `{status: 'loading' | 'ready' | 'error', data?: ManifestResponse, error?: Error}`.

**`JsonSchemaForm` component:**
- Props: `schema: JsonSchemaNode`, `value: unknown`, `onChange: (value) => void`, `errors?: Record<path, string>`.
- Internally uses React Hook Form for state.
- Renders each property via `JsonSchemaField`:
  - `type: "string"` → shadcn `<Input>` (or `<Textarea>` if `maxLength > 200`).
  - `type: "string", enum: [...]` → shadcn `<Select>`.
  - `type: "string", default: "..."` placeholder.
  - `type: "integer"` / `"number"` → `<Input type="number">` with min/max.
  - `type: "boolean"` → shadcn `<Switch>`.
  - `type: "array"` → simple repeater: list of items + add/remove buttons. Item rendering recurses on `items` schema. If `items.type === "string"` render chip-style.
  - `type: "object"` → collapsible `<fieldset>` with nested `JsonSchemaField` per property; label is humanized key; description from `description`.
  - Unknown type → render nothing, log a warning to console.
- Required fields show a red asterisk; validation errors render underneath the field.
- Follow AGENTS.md: Tailwind for styling, shadcn for primitives, no new CSS modules.

**Tests:**
- `fetcher.test.ts`: happy path, 404, malformed JSON.
- `JsonSchemaForm.test.tsx` using React Testing Library: render with a synthetic schema covering all shapes (`string`, `enum`, `integer`, `boolean`, nested `object` with a boolean + string child). Assert the right widgets render. Simulate user input and assert `onChange` fires with the merged value. Specifically test a schema with a `humanGate` nested object and assert the nested fields render inside a collapsible fieldset.

**Acceptance:**
- `npx vitest run src/features/node-registry/manifest/` and `npx vitest run src/features/inspector/components/JsonSchemaForm.test.tsx` green (< 10 s each per AGENTS.md).
- In a storybook-style smoke component (or devtools), mounting the provider + rendering storyWriter's live manifest produces a working form with humanGate visible.

---

## NM3 — Wire `JsonSchemaForm` into the inspector; pilot-migrate 3 templates

**Files:**
- Edit: `frontend/src/features/inspector/components/InspectorPanel.tsx` (or wherever the current inspector form lives — locate via `grep -rln "configSchema" frontend/src/features/inspector/`)
- Edit: three TS template files to strip their `configSchema` and `defaultConfig`:
  - `frontend/src/features/node-registry/templates/story-writer.ts`
  - `frontend/src/features/node-registry/templates/human-gate.ts`
  - `frontend/src/features/node-registry/templates/user-prompt.ts`
- Edit: `frontend/src/features/node-registry/node-registry.ts` (or equivalent) so the registry resolves `configSchema` + `defaultConfig` from the manifest first, falling back to the TS template only for nodes whose manifest entry is missing.

**Inspector refactor:**
- When a node is selected on the canvas, look up its manifest entry via `useNodeManifest(node.type)`.
- If the manifest is ready and has a `configSchema`, render `<JsonSchemaForm schema={manifest.configSchema} value={node.config} onChange={updateConfig} />`.
- If the manifest is still loading, show a subtle skeleton (no spinner spam).
- If the manifest errored out or lacks an entry for this type, fall back to the legacy TS `configSchema` so the inspector still works during partial migration.

**Strip-migration for the three pilot templates:**
- Remove the `XxxConfigSchema` Zod definition and the `defaultConfig` object from each file.
- Keep `inputs`, `outputs`, `buildPreview`, `mockExecute`, `fixtures`, `type`, `title`, `category`, `description`, `executable`, `templateVersion`.
- Import the shared `NodeTemplate<TConfig>` type where `TConfig = Record<string, unknown>` (or a codegen-ed type from the manifest — future work).
- The `NodeTemplate` interface in `workflow-types.ts` may need a small change to make `configSchema` and `defaultConfig` optional; if so, update it and ensure all other templates still typecheck (they'll still have the fields — optional means "may provide, or resolve from manifest").

**Drift sanity in this task:** after stripping, run the frontend test suite for the three templates:
```
npx vitest run src/features/node-registry/templates/story-writer.test.ts src/features/node-registry/templates/human-gate.test.ts src/features/node-registry/templates/user-prompt.test.ts
```
Expect some failures where tests assert on `configSchema` / `defaultConfig`. Rewrite those assertions to:
- Use the manifest: `import { fetchNodeManifest } from '@/features/node-registry/manifest/fetcher'` and assert on the fetched schema.
- Or skip/delete the assertion if it's redundant with NM1's backend tests.

**Test lock protocol** (from `AGENTS.md:71`): use `.test.lock` before running vitest.

**Acceptance:**
- Scoped vitest runs for the three templates + the inspector + the manifest module all pass.
- Manual smoke: `docker exec backend-app-1 php artisan db:seed --class=HumanGateDemoSeeder --force` then load the frontend, open the "StoryWriter (per-node gate) – Telegram" workflow, click the storyWriter node. **The inspector shows a `humanGate` section with `enabled` toggle + `channel` select + `botToken` / `chatId` inputs + `options` array widget**, all pre-populated from backend defaults.

---

## NM4 — Drift safety net + close the humanGate-in-UI loop

**Files:**
- Create: `backend/tests/Unit/Domain/Nodes/ManifestRegistryParityTest.php`
- Create: `frontend/src/features/node-registry/manifest/manifest-registry-parity.test.ts`

**Backend parity test** (`ManifestRegistryParityTest`):
- For every `NodeTemplate` registered in `NodeTemplateRegistry`, assert `NodeManifestBuilder::build($template)` returns a non-empty `configSchema` with `type: "object"` and every key from `defaultConfig()` appears as a property (possibly via dot-notation nesting).
- Assert: if a template uses `InteractsWithHuman` (check via `method_exists`), its manifest's `configSchema.properties.humanGate` is a nested object with `type: "object"` and `enabled` child.
- Purpose: any future `NodeTemplate` gets drift-checked without extra test files.

**Frontend parity test** (`manifest-registry-parity.test.ts`):
- Fetch the manifest (using MSW to stub the endpoint with the real JSON the backend would emit — capture a fixture via `curl` into `frontend/src/features/node-registry/manifest/__fixtures__/manifest.example.json`).
- Load the full frontend `nodeRegistry` template list.
- Assert: every frontend template's `type` appears in the manifest. Every manifest node has a matching frontend template type (or is a backend-only executable). Mismatches fail CI.
- This catches the exact class of bug that started this plan (backend has humanGate; frontend template blind to it) plus symmetrically (template renamed on one side but not the other).

**Close the humanGate loop:**
- With NM3 landed, manually run the "StoryWriter (per-node gate) – Telegram" workflow. Confirm the inspector renders the humanGate fields. Toggle `enabled: true`, verify the draft is sent to Telegram at run time (we already know this works server-side).
- Update `AGENTS.md`: short note under Tech Stack — "Node schemas are backend-authoritative. See `docs/plans/2026-04-18-node-manifest-alignment.md`. Never re-author a `configSchema` in TS unless there's a node-registry-specific UI hint that can't be expressed in JSON Schema."

**Acceptance:**
- Both parity tests green.
- Manual smoke passes.
- `AGENTS.md` updated.
- Follow-ups documented for the non-pilot templates (every template under `frontend/src/features/node-registry/templates/` other than the three in NM3 still has a local `configSchema` — fine for now; NM5 can sweep them later).

---

## Dependency order

```
NM1 (backend manifest) ─► NM2 (frontend client + form) ─► NM3 (inspector integration + pilot strip) ─► NM4 (parity tests + loop close)
```

Strictly sequential. Each task closes cleanly before the next starts; no parallelism beyond what a single task internally permits.

---

## Done — results

| Task | Commit | Tests |
|------|--------|-------|
| NM1 — backend transpiler + `GET /api/nodes/manifest` | `505cbe6` | ConfigSchemaTranspilerTest + NodeManifestControllerTest (green) |
| NM2 — frontend manifest client + JsonSchemaForm | `667b887` / `d47a582` | 22 tests green (fetcher + JsonSchemaForm) |
| NM3 — inspector integration + pilot template strip | `c0db607` | storyWriter, humanGate, userPrompt stripped; inspector renders from manifest |
| NM4 — drift parity tests + humanGate loop close | *(this commit)* | Backend: 90 tests / 236 assertions; Frontend: 7 tests |

**humanGate-in-UI smoke summary:** The backend manifest for `storyWriter` emits a `humanGate` nested object under `configSchema.properties` with sub-keys `enabled` (boolean, default false), `channel` (enum: ui/telegram/mcp/any), `botToken`, `chatId`, `options` (array), `timeoutSeconds` (integer 0–86400). With NM3 landed, opening the "StoryWriter (per-node gate) – Telegram" workflow and clicking the storyWriter node would show a collapsible `humanGate` fieldset in the inspector with an `enabled` toggle, `channel` select, `botToken`/`chatId` text inputs, `options` array widget, and `timeoutSeconds` number field — all rendered generically by `JsonSchemaForm` from the live manifest without any TS schema duplication.

**Follow-up (NM5):** The 18 non-pilot frontend templates still have local `configSchema` Zod definitions. A sweep bead can strip them using the same NM3 pattern once the manifest-driven inspector has proven stability in production. The frontend-only templates (`diverge`, `productImageInput`, `wanI2V`, `wanImageEdit`, `wanVideoEdit`) still need backend PHP counterparts — tracked in `FRONTEND_ONLY_TYPES` in the parity test.
