# Diverge Phase: Comparison Notes

## The Big Split

The 4 models split into two philosophical camps:

**Camp A — "Build the builder first" (GPT)**
- V1 is a design/prototyping tool with NO real AI execution
- Preview engine shows data shapes and contracts, not real outputs
- Execution comes in Phase 2+
- "The thing that proves value is whether users can design an AI video workflow in minutes and understand what every step expects and produces"

**Camp B — "Ship with execution" (Claude, Grok, Gemini)**
- V1 includes real workflow execution (with mock mode fallback)
- Users can actually run pipelines and see real/mock results
- More ambitious scope but more complexity

---

## Per-Model Breakdown

### GPT Pro
- **Strongest idea:** Preview Engine over Execution Engine. `buildPreview()` on every node that shows deterministic sample outputs WITHOUT calling any AI API. This means the product is useful immediately without API keys.
- **Strongest idea #2:** Semantic data types (`script`, `scenePlan`, `imageAssetList`) instead of generic types (`text`, `image`). Makes the product feel purpose-built for video.
- **Strongest idea #3:** Dexie + IndexedDB over localStorage. Workflows + preview payloads will outgrow localStorage fast.
- **Strongest idea #4:** Feature-oriented file structure (`src/features/workflow-canvas/`, `src/features/node-inspector/`). Cleaner than component-type grouping.
- **Strongest idea #5:** Command-oriented store with explicit undo/redo from day 1.
- **Strongest idea #6:** "Contract-aware design environment" — edges show type mismatches with suggested fixes.
- **Weakest spot:** May feel too abstract without real execution. Users might not see value in "just a diagram tool."
- **Unique angle:** Workflows as exportable design artifacts (commit to git as specs). Templates useful before providers exist.

### Claude Opus
- **Strongest idea:** Mock execution mode — every node ships with `mockExecute()` returning realistic fake data. Full pipeline works without API keys.
- **Strongest idea #2:** Port type compatibility matrix (text→text[] auto-wraps, json as wildcard).
- **Strongest idea #3:** "Run from here" — re-execute from a specific node without re-running upstream.
- **Weakest spot:** No specific AI provider details. Execution engine is sequential-only (no parallel branches).
- **Unique angle:** Two Zustand stores (workflow + execution) separated cleanly.

### Grok
- **Strongest idea:** Most concrete node definitions — 10 specific nodes with real provider recommendations (fal.ai for images, Runway Gen-3, ElevenLabs, Kling).
- **Strongest idea #2:** ExecutionRun as a first-class entity with timing per node.
- **Strongest idea #3:** IndexedDB caching of execution artifacts for 24h (reuse upstream outputs).
- **Strongest idea #4:** Scene Splitter as a dedicated node (LLM that splits script into scenes).
- **Strongest idea #5:** Review Node — human-in-the-loop pause point.
- **Weakest spot:** Over-scoped for v1 (encrypted keys, templates, subtitle burner, 10 nodes).
- **Unique angle:** shadcn/ui + dark mode default. Zod-validated config forms. Most production-ready feel.

### Gemini
- **Strongest idea:** "Smart Canvas, Dumb Engine" architecture. UI manages graph logic, execution offloaded to Worker pattern.
- **Strongest idea #2:** CORS awareness — v1 needs a proxy script for browser-based API calls.
- **Strongest idea #3:** AbortController pattern for cancelling running nodes (prevents crash on delete-while-running).
- **Strongest idea #4:** Batch processing from CSV input (50 products → 50 videos).
- **Strongest idea #5:** Manual trigger per-node with propagation (vs. run-all-at-once).
- **Weakest spot:** Thinnest proposal overall. Data model lacks detail. Missing persistence strategy.
- **Unique angle:** "LumenFlow" name. CLI for headless execution in v2. Data Inspector as THE killer feature.

---

## Cross-Cutting Agreements (all 4 agree)

- React + Vite + TypeScript + React Flow (xyflow) + Tailwind + Zustand
- DAG-only in v1 (no loops, no conditions)
- Node Registry pattern (each node type is self-contained)
- Client-side / local-first for v1 (no backend)
- Typed ports with connection validation
- Topological sort for execution ordering
- FFmpeg.wasm for video composition (where applicable)

## Cross-Cutting Disagreements

| Topic | GPT | Claude | Grok | Gemini |
|-------|-----|--------|------|--------|
| V1 has execution? | NO (preview only) | YES (mock + real) | YES (real + mock) | YES (manual trigger) |
| Persistence | Dexie/IndexedDB | localStorage + JSON | localStorage + IndexedDB | localStorage |
| Data types | Semantic (script, scenePlan) | Generic (text, image, video) | Generic (text, image, video) | Generic with DataPayload |
| File structure | Feature-oriented | Domain-grouped | App/canvas/core | Components/hooks/store |
| Undo/redo | V1 (explicit) | V2 | Not mentioned | Not mentioned |
| Node count v1 | 8-12 | 5-7 | 10 | 8-10 |
| API key handling | Not in v1 | Plain in browser | Web Crypto encrypted | localStorage + proxy |

---

## My Instinct

**The overall direction I prefer:**
GPT's philosophy is the smartest — make the builder excellent before adding execution complexity. BUT Claude's mock mode bridges the gap: you get the "feel" of execution without real API calls. **Hybrid: preview engine + mock execution.**

**Ideas I want to combine:**
1. GPT's `buildPreview()` + semantic types + Dexie + feature-oriented structure + undo/redo
2. Claude's mock execution mode + port compatibility matrix + "Run from here"
3. Grok's concrete node definitions + ExecutionRun entity + shadcn/ui + Zod config validation
4. Gemini's AbortController pattern + CORS awareness + Data Inspector emphasis

**Things all four missed:**
- How does the sidebar node palette actually work? (drag preview, categories, search?)
- Keyboard shortcuts and accessibility
- What happens when you paste a workflow JSON? (validation? migration?)
- Error recovery: what if the browser tab crashes mid-execution?
- How to handle nodes that need long polling (video generation takes minutes)
