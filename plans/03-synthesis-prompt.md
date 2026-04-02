# Synthesis Prompt for GPT Pro (Extended Reasoning)

> Copy everything below the line into a FRESH GPT Pro conversation with Extended Reasoning enabled.

---

I designed a software project and asked 4 frontier AI models to independently create competing design proposals. All 4 plans are included below in this message.

**PLAN A** is the base plan that you will revise. **PLANS B, C, and D** are competing proposals from other models. **MY NOTES** at the end highlight what each plan did best.

Your task: Treat PLAN A as the starting point. REALLY carefully analyze Plans B, C, and D with an open mind and be intellectually honest about what they did that's better than Plan A. Then come up with the best possible revisions to Plan A that artfully and skillfully blends the "best of all worlds" to create a true, ultimate, superior hybrid version.

You should provide me with:
1. For each competing plan (B, C, D): what they did better than Plan A and what you'd adopt
2. A complete series of git-diff style changes to Plan A to turn it into the new, enhanced, much longer and detailed plan
3. The FULL revised plan with all changes integrated (not just diffs — give me the complete document)

Be ruthlessly honest. If Plan B's mock execution is smarter than Plan A's preview-only approach, say so. If Plan C's concrete node definitions are better than Plan A's abstract ones, adopt them. If Plan D caught something everyone missed, integrate it.

---

## PLAN A (the base plan to revise)

# AI Video Workflow Builder Design Proposal

## Product Thesis

This product should be a **developer-first visual composer for AI video pipelines**, not a generic automation platform. The right v1 is a fast, local-first SPA where a user can drag nodes onto a canvas, connect them, configure each node, and inspect the data contract flowing between steps. The defining experience is not "enterprise orchestration" or "production job execution". It is **thinking clearly about a video pipeline visually**.

That framing matters. If v1 tries to be a hosted automation system, it will inherit the hardest parts too early: auth, queues, provider credentials, retries, billing, asset storage, and long-running execution. Those are valuable later, but they are not the thing that proves product value. The thing that proves value is whether users can design an AI video workflow in minutes and understand what every step expects and produces.

My recommendation is therefore:

- Build **a local-first, single-user, builder-only v1**.
- Make the graph editor excellent.
- Represent every node with explicit typed inputs, outputs, config schema, and preview transforms.
- Show intermediate data clearly in the UI, even before real execution exists.
- Leave remote execution, collaboration, provider integrations, and shared asset storage for later phases.

If you do this well, you get a product that is immediately demoable, useful for design/prototyping, and technically clean enough to evolve into a real execution platform.

## 1. Vision And Scope

### What Exactly We Are Building

We are building a browser-based workflow editor for AI video creation. Users assemble a directed graph from purpose-built nodes such as `scriptWriter`, `scenePlanner`, `promptRefiner`, `imageGenerator`, `voiceoverPlanner`, and `videoComposer`. Each node exposes:

- A human-readable purpose
- A typed configuration form
- One or more input ports
- One or more output ports
- A preview transformer that shows what data shape emerges from the node

The canvas is the center of gravity. Around it sits a node library, an inspector/config panel, and a data preview panel. The user's mental model should be: "I am designing a pipeline, and I can see what each step consumes and emits."

### V1 In Scope

V1 should include:

- Drag-and-drop node placement on a canvas
- Edge creation between compatible ports
- A curated library of video-oriented node types
- Typed node configuration forms
- Graph validation for missing required inputs, incompatible connections, and cycles
- Local persistence in the browser
- Import/export of workflow JSON
- Step-level preview data and schema inspection
- Basic workflow metadata: name, description, tags, last edited
- Undo/redo and autosave

### V1 Explicitly Out Of Scope

V1 should not include:

- Real AI execution against OpenAI, Runway, Kling, Pika, ElevenLabs, or other providers
- User accounts or multi-user collaboration
- Realtime editing
- Teams, comments, approval flows, or version branching UX
- Arbitrary scripting nodes
- Loops, conditions, retries, scheduling, or long-running jobs
- Hundreds of nodes on a single graph

This boundary is important. The product wins if it makes a 5-15 node pipeline feel obvious and trustworthy.

### Later Phases

Phase 2 should add real execution for a small curated set of nodes. Phase 3 can add cloud sync, credentials, remote runners, and asset storage. Phase 4 can add reusable subflows, templates, execution history, and lightweight collaboration.

The rule for expansion is simple: only add platform features when they directly strengthen the core workflow-design experience.

## 2. User Journeys

### Journey 1: Prototype A Short-Form Video Pipeline

A developer opens the app and lands on an empty canvas with a left sidebar of node categories: `Ideation`, `Script`, `Visuals`, `Audio`, `Video`, `Utility`. They drag in `Script Writer`, `Scene Planner`, `Image Generator`, and `Video Composer`.

They connect `Script Writer.story` to `Scene Planner.script`, then `Scene Planner.scenes` to `Image Generator.scenePlan`, and finally `Image Generator.frames` to `Video Composer.visualAssets`.

When they select `Script Writer`, the inspector shows a form with fields like `topic`, `tone`, `durationSeconds`, and `audience`. As they edit these values, the preview panel shows a sample output object for the node: title, hook, narration, and beats. Selecting `Scene Planner` shows how that output is transformed into an array of scenes with timing, shot intent, and prompt hints.

The user never runs a real AI model, but they still understand the pipeline. They can see the contracts, identify missing pieces, and export the workflow as JSON to share with a teammate or keep as a spec.

This is the core v1 success case.

### Journey 2: Diagnose A Broken Workflow

A user loads a saved workflow template called `product-launch-teaser`. One edge is highlighted red. Clicking it reveals a validation error: `VideoComposer.visualAssets expects AssetList<image>, but ImageGenerator.outputs.frames is SceneFrame[]`.

The inspector suggests the fix: insert `Asset Mapper` or change `Image Generator` output mode from `frames` to `assets`. The preview panel shows both the current output shape and the expected input shape side by side.

The user changes the node configuration and the error clears. The graph becomes valid again.

This journey is important because it demonstrates that the product is not just a drawing tool. It is a **contract-aware design environment**.

### Journey 3: Build From A Template And Fork It

A user starts from a built-in template called `NarratedStoryVideo`. The template pre-populates seven nodes, sensible defaults, and example preview data. They fork it locally, rename it, remove `Voiceover Planner`, and add `Subtitle Formatter`.

Because the app is local-first, changes autosave instantly. The user exports the workflow file and commits it into their own repo as a design artifact. They now have a repeatable visual specification for their future execution layer.

This matters commercially because templates make the product useful before provider integrations exist.

## 3. System Architecture

### Architecture Recommendation

Use a **thin, frontend-only architecture** for v1:

- React + Vite + TypeScript for the app shell
- `@xyflow/react` for the canvas and edge system
- Tailwind CSS for styling
- Zustand for editor state and commands
- Dexie on IndexedDB for local persistence
- Zod for runtime schemas and config validation
- React Hook Form for inspector forms

This stack is fast, maintainable, and aligned with the local-first requirement. Zustand is a better fit than Redux here because the domain is highly interactive and graph-centric; you want a small command-oriented store, not ceremony. Dexie is the right persistence choice because localStorage will become brittle once workflows, preview payloads, templates, and history entries get larger.

### High-Level Components

```
AppShell → CanvasSurface, NodeLibrary, InspectorPanel, PreviewPanel, WorkflowSidebar
  ↓
ZustandEditorStore → GraphValidator, PreviewEngine, PersistenceGateway
  ↓
PersistenceGateway → IndexedDB (Dexie)
GraphValidator → NodeRegistry
PreviewEngine → NodeRegistry
```

### Responsibilities

- `AppShell`: layout, routing, global keyboard shortcuts, panel management
- `CanvasSurface`: React Flow wrapper, drag/drop, edge interactions, selection, pan/zoom
- `NodeLibrary`: searchable source of draggable node templates
- `InspectorPanel`: selected node config editor and metadata editor
- `PreviewPanel`: schema view, example data, upstream/downstream contract comparison
- `ZustandEditorStore`: canonical in-memory state plus command actions
- `GraphValidator`: DAG rules, required input checks, type compatibility checks
- `PreviewEngine`: computes derived sample outputs from node config + upstream preview inputs
- `PersistenceGateway`: load/save/import/export, autosave, migrations
- `NodeRegistry`: central catalog of available node types and their schemas

## 4. Data Model

### Core Entities

- `WorkflowDocument`: top-level saved workflow
- `WorkflowNode`: node instance on a canvas
- `WorkflowEdge`: connection between node ports
- `NodeTemplate`: reusable definition for a node type
- `PortDefinition`: typed input/output contract for a node template
- `NodeConfig`: instance-specific settings for a node
- `PreviewValue`: sample or derived output shown in the UI
- `ValidationIssue`: graph or config problem surfaced to the user

### TypeScript Schema

```ts
export type DataType =
  | 'text'
  | 'prompt'
  | 'script'
  | 'scenePlan'
  | 'imageAssetList'
  | 'videoAsset'
  | 'audioTrack'
  | 'subtitleTrack'
  | 'json';

export interface PortDefinition {
  readonly key: string;
  readonly label: string;
  readonly direction: 'input' | 'output';
  readonly dataType: DataType;
  readonly required: boolean;
  readonly multiple: boolean;
  readonly description?: string;
}

export interface NodeTemplate<TConfig> {
  readonly type: string;
  readonly title: string;
  readonly category: 'script' | 'visuals' | 'audio' | 'video' | 'utility';
  readonly description: string;
  readonly inputs: readonly PortDefinition[];
  readonly outputs: readonly PortDefinition[];
  readonly defaultConfig: Readonly<TConfig>;
  readonly configSchema: z.ZodType<TConfig>;
  readonly buildPreview: (args: {
    config: TConfig;
    inputs: Record<string, unknown>;
  }) => Record<string, unknown>;
}

export interface WorkflowNode<TConfig = unknown> {
  readonly id: string;
  readonly type: string;
  readonly position: { readonly x: number; readonly y: number };
  readonly config: Readonly<TConfig>;
  readonly label: string;
}

export interface WorkflowEdge {
  readonly id: string;
  readonly sourceNodeId: string;
  readonly sourcePortKey: string;
  readonly targetNodeId: string;
  readonly targetPortKey: string;
}

export interface WorkflowDocument {
  readonly id: string;
  readonly version: 1;
  readonly name: string;
  readonly description: string;
  readonly tags: readonly string[];
  readonly nodes: readonly WorkflowNode[];
  readonly edges: readonly WorkflowEdge[];
  readonly viewport: {
    readonly x: number;
    readonly y: number;
    readonly zoom: number;
  };
  readonly createdAt: string;
  readonly updatedAt: string;
}
```

## 5. File Structure

```
src/
├── app/app.tsx
├── app/providers.tsx
├── app/routes.tsx
├── features/workflow-canvas/components/workflow-canvas.tsx
├── features/workflow-canvas/components/workflow-node.tsx
├── features/workflow-canvas/store/editor-store.ts
├── features/workflow-canvas/store/editor-selectors.ts
├── features/node-library/components/node-library-panel.tsx
├── features/node-inspector/components/node-inspector-panel.tsx
├── features/preview/components/preview-panel.tsx
├── features/workflows/data/workflow-db.ts
├── features/workflows/data/workflow-repository.ts
├── features/workflows/domain/workflow-types.ts
├── features/workflows/domain/graph-validator.ts
├── features/workflows/domain/preview-engine.ts
├── features/node-registry/node-registry.ts
├── features/node-registry/templates/script-writer.ts
├── features/node-registry/templates/scene-planner.ts
├── features/node-registry/templates/image-generator.ts
├── features/node-registry/templates/video-composer.ts
├── shared/lib/zod-helpers.ts
├── shared/ui/button.tsx
├── shared/ui/panel.tsx
```

## 6. Key Technical Decisions

### Decision 1: Local-First Persistence
Use Dexie + IndexedDB, not localStorage. Workflows quickly exceed localStorage comfort once you add templates, history, and preview payloads.

### Decision 2: Schema-Driven Nodes
Every node template must define `configSchema`, `inputs`, `outputs`, and `buildPreview`. One source of truth for forms, validation, compatibility, and preview rendering.

### Decision 3: Strictly DAG In V1
No loops, branches with conditions, or iterative nodes.

### Decision 4: Purpose-Built Data Types
Semantic types (`script`, `scenePlan`, `imageAssetList`) rather than generic JSON.

### Decision 5: Preview Instead Of Execution
V1 implements a preview engine, not an execution engine. Produces deterministic example outputs, surfaces schemas, recomputes incrementally.

### Decision 6: Command-Oriented Store
Explicit actions: `addNode`, `connectPorts`, `updateNodeConfig`, `duplicateSelection`, `undo`, `redo`, `loadWorkflow`.

## 7. Example Node Template

```ts
const scriptWriterTemplate: NodeTemplate<{
  topic: string;
  tone: 'educational' | 'cinematic' | 'playful';
  durationSeconds: number;
}> = {
  type: 'scriptWriter',
  title: 'Script Writer',
  category: 'script',
  description: 'Generates a short-form video script outline.',
  inputs: [],
  outputs: [
    {
      key: 'script',
      label: 'Script',
      direction: 'output',
      dataType: 'script',
      required: true,
      multiple: false,
    },
  ],
  defaultConfig: {
    topic: 'How AI video workflows work',
    tone: 'educational',
    durationSeconds: 45,
  },
  configSchema: z.object({
    topic: z.string().min(3),
    tone: z.union([
      z.literal('educational'),
      z.literal('cinematic'),
      z.literal('playful'),
    ]),
    durationSeconds: z.number().int().min(15).max(180),
  }),
  buildPreview: ({ config }) => ({
    script: {
      title: config.topic,
      hook: `In ${config.durationSeconds} seconds, explain ${config.topic}.`,
      beats: [
        'Open with a visual hook',
        'Explain the core concept',
        'Close with the result',
      ],
    },
  }),
};
```

## 8. UX Principles

Three-panel layout: Left (node library + templates), Center (canvas), Right (inspector + preview tabs).

- Selecting a node opens configuration first, then preview
- Validation errors appear directly on nodes/edges, not in a console
- Port compatibility visible before drop via hover states
- Empty states teach through templates, not documentation
- Preview shows both sample value AND declared schema

## 9. Risk And Unknowns

Product risks: builder-only may feel too abstract without strong previews. Mitigate with rich preview generation and templates. Over-generalization risk if arbitrary JSON dominates.

Technical risks: React Flow dynamic handle restore timing. Undo/redo fragility if store too granular. Solve with explicit command boundaries.

Scope risks: temptation to add providers early. Resist.

## 10. Testing Strategy

Unit: port compatibility, cycle detection, preview propagation, workflow import/export. Component: inspector forms, node states, preview rendering, validation badges. E2E (Playwright): drag node, connect, reject incompatible, edit config + see preview, autosave + reload, import/export round trip.

Concrete acceptance: (1) Create valid 3-node graph and persist. (2) Prevent cycle. (3) Display incompatible connection error. (4) Update config → recompute preview. (5) Export, reimport, preserve structure.

---

## PLAN B: CLAUDE OPUS

# Competing Proposal: AI Video Workflow Builder

## 1. Vision & Scope

### What We're Building

A lightweight visual pipeline editor where developers compose AI video generation workflows by wiring together specialized nodes. Each node is a discrete processing step — generate a script, create images, synthesize speech, composite video — and the canvas shows data transforming in real-time as it flows through the pipeline.

The core insight: AI video generation is a **multi-step pipeline**, not a single API call. Today, developers manually chain together script generation → image creation → audio synthesis → video composition, passing outputs between tools by hand. This builder makes that pipeline visual, debuggable, and reusable.

### V1 Scope (Ship This)

- **Canvas**: Drag-drop nodes from a sidebar, connect outputs → inputs with edges
- **5-7 core node types**: Text Prompt, Script Generator, Image Generator, Audio/TTS Generator, Video Composer, Preview, Output/Export
- **Data visibility**: Click any edge to see what data is flowing through it. Click any node to see its current input/output state
- **Execution**: Run the pipeline end-to-end. Nodes execute in dependency order. Progress shown per-node (pending → running → done/error)
- **Workflow persistence**: Save/load workflows as JSON files (local filesystem or localStorage)
- **Mock execution mode**: Run the full pipeline with mock AI responses for testing/development without burning API credits

### V2+ (Later)

- Real AI provider integrations (OpenAI, Runway, ElevenLabs, Replicate)
- Template workflows
- Branching/conditional logic nodes
- Backend execution server
- Custom node SDK
- Version history / undo-redo stack

### Explicitly NOT Building

Auth, user accounts, billing, marketplace, mobile, real-time collaboration.

## 2. User Journeys

Journey 1 "Text to Video": Developer drags Text Prompt → Script Generator → Image Generator → TTS Generator → Video Composer → Preview. Runs pipeline, sees data at every edge, watches video in Preview node.

Journey 2 "Debugging": Image Generator fails. Clicks it, sees error "prompt exceeded 500 char limit". Adjusts upstream config. Uses "Run from here" to re-run from that point without re-running upstream nodes.

Journey 3 "Batch Processing": Pastes JSON array of 5 topics. Downstream nodes detect array input and show "×5" badge. Pipeline runs 5 times.

## 3. Architecture

No backend for v1. Client-side execution. Node Registry pattern — each node exports definition, inputs, outputs, config, execute(), Component. Zustand for state. Two stores: workflow-store and execution-store.

Key file structure: src/store/, src/canvas/, src/sidebar/, src/detail/, src/engine/, src/nodes/{type}/, src/persistence/

## 4. Data Model

Port types: 'text' | 'text[]' | 'image' | 'image[]' | 'audio' | 'video' | 'json' | 'scene[]'

Type compatibility matrix with auto-wrapping (text→text[] ok, text[]→text not ok, json→anything ok).

Core types: PortDefinition, NodeTypeDefinition, WorkflowNode, WorkflowEdge, NodeExecutionState (status, inputs, outputs, error, timing).

Mock mode: each node ships `mockExecute` alongside `execute`, returning realistic fake data.

## 5. Key Decisions

- Client-side execution (zero infra, swap to ServerExecutor in v2)
- React Flow (xyflow) v12
- Zustand (minimal, no boilerplate)
- Async pipeline with topological sort (Kahn's algorithm)
- Mock mode: each node provides mockExecute()

## 6. Risks

React Flow customization ceiling. Large media in browser memory. Execution engine complexity creep. Port type system complication. AI API format variance. Unknown: scene granularity, video composition approach (FFmpeg.wasm recommended).

## 7. Testing

Unit: topo sort, cycle detection, input gathering, error propagation, port validation. Integration: canvas interactions, execution flow. E2E (Playwright): full journey, save/reload, error handling. Each node ships with __fixtures__/.

---

## PLAN C: GROK

10 concrete node types: user-prompt, script-generator, scene-splitter, image-generator, image-to-video, tts-voiceover, video-composer, subtitle-burner, final-export, review-node (human-in-loop).

Categories: input, llm, generation, audio, compose, output.

ExecutionRun as first-class entity with timing per node. IndexedDB caching for 24h. API keys encrypted with Web Crypto (AES-GCM). shadcn/ui + dark mode default. Zod-validated config forms.

Parallel execution for independent branches. "Run from here" partial execution (v1.1). Templates in v1.1. Max ~15 nodes per workflow.

Each execute function 30-80 lines with retry logic (exponential backoff). Provider-specific: fal.ai (Flux), Runway Gen-3, ElevenLabs, Kling.

File structure: src/app/, src/canvas/, src/core/ (types, registry, executor, topoSort, validation), src/store/, src/services/api/, src/lib/ (ffmpeg, zodSchemas).

Testing: Vitest unit, React Testing Library components, Playwright E2E, msw for mocked providers. 90%+ coverage on core.

---

## PLAN D: GEMINI ("LumenFlow")

"Smart Canvas, Dumb Engine" architecture. Logic-Based NLE for the AI era — dependency graph that generates assets, not a timeline.

Manual trigger per-node with propagation (not run-all-at-once). Each node has its own "Run" button. Execution pings child nodes when input data ready.

Data model: DataPayload wrapper with { value, status, error?, previewUrl? } on every port. Node data embeds both inputs and outputs directly.

Strongly typed handles with Zod schemas for validation via isValidConnection.

CORS awareness: v1 needs a proxy script for browser API calls. AbortController pattern for cancelling running nodes. Batch processing from CSV.

v2: CLI for headless execution (.lumen files in CI/CD). Custom Node SDK with hot-reload.

Core recommendation: build the Data Inspector first — "the biggest pain point in AI workflows is not knowing why a prompt failed or what the intermediate JSON looked like."

File structure: src/components/ (canvas, nodes, ui), src/hooks/ (useExecution, useFileExport), src/store/, src/services/api/, src/lib/, src/types/.

---

## MY COMPARISON NOTES

### The Big Split

GPT (you) took a fundamentally different position: v1 = builder/design tool only with NO real execution. The other 3 all include execution.

### Best Ideas To Adopt

**From Claude:**
- Mock execution mode (`mockExecute` on every node) — bridges the gap between preview-only and full execution
- Port type compatibility matrix with auto-wrapping rules
- "Run from here" partial execution
- Two separate Zustand stores (workflow vs execution state)
- Test fixtures shipped per node type

**From Grok:**
- 10 concrete node definitions with real provider names (not abstract placeholders)
- ExecutionRun as first-class entity with per-node timing
- Scene Splitter as a dedicated node type
- Review Node (human-in-the-loop pause)
- shadcn/ui + Zod config validation
- IndexedDB caching of execution artifacts

**From Gemini:**
- AbortController pattern for cancelling running nodes
- CORS proxy awareness for browser-based API calls
- Data Inspector as the core differentiator feature
- DataPayload wrapper (value + status + error on every port)
- Manual per-node execution trigger option

### Things All 4 Missed
- Sidebar UX details (search, categories, drag preview)
- Keyboard shortcuts and accessibility
- Workflow JSON paste/import validation and migration
- Error recovery on browser tab crash mid-execution
- Long polling handling for video generation APIs

### My Instinct
Hybrid of your builder-first philosophy + Claude's mock execution. Make the builder excellent AND let users test pipelines with mock data. Real AI execution stays in v2.

---

Now, with all of this in front of you:

1. Be intellectually honest about what each competing plan (B, C, D) did better than Plan A
2. Come up with the best possible revisions to Plan A that blend the "best of all worlds"
3. For each proposed change, give detailed analysis and rationale
4. Provide git-diff style changes to Plan A
5. Then provide the FULL revised plan as a complete document (not just diffs)

The revised plan should be significantly longer and more detailed than Plan A. Target 4000-6000 lines. Every section should be deep enough that an AI agent could implement it without asking questions.
