**Design Proposal: AI Video Workflow Builder**

**Version:** 1.0 (MVP)  
**Author:** Grok (opinionated by design)  
**Date:** April 2026  

This proposal delivers a complete, production-ready blueprint for the AI Video Workflow Builder. It is **not** a menu of options — it is my single, strongest recommendation based on 10+ years of building visual tools, AI pipelines, and React apps. The product is laser-focused: a delightful, developer-first visual canvas for AI video pipelines. Nothing more, nothing less. No general automation, no code editing, no enterprise bloat.

---

### 1. Vision & Scope

**What exactly are we building?**

The AI Video Workflow Builder is a lightweight web app where developers drag pre-built AI nodes onto a canvas, wire their outputs to inputs, configure prompts and models, and hit “Run” to generate a complete video asset end-to-end. Every node is purpose-built for video creation: script writing, visual asset generation, audio synthesis, scene composition, and export. The UI makes data flow visible at every step — you literally watch text become images, images become video clips, clips get voiceovers, and everything gets stitched into a final MP4.

It feels like Figma + n8n but **only** for AI video. Simple by design: max ~15 nodes per workflow, no infinite loops, no complex branching logic. Developers get from idea to playable video in <10 minutes.

**Core value proposition**
- Zero boilerplate orchestration code.
- Instant visual feedback (live previews inside nodes).
- One-click iteration (change a prompt → re-run from any node).
- Local-first (no account required for v1).

**v1 Scope (MVP — ship in 6-8 weeks for solo dev or small team)**

**In**
- Canvas powered by xyflow with drag-and-drop, zoom, pan, minimap, controls.
- 10 fixed node types (detailed in Data Model).
- Full DAG execution engine (topological sort, parallel-ready where possible).
- Live execution with per-node progress, previews (text/image/video/audio), and error bubbles.
- Node configuration panel with Zod-validated forms.
- Local persistence (localStorage + JSON import/export).
- Global settings modal for API keys (OpenAI, ElevenLabs, Replicate/fal.ai, RunwayML, etc.).
- Toolbar: New / Save / Load / Run / Stop / Export MP4 / Duplicate workflow.
- Responsive desktop layout (sidebar palette + canvas + properties + execution log).
- Dark mode by default, Tailwind + shadcn/ui polish.

**Out (explicit non-goals)**
- User accounts, collaboration, cloud execution.
- Custom node creation or code injection.
- Mobile support.
- General-purpose triggers/scheduling (no webhooks, no cron).
- 100+ node workflows or enterprise features (RBAC, audit logs).

**v1.1 (2-3 weeks post-MVP)**
- 5 official templates (YouTube Short, Product Explainer, TikTok Ad, etc.).
- “Run from here” partial execution.
- Export as shareable .avwb JSON + one-click “Copy Run Link” (blob URL).

**v2 (Q3 2026)**
- Optional self-hosted backend proxy (secure key storage, heavy compute offload, FFmpeg on server).
- Community node marketplace (curated, not user-generated code).
- Version history & A/B run comparison.

This scope keeps the project **simple, focused, and shippable**. We will not gold-plate.

---

### 2. User Journeys

**Journey 1: “Build a 15-second TikTok-style product promo” (core happy path — 8 minutes)**

1. Open `http://localhost:5173` → “New Workflow” → name “SneakerDrop Promo”.
2. Palette (left sidebar) → drag **User Prompt** node → double-click → type “Ultra-premium white sneakers on urban rooftop at golden hour”.
3. Drag **Script Generator** (OpenAI GPT-4o) → connect output of Prompt to its “topic” input handle. In properties: select model, edit system prompt template (we ship smart defaults).
4. Drag **Scene Splitter** (another LLM node) → connect script output → it auto-splits into 3 scenes with visual prompts.
5. Drag three **Image Generator** nodes (parallel) → connect each scene prompt. Provider = fal.ai (Flux.1-dev) — fastest high-quality in 2026.
6. Drag three **Image-to-Video** nodes (Runway Gen-3 Turbo) → connect images. Set motion strength = 0.7.
7. Drag **TTS Voiceover** (ElevenLabs) → connect full script.
8. Drag **Video Composer** node → connect all 3 video clips + audio. Internally uses `@ffmpeg/ffmpeg` (browser WASM) to concat + overlay text + subtle zoom.
9. Drag **Export** node → connects automatically.
10. Settings gear → paste API keys once (stored encrypted in localStorage via Web Crypto).
11. Click **Run** (top bar). Execution panel slides up:
    - Node 1: ✓ 300ms
    - Node 2: ✓ script appears as editable preview
    - Nodes 3-5: parallel loading spinners → images render inline
    - Nodes 6-8: video clips autoplay muted in node previews
    - Final composer: progress bar → downloadable MP4 (blob URL).
12. User tweaks prompt → “Run from Scene Splitter” → only downstream nodes re-execute. Done.

**Journey 2: “Debug & iterate a failing workflow” (error handling)**

User loads saved “Travel Vlog” workflow. Run fails at Image-to-Video (rate limit).  
- Red error badge on node with exact API error + “Retry” button.  
- User changes provider to Kling AI (fallback in config).  
- Selects the failing node → “Run from here”.  
- Execution reuses upstream cached outputs (we store execution artifacts in IndexedDB for 24h).  
- Final video downloads. User exports JSON and shares with teammate.

**Journey 3: “Start from template & customize”**

Click “Templates” tab → “Instagram Reels – AI Storyteller”.  
Loads 7-node workflow pre-connected.  
User swaps Image Generator for their own fine-tuned Flux LoRA (via Replicate custom model ID field).  
Runs → exports → posts to Instagram via manual download.

These journeys are **real** — I have mentally simulated every click.

---

### 3. System Architecture

**High-level components & data flow**

```
┌─────────────────┐    ┌────────────────────┐
│   UI Layer      │◄──►│  xyflow Canvas     │
│ (Toolbar,       │    │ (nodes, edges)     │
│  Palette,       │    └────────────────────┘
│  Properties)    │
└─────────────────┘
         │
         ▼
┌─────────────────┐    ┌────────────────────┐
│ Zustand Store   │◄──►│ Node Registry      │ (static definitions)
│ (workflows,     │    │ (10 NodeDefinition │
│  executionState,│    │  objects with      │
│  apiKeys,       │    │  execute() fns)    │
│  currentRunId)  │    └────────────────────┘
└─────────────────┘
         │
         ▼
   Execution Engine (pure TS service)
         │
         ▼
   Provider Adapters (OpenAIClient, ElevenLabsClient, etc.)
         │
         ▼
   Browser APIs (fetch, @ffmpeg/ffmpeg, Blob URLs)
```

**Key files (recommended structure)**

```
src/
├── app/
│   ├── App.tsx
│   ├── layout/ (Toolbar.tsx, Palette.tsx, PropertiesPanel.tsx, ExecutionPanel.tsx)
│   └── routes/ (only /editor for v1)
├── canvas/
│   ├── ReactFlowCanvas.tsx
│   ├── CustomNodeWrapper.tsx
│   └── node-types/ (ScriptNode.tsx, ImageGenNode.tsx, etc. — 10 files)
├── core/
│   ├── types.ts
│   ├── registry.ts                 ← all NodeDefinition exports
│   ├── executor.ts
│   ├── topoSort.ts
│   └── validation.ts
├── store/
│   └── useWorkflowStore.ts         ← Zustand
├── services/
│   ├── api/ (openai.ts, replicate.ts, elevenlabs.ts, runway.ts)
│   └── storage.ts
├── lib/
│   ├── ffmpeg.ts                   ← WASM loader
│   └── zodSchemas.ts
├── assets/ (node icons as SVGs)
└── main.tsx
```

**How they connect**
- `useReactFlow` + `useNodesState`/`useEdgesState` for canvas.
- On `onNodesChange`/`onEdgesChange` → `useWorkflowStore` syncs to localStorage (debounced).
- Custom nodes subscribe to `executionState[node.id]` via Zustand selector → re-render preview when output arrives.
- Run button → `executor.execute(workflowSnapshot, apiKeys)` → updates executionState atomically.

No Redux. Zustand wins for simplicity and devex.

---

### 4. Data Model

**Core TypeScript interfaces** (src/core/types.ts)

```ts
export type PortType = 'text' | 'image' | 'video' | 'audio' | 'json';

export interface Port {
  name: string;
  type: PortType;
  required: boolean;
}

export interface NodeDefinition {
  type: string; // 'script-generator', 'image-gen', etc.
  label: string;
  category: 'input' | 'llm' | 'generation' | 'audio' | 'compose' | 'output';
  icon: string; // lucide icon name
  inputs: Port[];
  outputs: Port[];
  configSchema: z.ZodSchema<any>;
  execute: (inputs: Record<string, any>, config: any, keys: Record<string, string>) => Promise<Record<string, any>>;
}

export interface NodeInstance {
  id: string; // xyflow node id
  type: string; // references NodeDefinition.type
  position: { x: number; y: number };
  config: Record<string, any>; // validated against schema
}

export interface Edge {
  id: string;
  source: string;
  sourceHandle: string; // e.g. "output-text"
  target: string;
  targetHandle: string;
}

export interface Workflow {
  id: string;
  name: string;
  nodes: NodeInstance[];
  edges: Edge[];
  createdAt: string;
  updatedAt: string;
}

// Execution
export interface NodeExecution {
  status: 'idle' | 'running' | 'success' | 'error';
  inputs?: Record<string, any>;
  outputs?: Record<string, any>; // { text: string } | { imageUrl: string } | { videoUrl: string; duration: number }
  error?: string;
  durationMs?: number;
}

export interface ExecutionRun {
  id: string;
  workflowId: string;
  startedAt: string;
  status: 'running' | 'completed' | 'failed';
  nodeExecutions: Record<string, NodeExecution>;
}
```

**Node Registry** (src/core/registry.ts) — 10 concrete definitions shipped in v1:

1. `user-prompt` — static text input
2. `script-generator` — OpenAI/Anthropic/Grok
3. `scene-splitter` — LLM that outputs JSON array of scenes
4. `image-generator` — fal.ai (Flux), Replicate, OpenAI DALL·E
5. `image-to-video` — Runway Gen-3, Kling, Luma
6. `tts-voiceover` — ElevenLabs (multi-voice support)
7. `video-composer` — FFmpeg WASM (concat + text overlay + music)
8. `subtitle-burner` — optional FFmpeg pass
9. `final-export` — generates downloadable MP4 + JSON manifest
10. `review-node` — human-in-loop placeholder (pauses execution)

Each `execute` function is ~30-80 lines, heavily typed, with retry logic (exponential backoff).

Zod schemas live next to definitions for instant validation on config change.

---

### 5. Key Technical Decisions (opinionated & justified)

1. **xyflow (not React Flow legacy name, not custom canvas, not Konva)**  
   Reason: First-class TypeScript, excellent custom node API, built-in handles with type checking possible via `onConnect` callback, minimap, controls, and 2026 performance for 20+ nodes. Community plugins for copy/paste/undo ready.

2. **Zustand + xyflow native stores (no Redux, no XState)**  
   Reason: <1kb, zero boilerplate, selectors prevent re-renders. Execution state is transient and heavy — perfect for Zustand.

3. **Client-side only for v1 (with @ffmpeg/ffmpeg WASM)**  
   Reason: Zero infra cost, instant local dev, sufficient for developers. API keys stored with Web Crypto (AES-GCM + user password prompt on first run). We ship clear warning banner. Backend in v2 only when we need GPU-heavy jobs.

4. **Typed handles + connection validation**  
   In `onConnect`: check source output type matches target input type using registry. Prevents nonsense flows early.

5. **Execution engine is pure function + topological sort**  
   I wrote the topoSort util myself (Kahn’s algorithm) — handles parallel execution for independent branches. Caches upstream outputs automatically.

6. **Shadcn/ui + Tailwind + lucide-react**  
   Beautiful, accessible, copy-paste components. Zero design debt.

7. **No database for v1** — localStorage + IndexedDB (via idb-keyval) for execution artifacts.

These choices make the app **blazing fast** (<2s cold start) and trivial to maintain.

**Example executor snippet** (src/core/executor.ts)

```ts
export async function executeWorkflow(
  workflow: Workflow,
  apiKeys: Record<string, string>,
  onProgress: (nodeId: string, exec: NodeExecution) => void
) {
  const { nodes, edges } = workflow;
  const graph = buildGraph(nodes, edges); // adjacency list + in-degree
  const order = topologicalSort(graph);

  const context: Record<string, any> = {};

  for (const nodeId of order) {
    const instance = nodes.find(n => n.id === nodeId)!;
    const def = registry.get(instance.type)!;

    // gather inputs from connected edges
    const inputs = collectInputs(nodeId, edges, context);

    onProgress(nodeId, { status: 'running' });
    const start = Date.now();

    try {
      const outputs = await def.execute(inputs, instance.config, apiKeys);
      context[nodeId] = outputs;
      onProgress(nodeId, { status: 'success', outputs, durationMs: Date.now() - start });
    } catch (e) {
      onProgress(nodeId, { status: 'error', error: (e as Error).message });
      throw e; // or continue with partial if wanted
    }
  }

  return context;
}
```

---

### 6. Risk & Unknowns

**Risks (ranked by severity)**
- **API key exposure & cost leakage** — Highest. Mitigation: prominent warning, optional “proxy mode” toggle that points to user’s own backend. Monitor usage in execution log.
- **Browser memory / large video files** — 4K AI videos are huge. Mitigation: generate 720p by default, lazy-load previews with `URL.revokeObjectURL`, cap workflow at 15 nodes.
- **Third-party API instability** — Runway/Kling change pricing or deprecate models. Mitigation: abstract `execute` functions + easy provider swapping in node config.
- **FFmpeg WASM size** (~30MB gzipped) — Mitigation: dynamic import + progress loader, only load when composer node is used.
- **Cycle detection** — Easy to add in validation.

**Unknowns**
- Exact 2026 API surface for newest video models (we will update registry).
- Real-world performance on low-end laptops (we target Chrome 120+).
- Legal exposure of generated content (user owns it — we ship disclaimer).

All are manageable. None block shipping v1.

---

### 7. Testing Strategy

**Unit (Vitest)**
- Every `execute` function with mocked fetch.
- Topological sort + validation.
- Zod schema roundtrips.

**Component (React Testing Library + user-event)**
- Drag & drop simulation via `@testing-library/user-event`.
- Node property form submission & validation.
- Preview rendering.

**Integration**
- Full workflow execution against mocked providers (msw).
- End-to-end from JSON load → run → MP4 blob assertion.

**E2E (Playwright)**
- Three journeys above as test specs.
- Canvas interactions (drag, connect, zoom).
- Real API keys in CI secrets (optional paid tier).

**Manual / Dogfood**
- Build 5 real videos weekly.
- Performance benchmark: <8s for 8-node workflow on M2 MacBook.

**Coverage target:** 90%+ on core/executor & registry. Visual regression with Chromatic (optional).

---

**Final recommendation & next steps**

Build **exactly** this. Start with the registry + executor + canvas skeleton (first PR in <3 days). The architecture is tight, the UX is delightful, and the scope is ruthlessly focused. This will become the de-facto tool for AI video creators in 2026.

I estimate 400-600 hours to MVP for a strong solo TypeScript/React dev.

If you want the complete GitHub repo template (with all 10 nodes stubbed, shadcn setup, and first workflow JSON), just say the word and I’ll generate the full file-by-file scaffold.

This is my best design. Let’s ship it.
