This is a compelling "seed." You’re moving away from the "chat-with-a-bot" paradigm and toward a "logic-factory" paradigm for media. Developers don't want another wrapper; they want an orchestration layer that feels like an IDE but acts like a creative suite.

By choosing **React Flow (xyflow)**, you’re already 50% of the way there. It handles the heavy lifting of the canvas, leaving you to solve the hard part: **state synchronization and execution logic.**

Here is the comprehensive design proposal for **"LumenFlow"** (let’s give it a working title).

---

## 1. Vision & Scope

### The North Star
LumenFlow is the **"Logic-Based NLE (Non-Linear Editor)"** for the AI era. While traditional NLEs (Premiere, Resolve) focus on a timeline, LumenFlow focuses on the **dependency graph** that generates the assets on that timeline.

### v1: The Developer Core (The "MVP")
* **The Canvas:** Fully interactive React Flow canvas with node snapping and edge routing.
* **The Node Library:** A curated set of 8-10 essential nodes:
    * **Input:** Text Prompt, File Upload.
    * **Logic:** LLM Scripting (OpenAI/Anthropic), Image Gen (Stable Diffusion/Midjourney via API).
    * **Processing:** Video Gen (Luma/Runway API), Audio/TTS (ElevenLabs).
    * **Output:** The "Composer" node (joining clips).
* **Data Flow:** Unidirectional flow with real-time data inspection (clicking a wire shows the JSON/Blob moving through it).
* **Persistence:** LocalStorage-based saving/exporting of workflow JSON.

### v2 & Beyond: The Ecosystem
* **Headless Execution:** A CLI to run `.lumen` files in a CI/CD pipeline.
* **Custom Node SDK:** Developers can write a `.ts` file and "hot-reload" it into the UI as a node.
* **Parallel Execution:** Triggering 5 video gens at once and awaiting all for the final cut.

---

## 2. User Journeys

### Scenario A: The "Quick-Cut" Marketer
1.  **Input:** User drags a **Text Input** node and types "A cyberpunk city in the rain."
2.  **Logic:** Connects to an **LLM Node** with a system prompt: "Expand this into 4 distinct cinematic scene descriptions."
3.  **Iteration:** The LLM outputs an array of 4 strings. The user sees this output in the UI.
4.  **Generation:** Connects the array to a **Batch Video Node**. It fires off 4 requests to an API.
5.  **Assembly:** Connects to a **Stitcher Node** that compiles them into a 20-second MP4.

### Scenario B: The Technical Artist (Dynamic Templating)
1.  **Trigger:** User uploads a CSV of 50 product names.
2.  **Pipeline:** For each row, an image is generated of the product, a TTS voiceover reads the product name, and a background music node generates a "happy" track.
3.  **Output:** The system generates 50 unique video ads. The developer uses the canvas to debug why "Product #12" failed (visible error state on the specific node).

---

## 3. System Architecture

We will follow a **"Smart Canvas, Dumb Engine"** architecture. The UI manages the graph logic, but we offload the execution to a structured "Worker" pattern.

### The Component Stack
* **Frontend Framework:** React + Vite + TypeScript.
* **State Management:** **Zustand**. It is significantly more performant than Redux for React Flow and allows for "transient" state updates (like node positions) without re-rendering the entire app.
* **Node Canvas:** `@xyflow/react`.
* **Styling:** Tailwind CSS + Shadcn/UI for the sidebar and overlays.
* **Communication:** Axios with interceptors for handling long-polling AI video APIs.

### High-Level Component Diagram


---

## 4. Data Model

The data model must be serializable to JSON. We represent the workflow as a `WorkflowGraph`.

### The Entity Schema

```typescript
// types.ts

export type NodeType = 'llm' | 'image-gen' | 'video-gen' | 'audio' | 'composer' | 'input';

export interface DataPayload {
  value: any;
  status: 'idle' | 'loading' | 'success' | 'error';
  error?: string;
  previewUrl?: string; // For images/videos
}

export interface LumenNode {
  id: string;
  type: NodeType;
  position: { x: number; y: number };
  data: {
    label: string;
    config: Record<string, any>; // API keys, model params
    inputs: Record<string, DataPayload>;
    outputs: Record<string, DataPayload>;
  };
}

export interface LumenEdge {
  id: string;
  source: string;
  target: string;
  sourceHandle: string; // The specific output port
  targetHandle: string; // The specific input port
}

export interface WorkflowGraph {
  nodes: LumenNode[];
  edges: LumenEdge[];
  viewport: { x: number; y: number; zoom: number };
}
```

---

## 5. Key Technical Decisions

### Decision 1: Execution Strategy (Reactive vs. Manual)
**Recommendation:** **Manual Trigger with Propagation.**
* *Why:* In a video workflow, you don't want a node to execute the moment a wire is connected (that costs money!).
* *Solution:* Each node has a "Run" button. When clicked, it executes and then "pings" child nodes that their input data is ready.

### Decision 2: The "Composer" Node
**Recommendation:** **FFmpeg.wasm for Client-side preview.**
* *Why:* To stay "Simple" and "Developer-first," we should avoid a heavy backend for v1. We can use the browser's resources to stitch videos using WebAssembly.
* *Benefit:* Zero server costs for video processing during the "building" phase.

### Decision 3: Handling Port Types
**Recommendation:** **Strongly Typed Handles.**
* *Why:* You shouldn't be able to connect a "Video Blob" output to a "Text Prompt" input. 
* *Implementation:* We will use Zod schemas to define the `dataType` of every handle. React Flow's `isValidConnection` prop will check these schemas in real-time.

---

## 6. Project Structure

```text
src/
├── components/
│   ├── canvas/
│   │   ├── FlowCanvas.tsx
│   │   └── ConnectionLine.tsx
│   ├── nodes/
│   │   ├── BaseNode.tsx       # Wrapper for all nodes (styles, port logic)
│   │   ├── LLMNode.tsx
│   │   ├── VideoGenNode.tsx
│   │   └── OutputNode.tsx
│   └── ui/                    # Shadcn components
├── hooks/
│   ├── useExecution.ts        # The logic that "walks" the graph
│   └── useFileExport.ts
├── store/
│   └── useStore.ts            # Zustand store
├── lib/
│   ├── ai-clients.ts          # Wrappers for OpenAI, Replicate, etc.
│   └── utils.ts
└── types/
    └── workflow.d.ts
```

---

## 7. Implementation Detail: The Execution Engine

The core of the app isn't the drawing; it's the **Graph Traversal**. We need an engine that understands dependencies.

### Code Example: The Execution Hook

```typescript
// useExecution.ts
import { useStore } from '../store/useStore';

export const useExecution = () => {
  const { nodes, edges, updateNodeData } = useStore();

  const runNode = async (nodeId: string) => {
    const node = nodes.find((n) => n.id === nodeId);
    if (!node) return;

    // 1. Gather inputs from incoming edges
    const incomingEdges = edges.filter((e) => e.target === nodeId);
    const inputData = incomingEdges.reduce((acc, edge) => {
      const sourceNode = nodes.find((n) => n.id === edge.source);
      acc[edge.targetHandle] = sourceNode?.data.outputs[edge.sourceHandle];
      return acc;
    }, {});

    // 2. Execute Node Logic (Example: Image Gen)
    updateNodeData(nodeId, { status: 'loading' });
    
    try {
      const result = await executeNodeLogic(node.type, inputData, node.data.config);
      
      // 3. Update outputs and trigger children
      updateNodeData(nodeId, { 
        outputs: { default: result }, 
        status: 'success' 
      });
      
      // Auto-triggering logic could go here if desired
    } catch (err) {
      updateNodeData(nodeId, { status: 'error', error: err.message });
    }
  };

  return { runNode };
};
```

---

## 8. Risks & Unknowns

1.  **CORS & API Limits:** Most AI APIs don't like being called directly from a browser (CORS issues and leaking API keys). 
    * *Mitigation:* v1 will require users to run a tiny "Proxy Server" (provided as a script) or input their own keys (stored only in LocalStorage).
2.  **Large File Handling:** Browsers struggle with 4K video blobs in memory.
    * *Mitigation:* Use `URL.createObjectURL` for previews and strictly limit the resolution in the v1 Composer node.
3.  **Complex State Sync:** If a user deletes a node while it's "running," the app might crash.
    * *Mitigation:* Implement a robust AbortController pattern for every node execution.

---

## 9. Testing Strategy

* **Unit Testing (Vitest):** Focus on the graph traversal logic. If I provide Graph A, does the engine identify the correct execution order?
* **Integration Testing (Playwright):**
    * Drag a node from the sidebar to the canvas.
    * Connect Node A to Node B.
    * Verify the "Connection Valid" state.
* **Visual Regression:** Use Chromatic to ensure that complex node layouts don't break across browser versions.

---

### Final Recommendation

Start by building the **"Data Inspector."** The biggest pain point in AI workflows is not knowing *why* a prompt failed or what the intermediate JSON looked like. If LumenFlow makes every wire "peekable," developers will use it as their primary debugging tool for video pipelines.

**Does this architectural direction align with the specific AI models (e.g., Sora, Kling, Runway) you're planning to support first?**
