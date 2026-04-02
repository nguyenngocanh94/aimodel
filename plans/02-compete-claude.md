# Competing Proposal: AI Video Workflow Builder
## Claude Opus — 2026-04-02

---

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
- Template workflows (e.g., "Text-to-Video", "Image Slideshow with Voiceover")
- Branching/conditional logic nodes
- Looping/iteration nodes
- Workflow sharing/export as code
- Backend execution server (run workflows headlessly)
- Custom node SDK (let developers build their own nodes)
- Version history / undo-redo stack

### Explicitly NOT Building

- Auth, user accounts, teams (developer tool, runs locally)
- Billing or metering
- A marketplace
- Mobile support
- Real-time collaboration

---

## 2. User Journeys

### Journey 1: "Text to Video" — The Happy Path

1. Developer opens the app. Empty canvas with a node sidebar on the left.
2. Drags a **Text Prompt** node onto the canvas. Types: "A 30-second explainer video about how solar panels work."
3. Drags a **Script Generator** node. Connects Text Prompt's `text` output → Script Generator's `prompt` input. The edge appears.
4. Drags an **Image Generator** node. Connects Script Generator's `scenes[]` output → Image Generator's `scenes` input.
5. Drags a **TTS Generator** node. Connects Script Generator's `narration` output → TTS's `text` input.
6. Drags a **Video Composer** node. Connects Image Generator's `images[]` output → Composer's `visuals` input. Connects TTS's `audio` output → Composer's `audio` input.
7. Drags a **Preview** node. Connects Composer's `video` output → Preview's `media` input.
8. Clicks **Run** in the toolbar. Nodes light up sequentially: Text Prompt ✓ → Script Generator ⏳ → ... → Preview ✓
9. Clicks the edge between Script Generator and Image Generator. A panel shows the actual scene descriptions generated.
10. Clicks the Preview node. Watches the composed video inline.
11. Saves the workflow as `solar-explainer.json`.

### Journey 2: "Debugging a Failed Node"

1. Developer loads a saved workflow. Clicks **Run**.
2. The Image Generator node turns red — error state.
3. Clicks the node. The detail panel shows:
   - Input received: `{ scenes: ["Scene 1: Sunlight hitting a panel...", ...] }`
   - Error: `"Image generation failed: prompt exceeded 500 char limit for scene 3"`
   - The problematic scene text is highlighted
4. Developer double-clicks the Script Generator node, adjusts the "max scene description length" parameter from 1000 → 400.
5. Clicks **Run from here** on the Script Generator node (re-runs from this point, doesn't re-run upstream nodes).
6. This time, all nodes complete. Green checkmarks across the board.

### Journey 3: "Building a Custom Workflow for Batch Processing"

1. Developer wants to generate 5 videos from 5 different topics.
2. Drags a **Text Prompt** node. Instead of a single string, pastes a JSON array: `["Solar panels", "Wind turbines", "Hydropower", "Nuclear", "Geothermal"]`
3. The Text Prompt node's output type automatically shows as `text[]` instead of `text`.
4. Downstream nodes detect array input and show a badge: "×5 — will batch process."
5. Clicks Run. The pipeline runs 5 times. Progress shows "3/5 complete" on each node.
6. Preview node shows a carousel of 5 videos.

---

## 3. System Architecture

### High-Level Components

```
┌─────────────────────────────────────────────────────┐
│                    Frontend (Vite + React)            │
│                                                       │
│  ┌──────────┐  ┌──────────────┐  ┌────────────────┐ │
│  │  Sidebar  │  │  Canvas      │  │  Detail Panel  │ │
│  │  (node    │  │  (React Flow)│  │  (node config, │ │
│  │  palette) │  │              │  │  data inspect)  │ │
│  └──────────┘  └──────────────┘  └────────────────┘ │
│                                                       │
│  ┌────────────────────────────────────────────────┐  │
│  │  Execution Engine (runs in browser / worker)    │  │
│  │  - Topological sort of node graph               │  │
│  │  - Sequential execution with dependency respect │  │
│  │  - State machine per node: idle→pending→running │  │
│  │    →success/error                               │  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
│  ┌────────────────────────────────────────────────┐  │
│  │  Node Registry                                  │  │
│  │  - Type definitions (inputs, outputs, config)   │  │
│  │  - Executor functions per node type             │  │
│  │  - UI components per node type                  │  │
│  └────────────────────────────────────────────────┘  │
│                                                       │
│  ┌──────────────┐  ┌──────────────────────────────┐  │
│  │  Store        │  │  Persistence Layer           │  │
│  │  (Zustand)    │  │  (localStorage / JSON file)  │  │
│  └──────────────┘  └──────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
```

### Key Architecture Decisions

**No backend for v1.** The execution engine runs entirely in the browser. AI API calls happen client-side (developer provides their own API keys). This keeps deployment trivial (`npm run build` → static files) and avoids infrastructure complexity.

**Node Registry pattern.** Every node type is a self-contained module that exports:
- `definition`: metadata (name, description, icon, category)
- `inputs`: typed input ports
- `outputs`: typed output ports
- `config`: user-configurable parameters (with defaults)
- `execute(inputs, config) → outputs`: the runtime function
- `Component`: the React component rendered on the canvas

This makes adding new node types trivial — drop a file in `src/nodes/`, register it, done.

**Zustand for state.** The workflow state (nodes, edges, node configs, execution state, data on edges) lives in a single Zustand store. React Flow controls the visual state; Zustand controls the data/execution state. They stay in sync via React Flow's `onNodesChange`/`onEdgesChange` callbacks.

### File Structure

```
src/
├── main.tsx                    # Entry point
├── App.tsx                     # Layout: sidebar + canvas + detail panel
├── store/
│   ├── workflow-store.ts       # Zustand: nodes, edges, configs
│   └── execution-store.ts      # Zustand: execution state, node results
├── canvas/
│   ├── WorkflowCanvas.tsx      # React Flow wrapper
│   ├── CustomNode.tsx          # Generic node renderer (uses registry)
│   ├── CustomEdge.tsx          # Edge with data preview on hover
│   └── NodePort.tsx            # Input/output port with type indicator
├── sidebar/
│   ├── NodePalette.tsx         # Draggable node list grouped by category
│   └── NodePaletteItem.tsx     # Single draggable node type
├── detail/
│   ├── DetailPanel.tsx         # Right panel: shows selected node/edge
│   ├── NodeConfig.tsx          # Edit node parameters
│   ├── DataInspector.tsx       # View input/output data for a node
│   └── EdgeDataView.tsx        # View data flowing through an edge
├── engine/
│   ├── executor.ts             # Topological sort + sequential execution
│   ├── graph.ts                # Graph utilities (cycle detection, ordering)
│   └── types.ts                # Core types: Port, NodeDef, ExecutionResult
├── nodes/
│   ├── registry.ts             # Node type registry (auto-discovers nodes)
│   ├── text-prompt/
│   │   ├── definition.ts       # Node metadata + ports
│   │   ├── executor.ts         # execute() function
│   │   └── Component.tsx       # Canvas UI for this node
│   ├── script-generator/
│   │   ├── definition.ts
│   │   ├── executor.ts
│   │   └── Component.tsx
│   ├── image-generator/
│   │   └── ...
│   ├── tts-generator/
│   │   └── ...
│   ├── video-composer/
│   │   └── ...
│   └── preview/
│       └── ...
└── persistence/
    ├── save-load.ts            # Serialize/deserialize workflow JSON
    └── schema.ts               # Workflow file format (versioned)
```

---

## 4. Data Model

### Core Types

```typescript
// === Port System ===

type PortType = 'text' | 'text[]' | 'image' | 'image[]' | 'audio' | 'video' | 'json' | 'scene[]';

interface PortDefinition {
  id: string;           // e.g., "prompt", "scenes", "audio"
  label: string;        // Human-readable: "Prompt Text"
  type: PortType;       // What kind of data this port carries
  required: boolean;    // Must be connected before execution?
}

// === Node Definition (static, from registry) ===

interface NodeTypeDefinition {
  type: string;                   // e.g., "script-generator"
  label: string;                  // "Script Generator"
  description: string;            // "Generates a multi-scene script..."
  icon: string;                   // Lucide icon name
  category: 'input' | 'ai' | 'output' | 'utility';
  inputs: PortDefinition[];
  outputs: PortDefinition[];
  configSchema: ConfigField[];    // User-editable parameters
  execute: (inputs: Record<string, unknown>, config: Record<string, unknown>) => Promise<Record<string, unknown>>;
}

interface ConfigField {
  key: string;
  label: string;
  type: 'string' | 'number' | 'select' | 'textarea' | 'boolean';
  default: unknown;
  options?: { label: string; value: string }[];  // For 'select' type
}

// === Node Instance (on canvas) ===

interface WorkflowNode {
  id: string;                     // Unique instance ID (uuid)
  type: string;                   // References NodeTypeDefinition.type
  position: { x: number; y: number };
  config: Record<string, unknown>; // User-set parameter values
}

// === Edge (connection between nodes) ===

interface WorkflowEdge {
  id: string;
  source: string;       // Source node ID
  sourcePort: string;   // Output port ID on source
  target: string;       // Target node ID
  targetPort: string;   // Input port ID on target
}

// === Execution State ===

type NodeStatus = 'idle' | 'pending' | 'running' | 'success' | 'error' | 'skipped';

interface NodeExecutionState {
  status: NodeStatus;
  inputs: Record<string, unknown> | null;   // What was fed in
  outputs: Record<string, unknown> | null;  // What came out
  error: string | null;
  startedAt: number | null;
  completedAt: number | null;
  duration: number | null;                  // ms
}

// === Workflow File (persistence) ===

interface WorkflowFile {
  version: 1;
  name: string;
  description: string;
  createdAt: string;          // ISO 8601
  updatedAt: string;
  nodes: WorkflowNode[];
  edges: WorkflowEdge[];
}
```

### Entity Relationships

```
NodeTypeDefinition (registry, static)
  └── defines shape of → WorkflowNode (instance, on canvas)
                            ├── has config values
                            ├── connected via → WorkflowEdge
                            │                    ├── sourcePort (references PortDefinition.id)
                            │                    └── targetPort (references PortDefinition.id)
                            └── tracked by → NodeExecutionState
                                               ├── captures inputs received
                                               ├── captures outputs produced
                                               └── captures errors
```

### Type Compatibility Matrix

Not all ports can connect. The type system enforces:

```
text    → text, text[]     ✓ (single text auto-wraps into array)
text[]  → text[]           ✓
text[]  → text             ✗ (ambiguous: which element?)
image   → image, image[]   ✓
image[] → image[]          ✓
audio   → audio            ✓
video   → video            ✓
json    → json             ✓ (wildcard — connects to anything)
scene[] → scene[]          ✓
```

---

## 5. Key Technical Decisions

### Decision 1: Client-side execution vs. backend server

**Recommendation: Client-side for v1.**

Pros:
- Zero infrastructure. `npm run build` → deploy anywhere static.
- Developer provides their own API keys. No proxy, no billing, no secrets management.
- Instant feedback loop during development.
- Works offline (with mock mode).

Cons:
- Long-running workflows block the tab (mitigated with Web Workers).
- API keys in the browser (acceptable for developer tool, not for SaaS).
- No scheduled/background execution.

The backend can come in v2 when needed. The execution engine interface stays the same — swap `BrowserExecutor` for `ServerExecutor`.

### Decision 2: React Flow vs. building canvas from scratch

**Recommendation: React Flow (xyflow) v12.**

React Flow handles all the hard canvas stuff: panning, zooming, node dragging, edge routing, minimap, controls. Building this from scratch would take months. React Flow gets us there in days.

Custom pieces we build on top:
- Custom node component (shows ports, status, mini-preview)
- Custom edge component (shows data type badge, click-to-inspect)
- Port type validation (prevent connecting incompatible types)

### Decision 3: State management

**Recommendation: Zustand.**

React Flow has its own internal state for visual positioning. We need a parallel store for:
- Node configurations (user parameters)
- Execution state (status, inputs, outputs per node)
- UI state (selected node, panel visibility)

Zustand is minimal, no boilerplate, works great with React Flow. Redux is overkill. Context API would cause re-render hell on a canvas app.

### Decision 4: Node executor pattern — sync pipeline vs. event-driven

**Recommendation: Async pipeline with topological sort.**

```typescript
async function executeWorkflow(nodes: WorkflowNode[], edges: WorkflowEdge[]) {
  const order = topologicalSort(nodes, edges);  // Kahn's algorithm
  const results: Map<string, Record<string, unknown>> = new Map();

  for (const nodeId of order) {
    const node = nodes.find(n => n.id === nodeId)!;
    const definition = registry.get(node.type)!;

    // Gather inputs from upstream node outputs
    const inputs = gatherInputs(nodeId, edges, results);

    setNodeStatus(nodeId, 'running');
    try {
      const outputs = await definition.execute(inputs, node.config);
      results.set(nodeId, outputs);
      setNodeStatus(nodeId, 'success');
    } catch (err) {
      setNodeStatus(nodeId, 'error', err.message);
      // Stop execution — downstream nodes get 'skipped'
      break;
    }
  }
}
```

Simple, predictable, debuggable. No event bus complexity. Parallel execution of independent branches can come in v2.

### Decision 5: Mock mode architecture

**Recommendation: Each node type provides a `mockExecute` alongside `execute`.**

```typescript
interface NodeTypeDefinition {
  // ... other fields
  execute: ExecuteFn;
  mockExecute: ExecuteFn;  // Returns realistic fake data, no API calls
}
```

Mock mode toggles which function runs. This means:
- Developers test workflows without API keys or credits
- Mock data is realistic (not just `null`), generated per node type
- The full UI pipeline works identically in both modes

---

## 6. Risks & Unknowns

### Risk 1: React Flow customization ceiling
React Flow is great out of the box, but deeply custom nodes (inline previews, port type indicators, execution status badges) may fight the library's assumptions. **Mitigation:** Build a proof-of-concept custom node in week 1 before committing.

### Risk 2: Large media in browser memory
Image generation produces base64 or blob URLs. A workflow with 10 images and a video could consume hundreds of MB. **Mitigation:** Use object URLs (`URL.createObjectURL`), revoke them aggressively, limit preview resolution. Consider streaming previews.

### Risk 3: Execution engine complexity creep
Branching, loops, error recovery, partial re-runs — each adds significant complexity. **Mitigation:** V1 is strictly linear/DAG. No branches, no loops. "Run from here" is the only partial-execution feature.

### Risk 4: Port type system gets complicated
As node types grow, the type system may need generics, union types, or custom schemas. **Mitigation:** Start with the simple enum. Add `json` as a wildcard escape hatch. Revisit only when a real node needs something the system can't express.

### Risk 5: AI API response format variance
Different AI providers return results in wildly different formats. **Mitigation:** Each node's executor is responsible for normalizing its provider's response into a standard output shape. The pipeline never sees raw API responses.

### Unknown 1: What's the right granularity for "scene"?
A script generator might output scenes, but what's a scene? A paragraph? A shot list? A timestamp range? **Need to decide** based on what downstream image/video generators actually accept.

### Unknown 2: Video composition approach
Compositing images + audio into video in the browser is non-trivial. Options:
- FFmpeg.wasm (heavy, ~25MB, but capable)
- Canvas API + MediaRecorder (lighter, less control)
- Server-side composition (defeats client-only goal)

**Recommendation:** FFmpeg.wasm for v1. The 25MB cost is acceptable for a developer tool.

---

## 7. Testing Strategy

### Unit Tests (Vitest)

**Execution engine:**
- Topological sort correctness (linear, branching, diamond dependencies)
- Cycle detection (reject circular graphs)
- Input gathering (correct outputs routed to correct inputs)
- Error propagation (downstream nodes skipped on upstream failure)
- Port type validation (incompatible connections rejected)

**Node executors:**
- Each node type has unit tests for its `execute` and `mockExecute` functions
- Test with edge cases: empty inputs, oversized inputs, malformed data

**Store:**
- Zustand store actions tested in isolation
- Node add/remove/connect/disconnect state transitions

### Integration Tests (Vitest + React Testing Library)

**Canvas interactions:**
- Drag node from palette → appears on canvas
- Connect two nodes → edge appears, data types validated
- Delete node → connected edges removed
- Select node → detail panel shows config

**Execution flow:**
- Build a 3-node workflow in test → execute → verify all nodes reach 'success'
- Build workflow with bad config → execute → verify error node + skipped downstream

### E2E Tests (Playwright)

- Full journey: open app → build workflow → run → see results in preview
- Save workflow → reload → verify restored correctly
- Error handling: disconnect mid-pipeline → verify UI shows partial results

### Test Data

Each node type ships with fixture data:
```
src/nodes/script-generator/
├── __fixtures__/
│   ├── input-simple.json       # Minimal valid input
│   ├── input-complex.json      # Multi-scene, edge cases
│   ├── output-expected.json    # Expected output for simple input
│   └── output-mock.json        # What mockExecute returns
```

### What We Don't Test (v1)

- Visual pixel-perfect rendering (canvas is too dynamic)
- AI API integrations (they're behind mock mode)
- Performance under 100+ nodes (explicitly out of scope)
