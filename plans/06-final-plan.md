# AI Video Workflow Builder Design Proposal
## Revised Hybrid Specification

## Product Thesis

This product should be a **developer-first visual composer and mock-execution sandbox for AI video pipelines**, not a generic automation platform and not a premature hosted orchestration system.

The most important strategic decision is to preserve the strongest idea from the original Plan A: **the core value is helping users think clearly about an AI video pipeline visually**. The product should make pipeline structure, data contracts, payload shapes, and step-by-step transformations easy to understand. That remains the heart of the product.

However, the original Plan A was too strict in limiting v1 to preview transforms only. That approach keeps scope clean, but it undershoots user value. A static preview-only tool risks feeling like a diagram editor with nice schemas. The revised v1 should therefore go one step further without collapsing into platform complexity:

- local-first
- single-user
- browser SPA
- no real provider execution
- no auth
- no backend
- no queues
- no billing
- no remote asset storage
- but **yes to deterministic mock execution**

This hybrid gives the product a much stronger v1. Users can build a workflow, inspect its contracts, and then test its behavior safely using realistic, deterministic mock outputs. That creates a better bridge between design and future execution. It also avoids all the complexity traps that come from adding real AI providers too early.

The revised product philosophy is:

- The canvas is where users define the structure.
- The Data Inspector is where users build trust.
- Mock execution is how users validate behavior.
- Real provider execution is a later extension, not a v1 responsibility.

If this is executed well, the product will be useful immediately as:

- a workflow design tool
- a spec authoring tool
- a debugging and validation environment
- a template-driven prototyping tool
- a local artifact for teams planning future execution systems

The product should not try to prove "we can run AI video jobs in production" in v1. It should prove a narrower but stronger claim: **we can make AI video pipelines understandable, debuggable, and reusable before the hard infrastructure exists**.

---

## 1. Vision And Scope

### 1.1 What Exactly We Are Building

We are building a browser-based workflow editor for AI video creation pipelines. Users compose a directed graph from purpose-built nodes such as:

- `userPrompt`
- `scriptWriter`
- `sceneSplitter`
- `promptRefiner`
- `imageGenerator`
- `imageAssetMapper`
- `ttsVoiceoverPlanner`
- `subtitleFormatter`
- `videoComposer`
- `reviewCheckpoint`
- `finalExport`

Each node is a strongly defined contract, not a loose box with arbitrary JSON. Every node exposes:

- a human-readable purpose
- a stable node type identifier
- a category
- a typed configuration schema
- a list of input ports
- a list of output ports
- validation rules
- preview generation logic
- optional deterministic mock execution logic
- fixtures for test and demo data

The user's mental model should be simple and consistent:

- I place steps on a canvas.
- I connect compatible outputs to inputs.
- I configure how each step behaves.
- I inspect what data a step expects and produces.
- I can mock-run one node, a branch, or the whole workflow.
- I can see exactly where a pipeline is invalid or confusing.

This is not a generic Zapier competitor, not a generic DAG runner, and not a timeline editor. It is a **logic-based non-linear editor for AI video pipelines**, where the graph represents asset generation and transformation rather than temporal sequencing.

### 1.2 The Defining Experience

The revised product's defining experience is:

1. A user drags purpose-built nodes onto a canvas.
2. They connect only compatible ports.
3. They configure a node with a type-safe form.
4. They inspect live contracts and payloads through a first-class Data Inspector.
5. They run the graph in deterministic mock mode.
6. They understand what each step consumes, emits, and why.

That combination matters. The product should feel like a **contract-aware design environment**, not a raw node editor and not a speculative production runner.

### 1.3 V1 In Scope

V1 includes:

- Drag-and-drop node placement on a canvas
- Node library with search, categories, and drag previews
- Edge creation between compatible ports
- Strong node templates with typed config schemas
- Graph validation:
  - missing required inputs
  - incompatible types
  - invalid cardinality
  - cycles
  - orphan nodes
  - disabled nodes
- Data Inspector:
  - selected node inputs
  - selected node outputs
  - selected edge payload
  - current payload state
  - declared schema
  - expected schema
  - upstream/downstream comparison
  - validation and coercion hints
  - payload lineage
- Deterministic preview generation for every node
- Deterministic mock execution for executable nodes
- Manual per-node execution
- Run workflow
- Run from here
- Run up to here
- Cancel run
- Execution history for recent local runs
- Local persistence in IndexedDB
- Import/export of versioned workflow JSON
- Autosave
- Undo/redo
- Workflow metadata:
  - name
  - description
  - tags
  - createdAt
  - updatedAt
  - schemaVersion
- Built-in templates
- Crash recovery for unsaved edits and interrupted local runs
- Keyboard shortcuts and basic accessibility support

### 1.4 V1 Explicitly Out Of Scope

V1 does not include:

- Real provider execution against OpenAI, Runway, Kling, Pika, ElevenLabs, fal.ai, Replicate, or any external service
- API keys, secrets management, or provider credential storage
- Remote execution servers
- Queues, retries, polling, webhooks, or background jobs
- Remote asset storage
- User accounts
- Multi-user collaboration
- Realtime editing
- Team workspaces
- Comments or approval flows
- Scripting/custom code nodes
- Arbitrary plugin SDK
- Conditions, loops, retries, schedulers, or dynamic branching
- Production-grade binary media generation
- Large graphs beyond approximately 15 nodes
- Multi-tab sync guarantees
- Mobile-first UX

The scope rule is:

> If a feature primarily helps users understand and validate workflow design locally, it belongs in v1. If it primarily helps execute, scale, host, share, secure, or operationalize workflows remotely, it belongs later.

### 1.5 Product Success Criteria

V1 succeeds if a user can:

- create a 5-10 node AI video workflow in under 10 minutes
- understand every edge's payload and schema
- identify exactly why a connection is invalid
- mock-run a pipeline and get realistic step outputs
- rerun a failed step without rerunning everything
- export the workflow as a reusable design artifact
- recover from a tab refresh or crash without losing meaningful work

V1 fails if users describe it as:

- "just a drawing tool"
- "too fake to be useful"
- "unclear what the data shape is"
- "annoying to debug"
- "half of the work is recovering from broken imports or lost state"

### 1.6 Later Phases

Phase 2 should add:

- a `RealExecutor` interface implementation
- curated provider integrations for 2-3 nodes
- local or desktop bridge for provider calls if browser-only becomes limiting
- artifact storage strategy
- long-polling abstractions for long-running generation APIs

Phase 3 should add:

- cloud sync
- optional accounts
- remote execution
- credential management
- execution logs and durable run history

Phase 4 should add:

- subflows
- template marketplace
- collaboration
- lightweight branching and version comparisons
- custom node SDK

The rule for expansion remains the same: platform features are justified only when they directly strengthen the core workflow design experience.

---

## 2. Core Product Principles

### 2.1 Builder-First, Not Platform-First

The product is a workflow builder before it is an execution platform. That means:

- UX quality beats infrastructure breadth
- clarity beats flexibility
- concrete node contracts beat generic JSON pipes
- local determinism beats remote complexity

### 2.2 Inspectability Over Magic

Every value that matters should be inspectable. The user should not wonder:

- what a node output looks like
- why a connection is failing
- whether a node ran
- what data a downstream node is seeing

If something changes, the UI should show it.

### 2.3 Strong Contracts Over Loose Wiring

Edges should not merely connect boxes. They should connect typed ports with explicit compatibility semantics. The graph is valid or invalid for specific reasons that the user can see.

### 2.4 Mockability Before Real Execution

A node is not ready for the registry until it can:

- define a schema
- define a config form
- generate preview output
- expose fixtures
- optionally mock-execute deterministically

This makes nodes testable and demoable before any real provider exists.

### 2.5 Local-First Reliability

The app should behave like a serious local tool:

- fast startup
- durable autosave
- resilient imports
- recovery after interruption
- no required internet connectivity for core usage

### 2.6 Intentional Constraint

The product should choose constraints that preserve clarity:

- DAG only
- small to medium graphs
- no arbitrary scripting
- no dynamic runtime schema mutation
- no hidden implicit conversions

### 2.7 Accessibility And Keyboard Respect

Even though this is a canvas-heavy app, the UI should not assume mouse-only interaction. Keyboard and focus behavior should be specified, not left to chance.

---

## 3. Primary User Personas

### 3.1 Solo Developer Prototyping AI Video Pipelines

This user is designing a workflow they may later implement elsewhere. They care about:

- pipeline clarity
- data contracts
- step order
- configuration shape
- exportability

They do not need hosted execution yet.

### 3.2 AI Product Engineer Validating Pipeline Structure

This user is trying to answer:

- are we missing an intermediate node?
- are these outputs compatible?
- do we need an adapter node?
- what should our future backend execute?

This user values the Data Inspector and mock execution heavily.

### 3.3 Technical Creative / Prompt Systems Designer

This user iterates on prompts, scene decomposition, subtitles, and composition structure. They care about seeing how upstream configuration changes affect downstream shapes, even if no real media is generated.

### 3.4 Team Using Workflows As Specs

Even in single-user local-first mode, exported workflow JSON can function as a design artifact in a repo. This user cares about:

- stable schemas
- import/export integrity
- readability of node types
- workflow metadata
- deterministic mock previews for demos

---

## 4. Detailed User Journeys

### 4.1 Journey 1: Create A Short-Form Video Workflow From Scratch

A user opens the app. The left sidebar shows categories:

- Input
- Script
- Visuals
- Audio
- Video
- Utility
- Output

A search field sits above the library. Typing "scene" filters the library to `Scene Splitter` and any templates mentioning scenes.

The center canvas shows an empty-state illustration with three suggested templates and a "Quick Add" affordance. The right panel is collapsed until something is selected.

The user drags in:

- `User Prompt`
- `Script Writer`
- `Scene Splitter`
- `Image Generator`
- `Video Composer`
- `Final Export`

Each node renders with:

- title
- icon
- status badge
- port handles
- small metadata row

The user connects:

- `User Prompt.prompt` -> `Script Writer.prompt`
- `Script Writer.script` -> `Scene Splitter.script`
- `Scene Splitter.scenes` -> `Image Generator.scenes`
- `Image Generator.imageAssets` -> `Video Composer.visualAssets`
- `Video Composer.videoAsset` -> `Final Export.videoAsset`

When the user selects `Scene Splitter`, the right panel opens to the Inspector tab. It shows:

- description
- config form
- input ports
- output ports
- node notes
- fixture selector for preview context

The user changes `sceneCountTarget` from `5` to `8`. The Preview tab updates instantly to show a larger scene array.

The user then switches to the Data Inspector tab and sees:

- the last computed input payload from `Script Writer`
- the current output payload for `Scene Splitter`
- declared schemas
- payload lineage
- type metadata

They click "Run Workflow (Mock)." A toolbar appears at the top of the canvas showing overall progress. Nodes animate through `pending`, `running`, `success`. The `Image Generator` mock-executes and produces inspectable asset placeholders with synthetic URLs and captions.

The user now has both a visually clear graph and a believable simulated run.

### 4.2 Journey 2: Diagnose A Broken Connection

A user loads a saved workflow. One edge is highlighted red with a warning icon.

Selecting the edge opens the Data Inspector in edge mode. The panel shows:

- source node and port
- target node and port
- source schema
- target schema
- compatibility result
- why the edge is invalid
- suggested fix options

Example:

- Source: `Image Generator.imageFrames`
- Target: `Video Composer.visualAssets`
- Result: incompatible
- Reason: `imageFrameList` is not assignable to `imageAssetList`
- Suggested fixes:
  - insert `Image Asset Mapper`
  - switch `Image Generator.outputMode` to `assets`

The user clicks the quick action to insert `Image Asset Mapper`. The app places it between the two nodes and reconnects the edges automatically if the insertion is unambiguous.

Auto-reconnection rules:

1. do not remove the original edge until a valid replacement path is confirmed
2. choose the inserted-node input port most compatible with the original source port
3. choose the inserted-node output port most compatible with the original target port
4. reconnect source -> inserted node -> target as one atomic command

If there is no unique best port pair, open a small chooser so the user can confirm before the original edge is removed.

The validation error clears immediately.

### 4.3 Journey 3: Rerun From A Failed Step

A user mock-runs a workflow. `Subtitle Formatter` fails because the config requires a `maxCharsPerLine` between `12` and `42`, but the imported workflow contains `60`.

The failed node shows an error badge. The Run panel displays:

- run status: error
- failed node: `Subtitle Formatter`
- last successful upstream nodes
- elapsed time
- rerun actions

The Data Inspector shows:

- config validation failure
- old payload snapshot from upstream
- expected config constraints
- the last successful output from the previous run if available

The user changes `maxCharsPerLine` to `32` and clicks `Run From Here`. The executor computes the downstream dependency slice and reruns:

- `Subtitle Formatter`
- `Video Composer`
- `Final Export`

Upstream outputs are reused from the prior run cache for that workflow version and config hash when valid to do so.

### 4.4 Journey 4: Inspect Data On An Edge

A user wants to know exactly what is flowing from `Script Writer` to `Scene Splitter`.

They click the edge itself. The right panel switches to edge inspection mode. It shows:

- source payload
- source schema
- transport wrapper metadata
- preview rendering
- line-by-line JSON viewer
- copy payload
- compare against target schema

The user sees:

- `title`
- `hook`
- `beats`
- `cta`
- `estimatedDurationSeconds`

The target schema requires:

- `narrative`
- `beats`
- `durationSeconds`

The compatibility system explains that the connection is allowed because the node adapter extracts and normalizes `beats` and `estimatedDurationSeconds` according to the node template's compatibility rule. If no such rule existed, the edge would be invalid.

### 4.5 Journey 5: Start From A Template And Fork It

The user starts from `Narrated Product Teaser`. The template includes:

- `User Prompt`
- `Script Writer`
- `Scene Splitter`
- `Prompt Refiner`
- `Image Generator`
- `TTS Voiceover Planner`
- `Subtitle Formatter`
- `Video Composer`
- `Final Export`

The canvas loads centered and zoomed to fit.

Each node includes example preview data and valid default config. The user removes `TTS Voiceover Planner` because they want a silent visual teaser, then reruns mock execution.

The app preserves metadata showing the workflow was created from a template but forked locally.

### 4.6 Journey 6: Recover After A Refresh

A user is editing a workflow and running a mock execution. The tab refreshes unexpectedly.

When they reopen the app, they are presented with:

- recovered draft available
- last autosave timestamp
- interrupted run detected
- restore options:
  - restore draft and mark run interrupted
  - discard recovered draft
  - open last committed workflow snapshot

This turns local-first from a slogan into a reliable editing model.

---

## 5. V1 Node Catalog

The revised v1 should define a concrete, limited, high-quality node catalog. Every node needs:

- stable type id
- config schema
- input/output schemas
- preview builder
- fixtures
- icon
- category
- optional mock execution

### 5.1 `userPrompt`

Purpose:
Collects or seeds the initial creative intent.

Category:
`input`

Inputs:
None

Outputs:
- `prompt: prompt`

Config:
- `topic: string`
- `goal: string`
- `audience: string`
- `tone: 'educational' | 'cinematic' | 'playful' | 'dramatic'`
- `durationSeconds: number`

Behavior:
Generates a structured prompt payload from simple form inputs.

Mock execution:
Not necessary as a separate async step; preview and run output can be identical.

### 5.2 `scriptWriter`

Purpose:
Transforms prompt intent into a script object.

Category:
`script`

Inputs:
- `prompt: prompt`

Outputs:
- `script: script`

Config:
- `style`
- `structure`
- `includeHook`
- `includeCTA`
- `targetDurationSeconds`

Behavior:
Produces a structured script payload with title, hook, beats, narration, and CTA.

Mock execution:
Deterministic output based on prompt and config hash.

### 5.3 `sceneSplitter`

Purpose:
Turns a script into a list of scenes.

Category:
`script`

Inputs:
- `script: script`

Outputs:
- `scenes: sceneList`

Config:
- `sceneCountTarget`
- `maxSceneDurationSeconds`
- `includeShotIntent`
- `includeVisualPromptHints`

Behavior:
Creates structured scenes with sequence index, summary, timing, shot intent, and prompt hints.

Mock execution:
Produces realistic scene arrays.

### 5.4 `promptRefiner`

Purpose:
Converts scenes into image-generation prompts or enhanced prompts.

Category:
`visuals`

Inputs:
- `scenes: sceneList`

Outputs:
- `prompts: promptList`

Config:
- `visualStyle`
- `cameraLanguage`
- `aspectRatio`
- `consistencyNotes`
- `negativePromptEnabled`

Behavior:
Produces one refined prompt per scene.

Mock execution:
Deterministic prompt expansion.

### 5.5 `imageGenerator`

Purpose:
Represents image generation per scene.

Category:
`visuals`

Inputs:
- `scenes: sceneList`
- `prompts: promptList`

Outputs:
- `imageFrames: imageFrameList`
- `imageAssets: imageAssetList`

Config:
- `inputMode: 'scenes' | 'prompts'`
- `outputMode: 'frames' | 'assets'`
- `stylePreset`
- `resolution`
- `seedStrategy`

Behavior:
In v1, does not call a provider. Produces placeholder image artifacts with metadata.

Port rendering rule:

- keep both input ports and both output ports structurally present
- config marks ports active vs inactive
- inactive ports render disabled and reject new connections
- existing connections to newly inactive ports become validation errors until resolved

Mock execution:
Required.

### 5.6 `imageAssetMapper`

Purpose:
Adapts raw frames into normalized asset descriptors.

Category:
`utility`

Inputs:
- `imageFrames: imageFrameList`

Outputs:
- `imageAssets: imageAssetList`

Config:
- `assetRole`
- `namingPattern`

Behavior:
Normalizes frame outputs to the composition contract.

Mock execution:
Mostly deterministic transform; can share logic with preview.

### 5.7 `ttsVoiceoverPlanner`

Purpose:
Plans voiceover segments and metadata without synthesizing actual audio.

Category:
`audio`

Inputs:
- `script: script`

Outputs:
- `audioPlan: audioPlan`

Config:
- `voiceStyle`
- `pace`
- `genderStyle`
- `includePauses`

Behavior:
Creates audio timing plan, transcript chunks, and placeholder URL.

Mock execution:
Required.

### 5.8 `subtitleFormatter`

Purpose:
Formats subtitles from script or audio plan.

Category:
`video`

Inputs:
- `script: script`
- optional `audioPlan: audioPlan`

Outputs:
- `subtitleAsset: subtitleAsset`

Config:
- `maxCharsPerLine`
- `linesPerCard`
- `stylePreset`
- `burnMode: 'soft' | 'burnedPreview'`

Behavior:
Produces subtitle segments and style metadata.

Mock execution:
Required.

### 5.9 `videoComposer`

Purpose:
Combines visual assets, optional subtitles, and optional audio plan into a composed video artifact.

Category:
`video`

Inputs:
- `visualAssets: imageAssetList`
- optional `audioPlan: audioPlan`
- optional `subtitleAsset: subtitleAsset`

Outputs:
- `videoAsset: videoAsset`

Config:
- `aspectRatio`
- `transitionStyle`
- `fps`
- `includeTitleCard`
- `musicBed: 'none' | 'placeholder'`

Behavior:
Produces:

- a mock composed asset descriptor
- a timeline summary
- a poster frame URL
- an animated storyboard preview recipe
- preview metadata for subtitles, transitions, and audio timing alignment

Mock execution:
Required.

Preview semantics in v1:

- v1 does not render a true encoded MP4
- the primary preview is an animated storyboard player
- the player simulates cuts, fades, title cards, subtitle overlays, and audio timing

### 5.10 `reviewCheckpoint`

Purpose:
Introduces a human-in-the-loop checkpoint where the user can annotate or approve data before downstream steps.

Category:
`utility`

Inputs:
- exactly one input from this supported union in v1:
  - `script`
  - `sceneList`
  - `imageAssetList`
  - `subtitleAsset`
  - `videoAsset`

Outputs:
- `approvedScript: script`
- `approvedSceneList: sceneList`
- `approvedImageAssetList: imageAssetList`
- `approvedSubtitleAsset: subtitleAsset`
- `approvedVideoAsset: videoAsset`
- `reviewDecision`

Exactly one input/output pair is active per checkpoint config; inactive pairs remain disconnected and disabled.

Config:
- `reviewLabel`
- `instructions`
- `blocking: boolean`

Behavior:
Does not transform data automatically; it wraps and re-emits after user confirmation in mock mode.

V1 review modes:

- `autoApprove`
- `manualApprove`
- `manualReject`

`approvedPayload` preserves the input schema type exactly.

Mock execution:
Can default to auto-approve in full workflow mock mode, but should support manual review mode.

### 5.11 `finalExport`

Purpose:
Packages the output into an export descriptor.

Category:
`output`

Inputs:
- `videoAsset: videoAsset`

Outputs:
- `exportBundle: json`

Config:
- `fileNamePattern`
- `includeMetadata`
- `includeWorkflowSpecReference`

Behavior:
Produces exportable JSON descriptor and synthetic file info.

Mock execution:
Required.

### 5.12 Node Catalog Rules

A node is eligible for v1 only if it satisfies all of the following:

- solves a clear AI video workflow task
- has a stable contract
- can be previewed deterministically
- can be mocked deterministically if executable
- does not require real provider infrastructure
- improves clarity rather than increasing platform surface area

---

## 6. Information Architecture And UX

### 6.1 Primary Layout

Use a three-region app shell:

- Left: `NodeLibraryPanel`
- Center: `CanvasSurface` with `RunToolbar`
- Right: `InspectorPanel` with tabs for:
  - Config
  - Data
  - Validation
  - Metadata

If persistence mode is `memory-fallback` or `unavailable`, show a persistent warning banner with:
- current mode
- durability warning
- `Export Workflow JSON`
- `Retry Persistence`

Top bar:

- workflow name
- save state
- undo/redo
- import/export
- template actions
- settings

Bottom status row optional:

- selected item
- validation summary
- autosave status
- run status

### 6.2 Left Panel: Node Library

The node library should include:

- search input
- category filters
- recently used nodes
- template quick starts
- drag handles
- compact/expanded mode

Each node library item shows:

- icon
- title
- short description
- supported input/output summary

Interaction details:

- drag to canvas creates node
- click opens a detail popover
- keyboard quick-add opens a searchable command menu
- hovering a node while an edge is selected highlights compatible drop targets

### 6.3 Center Panel: Canvas

The canvas should support:

- pan
- zoom
- fit view
- grid background
- snap-to-grid optional
- selection box
- multi-select
- edge creation
- node duplication
- delete
- alignment helpers
- quick insertion on edge

Quick insertion should be implemented as a workflow-store command, not ad hoc canvas mutation.

#### 6.3.1 Edge Insertion Command

```ts
export interface InsertNodeOnEdgeCommand {
  readonly kind: 'insertNodeOnEdge';
  readonly edgeId: string;
  readonly newNodeType: string;
  readonly preferredInputPortKey?: string;
  readonly preferredOutputPortKey?: string;
}
```

Execution semantics:

1. create the new node at the geometric midpoint of the original edge
2. resolve compatible input/output ports
3. remove the original edge
4. add replacement edges
5. select the inserted node
6. open the inspector if config is required

Undo semantics:

- one undo removes the inserted node and replacement edges
- the original edge is restored in the same history step

#### 6.3.2 Port Matching Algorithm

Score candidate ports in this order:

- exact semantic type match
- compatibility via explicit coercion rule
- compatibility via node-specific adapter rule

Tie-breakers:

- prefer required over optional ports
- prefer semantically matching labels
- prefer the only non-generic port if exactly one exists

Example scoring:

- exact match: 100
- explicit coercion: 60
- adapter-specific compatibility: 40
- incompatible: reject

If exactly one input/output pair yields the best score, insert automatically.
If multiple pairs tie, require user confirmation.
If no pair is valid, preserve the original edge and optionally open the node disconnected.

Node visuals should include:

- title
- category color or icon accent
- validation badge
- run status badge
- disabled state
- compact port labels
- execution affordances where applicable

Edge visuals should include:

- default
- selected
- invalid
- warning
- carrying data
- last-run success/error indicators

### 6.4 Right Panel: Inspector

The right panel is not a generic sidebar; it is a high-value diagnostic workspace.

Modes:

- node selected
- edge selected
- workflow selected
- nothing selected

Tabs for node mode:

- `Config`
- `Preview`
- `Data`
- `Validation`
- `Metadata`

Config tab includes:

- node title
- description
- typed form
- reset to defaults
- fixture selector
- last run summary
- run actions

Data tab includes:

- latest input payloads
- latest output payloads
- preview payloads
- schema comparison
- lineage trace
- JSON/raw view
- human-readable summary view

Validation tab includes:

- config schema issues
- missing inputs
- type mismatch issues
- warnings
- suggested remediations

Metadata tab includes:

- node id
- type
- createdAt
- updatedAt
- notes
- pinned comment

### 6.5 Data Inspector As First-Class Feature

The Data Inspector is the biggest differentiator and should be treated as a dedicated product pillar, not an afterthought.

It must support inspecting:

- node inputs
- node outputs
- selected edge payload via `EdgePayloadSnapshot`
- workflow-level run summary
- current preview vs last run output
- schema mismatch diagnostics
- payload history for recent runs
- raw JSON and readable summary modes

For every payload shown, display:

- payload status
- schema type
- producer node
- produced timestamp
- preview text or preview URL if available
- data size estimate
- validation state

If a payload is too large, truncate intelligently and provide:

- expand
- copy
- download JSON

Large-payload policy:
- inline render up to 256 KB
- collapse and virtualize between 256 KB and 2 MB
- require download above 2 MB

### 6.6 Run Toolbar

The run toolbar sits above the canvas and exposes:

- `Run Workflow`
- `Run Selected Node`
- `Run From Here`
- `Run Up To Here`
- `Cancel`
- last run status
- elapsed time
- mock mode indicator

If nothing valid is selected for node-specific actions, disable those actions with explanatory tooltips.

### 6.7 Empty States

Empty-state UX matters because templates are a major adoption strategy.

The initial state should offer:

- "Start from template"
- "Add first node"
- suggested workflows
- keyboard shortcut hints

Invalid or missing data states should avoid generic "No data." Instead use:

- "This node has not run yet."
- "This output is unavailable because upstream validation failed."
- "Select an edge to inspect its payload."
- "Run this node in mock mode to generate payloads."

### 6.8 Accessibility

The app should provide:

- keyboard navigation between panels
- keyboard selection of nodes and edges
- focus-visible states
- labeled buttons and handles
- screen-reader labels for run status and validation counts
- reduced motion mode
- sufficient contrast in both light and dark themes

Canvas-heavy apps are difficult to make perfectly screen-reader friendly, but the inspector and library should remain highly accessible.

Minimum keyboard graph-editing support in v1:

- tab or arrow navigation between nodes
- enter to inspect selected node
- keyboard command to start "connect from selected output"
- searchable dialog listing valid target ports
- keyboard command to disconnect selected edge

### 6.9 Keyboard Shortcuts

Recommended defaults:

- `Cmd/Ctrl + S`: save committed local snapshot
- `Cmd/Ctrl + Shift + E`: export workflow JSON
- `Cmd/Ctrl + Z`: undo
- `Cmd/Ctrl + Shift + Z`: redo
- `Backspace/Delete`: delete selection
- `Space`: pan mode while held
- `A`: quick add node
- `Enter`: inspect selected item
- `C`: connect from selected node or port
- `R`: run selected node
- `Shift + R`: run workflow
- `Escape`: clear selection / close menus

---

## 7. System Architecture

### 7.1 Architecture Recommendation

Use a frontend-only architecture with deterministic mock execution.

Core stack:

- React
- Vite
- TypeScript with strict mode
- `@xyflow/react`
- Tailwind CSS
- shadcn/ui primitives
- Zustand
- Dexie
- Zod
- React Hook Form
- Vitest
- React Testing Library
- Playwright

This stack is optimized for:

- fast local iteration
- strong type safety
- canvas interactions
- offline-capable persistence
- schema-driven forms
- maintainable UI composition

#### 7.1.1 App Entry Responsibilities

- `app.tsx`: mounts the app and top-level providers
- `providers.tsx`: composes theme, persistence, boot, and `ReactFlowProvider`
- `routes.tsx`: defines the route tree and modal overlays

### 7.2 Architectural Position

V1 architecture should intentionally separate four concerns:

1. workflow document modeling
2. graph validation and preview derivation
3. run planning and mock execution
4. persistence and recovery

This separation is crucial. It prevents the codebase from turning into a single mutable graph blob with mixed UI and runtime state.

### 7.3 High-Level Component Graph

```mermaid
flowchart TD
    AppShell --> NodeLibraryPanel
    AppShell --> CanvasSurface
    AppShell --> InspectorPanel
    AppShell --> RunToolbar

    CanvasSurface --> WorkflowStore
    InspectorPanel --> WorkflowStore
    InspectorPanel --> RunStore
    RunToolbar --> RunStore

    WorkflowStore --> GraphValidator
    WorkflowStore --> PreviewEngine
    WorkflowStore --> PersistenceGateway

    RunStore --> RunPlanner
    RunStore --> MockExecutor
    RunStore --> RunCache

    GraphValidator --> NodeRegistry
    GraphValidator --> TypeCompatibilityRegistry
    PreviewEngine --> NodeRegistry
    MockExecutor --> NodeRegistry
    MockExecutor --> RunCache
    PersistenceGateway --> DexieDB
```

#### 7.3.1 Route Structure

V1 should use a single primary route:

- `/` -> editor shell

Optional dialog/search-param states:

- `/?dialog=template-gallery`
- `/?dialog=import`
- `/?dialog=recovery`
- `/?dialog=settings`

This keeps v1 simple without blocking later expansion.

#### 7.3.2 Provider Composition

```tsx
export function AppProviders({ children }: { children: React.ReactNode }) {
  return (
    <React.StrictMode>
      <ThemeProvider>
        <PersistenceProvider>
          <BootProvider>
            <ReactFlowProvider>{children}</ReactFlowProvider>
          </BootProvider>
        </PersistenceProvider>
      </ThemeProvider>
    </React.StrictMode>
  );
}
```

Notes:

- Zustand stores remain module-scoped and do not require a custom provider
- `PersistenceProvider` exposes repository mode
- `BootProvider` is responsible for store hydration and recovery decisions

#### 7.3.3 Boot State Machine

```ts
type BootState =
  | { status: 'checkingPersistence' }
  | { status: 'checkingRecovery'; repository: WorkflowRepository }
  | { status: 'ready'; repository: WorkflowRepository; initialWorkflowId?: string }
  | { status: 'degraded'; repository: WorkflowRepository; reason: string }
  | { status: 'fatal'; message: string };
```

Boot order:

1. mount providers and render a lightweight splash screen
2. open the persistence repository
3. detect degraded mode if IndexedDB is unavailable
4. check for a recovery snapshot
5. if recovery exists, show recovery dialog before loading a document
6. otherwise load `lastOpenedWorkflowId`
7. if a last-opened workflow exists, hydrate it into `workflowStore`
8. otherwise show empty state with templates
9. only then mount the editable `AppShell`

#### 7.3.4 Initial Render Contract

`AppShell` should not render the editable canvas until boot has decided one of:

- restored recovery snapshot
- loaded last workflow
- shown empty state

This avoids flashing an empty canvas and then replacing it.

`AppShell` must render the degraded-mode banner before the user edits the document.

Recommended file responsibilities:

```tsx
// app.tsx
export function App() {
  return (
    <AppProviders>
      <AppRoutes />
    </AppProviders>
  );
}
```

```tsx
// routes.tsx
const router = createBrowserRouter([
  {
    path: '/',
    element: <BootGate />,
  },
]);

function BootGate() {
  const boot = useBootState();

  if (boot.status === 'checkingPersistence' || boot.status === 'checkingRecovery') {
    return <AppSplashScreen />;
  }

  if (boot.status === 'fatal') {
    return <FatalBootErrorScreen message={boot.message} />;
  }

  return <AppShell />;
}
```

### 7.4 Major Runtime Modules

#### `NodeRegistry`

Responsible for:

- registering node templates
- exposing node metadata
- exposing config schemas
- exposing port definitions
- exposing preview builders
- exposing mock execution handlers
- exposing fixtures

#### `GraphValidator`

Responsible for:

- cycle detection
- missing input detection
- connection validation
- config validation integration
- downstream invalidation reporting
- workflow-level validation summary

#### `TypeCompatibilityRegistry`

Responsible for:

- source-target compatibility lookup
- coercion rules
- warning vs error classification
- adapter recommendations

#### `PreviewEngine`

Responsible for:

- deterministic local preview derivation
- incremental recomputation
- fixture-aware previews
- preview invalidation on config or topology changes

#### `RunPlanner`

Responsible for:

- topological ordering
- selection-based subgraph extraction
- run-from-here planning
- run-up-to-here planning
- dependency pruning
- cache reuse eligibility

#### `MockExecutor`

Responsible for:

- invoking `mockExecute`
- managing per-node status
- wrapping outputs as `PortPayload`
- capturing timing
- recording failures
- cancellation support
- writing run records

#### `RunCache`

Responsible for:

- storing recent mock outputs
- invalidating on config/topology changes
- reusing upstream payloads during partial rerun
- bounding storage size

#### `PersistenceGateway`

Responsible for:

- load
- save
- autosave
- import
- export
- migrations
- recovery snapshots
- recent workflows listing

### 7.5 State Separation

#### `workflowStore`

Contains:

- workflow document
- selected node ids
- selected edge id
- viewport
- library UI state
- inspector tab state
- undo/redo history
- dirty state
- validation summary
- derived preview caches if kept in-memory

This store should persist document-related state only.

#### `runStore`

Contains:

- active run
- recent runs
- node run records
- payload snapshots
- cache metadata
- cancellation controllers
- run toolbar state
- last execution scope

This store should never be part of undo/redo history.

### 7.6 Why Two Stores Matter

If run state is mixed into document state:

- undo becomes polluted with runtime changes
- save/export can accidentally include transient runtime noise
- crash recovery becomes more complex
- document diffs become less meaningful

Separation creates a cleaner mental and code architecture.

---

## 8. Data Model

### 8.1 Core Design Principles

The data model should prioritize:

- versionability
- inspectability
- strict typing
- runtime metadata without polluting authoring structures
- clean separation between document and run artifacts

### 8.2 Semantic Data Types

Use semantic types rather than generic `json` whenever possible.

```ts
export type DataType =
  | 'text'
  | 'textList'
  | 'prompt'
  | 'promptList'
  | 'script'
  | 'scene'
  | 'sceneList'
  | 'imageFrame'
  | 'imageFrameList'
  | 'imageAsset'
  | 'imageAssetList'
  | 'audioPlan'
  | 'audioAsset'
  | 'subtitleAsset'
  | 'videoAsset'
  | 'reviewDecision'
  | 'json';
```

These types are still intentionally limited. They are expressive enough for v1 without pretending to solve every future media type.

### 8.3 Port Definition

```ts
export interface PortDefinition {
  readonly key: string;
  readonly label: string;
  readonly direction: 'input' | 'output';
  readonly dataType: DataType;
  readonly required: boolean;
  readonly multiple: boolean;
  readonly description?: string;
}
```

### 8.4 Workflow Document Entities

```ts
export interface WorkflowNode<TConfig = unknown> {
  readonly id: string;
  readonly type: string;
  readonly label: string;
  readonly position: {
    readonly x: number;
    readonly y: number;
  };
  readonly config: Readonly<TConfig>;
  readonly disabled?: boolean;
  readonly notes?: string;
}

export interface WorkflowEdge {
  readonly id: string;
  readonly sourceNodeId: string;
  readonly sourcePortKey: string;
  readonly targetNodeId: string;
  readonly targetPortKey: string;
  readonly targetOrder?: number;
}

export interface WorkflowDocument {
  readonly id: string;
  readonly schemaVersion: number;
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
  readonly basedOnTemplateId?: string;
  readonly basedOnTemplateVersion?: string;
}
```

### 8.5 Port Payload Wrapper

This is one of the most important revisions.

```ts
export interface PortPayload<TValue = unknown> {
  readonly value: TValue | null;
  readonly status: 'idle' | 'ready' | 'running' | 'success' | 'error' | 'skipped' | 'cancelled';
  readonly schemaType: DataType;
  readonly producedAt?: string;
  readonly sourceNodeId?: string;
  readonly sourcePortKey?: string;
  readonly previewText?: string;
  readonly previewUrl?: string;
  readonly sizeBytesEstimate?: number;
  readonly errorMessage?: string;
}
```

`ready` means "preview-derived and available before mock execution".
`success` means "produced by mock execution or reused from run cache".

Why this wrapper matters:

- it gives the inspector richer semantics than plain JSON
- it supports both preview and run outputs
- it carries enough metadata for debugging
- it creates a clean path for future real execution

### 8.6 Validation Model

```ts
export type ValidationSeverity = 'error' | 'warning' | 'info';

export interface ValidationIssue {
  readonly id: string;
  readonly severity: ValidationSeverity;
  readonly scope: 'workflow' | 'node' | 'edge' | 'port' | 'config';
  readonly message: string;
  readonly nodeId?: string;
  readonly edgeId?: string;
  readonly portKey?: string;
  readonly code:
    | 'cycleDetected'
    | 'missingRequiredInput'
    | 'incompatiblePortTypes'
    | 'configInvalid'
    | 'orphanNode'
    | 'disabledNode'
    | 'coercionApplied'
    | 'downstreamInvalidated';
  readonly suggestion?: string;
}
```

### 8.7 Run Model

```ts
export interface ExecutionRun {
  readonly id: string;
  readonly workflowId: string;
  readonly mode: 'mock';
  readonly trigger: 'runWorkflow' | 'runNode' | 'runFromHere' | 'runUpToHere';
  readonly targetNodeId?: string;
  readonly plannedNodeIds: readonly string[];
  readonly status: 'pending' | 'running' | 'awaitingReview' | 'success' | 'error' | 'cancelled' | 'interrupted';
  readonly startedAt: string;
  readonly completedAt?: string;
  readonly terminationReason?: 'completed' | 'nodeError' | 'userCancelled' | 'tabClosed' | 'recoveredAfterCrash';
  readonly documentHash: string;
  readonly nodeConfigHashes: Readonly<Record<string, string>>;
}

export interface NodeRunRecord {
  readonly runId: string;
  readonly nodeId: string;
  readonly status: 'pending' | 'running' | 'awaitingReview' | 'success' | 'error' | 'skipped' | 'cancelled';
  readonly skipReason?: 'disabled' | 'missingRequiredInputs' | 'upstreamFailed';
  readonly blockedByNodeIds?: readonly string[];
  readonly startedAt?: string;
  readonly completedAt?: string;
  readonly durationMs?: number;
  readonly inputPayloads: Readonly<Record<string, PortPayload>>;
  readonly outputPayloads: Readonly<Record<string, PortPayload>>;
  readonly errorMessage?: string;
  readonly usedCache: boolean;
}

export interface EdgePayloadSnapshot {
  readonly edgeId: string;
  readonly sourcePayload: PortPayload;
  readonly transportedPayload: PortPayload;
  readonly coercionApplied?: string;
}
```

All runs execute against an immutable workflow snapshot captured at run start.

### 8.8 Compatibility Model

```ts
export interface CompatibilityResult {
  readonly compatible: boolean;
  readonly coercionApplied: boolean;
  readonly severity: 'none' | 'warning' | 'error';
  readonly reason?: string;
  readonly suggestedAdapterNodeType?: string;
}
```

### 8.9 Node Template Contract

```ts
import { z } from 'zod';

export interface NodeFixture<TConfig> {
  readonly id: string;
  readonly label: string;
  readonly config?: Partial<TConfig>;
  readonly previewInputs?: Readonly<Record<string, PortPayload>>;
  readonly executionInputs?: Readonly<Record<string, PortPayload>>;
}

export interface MockNodeExecutionArgs<TConfig> {
  readonly nodeId: string;
  readonly config: Readonly<TConfig>;
  readonly inputs: Readonly<Record<string, PortPayload>>;
  readonly signal: AbortSignal;
  readonly runId: string;
}

interface NodeTemplateBase<TConfig> {
  readonly type: string;
  readonly templateVersion: string;
  readonly title: string;
  readonly category:
    | 'input'
    | 'script'
    | 'visuals'
    | 'audio'
    | 'video'
    | 'utility'
    | 'output';
  readonly description: string;
  readonly inputs: readonly PortDefinition[];
  readonly outputs: readonly PortDefinition[];
  readonly defaultConfig: Readonly<TConfig>;
  readonly configSchema: z.ZodType<TConfig>;
  readonly fixtures: readonly NodeFixture<TConfig>[];
  readonly buildPreview: (args: {
    readonly config: Readonly<TConfig>;
    readonly inputs: Readonly<Record<string, PortPayload>>;
  }) => Readonly<Record<string, PortPayload>>;
}

export type NodeTemplate<TConfig> =
  | (NodeTemplateBase<TConfig> & {
      readonly executable: false;
      readonly mockExecute?: never;
    })
  | (NodeTemplateBase<TConfig> & {
      readonly executable: true;
      readonly mockExecute: (
        args: MockNodeExecutionArgs<TConfig>,
      ) => Promise<Readonly<Record<string, PortPayload>>>;
    });
```

### 8.10 Crash Recovery Snapshot

```ts
export interface WorkflowSnapshot {
  readonly id: string;
  readonly workflowId: string;
  readonly kind: 'autosave' | 'recovery';
  readonly savedAt: string;
  readonly document: WorkflowDocument;
  readonly interruptedRunId?: string;
  readonly activeRunSummary?: {
    readonly runId: string;
    readonly trigger: ExecutionRun['trigger'];
    readonly status: ExecutionRun['status'];
    readonly currentNodeId?: string;
    readonly plannedNodeIds: readonly string[];
  };
}
```

---

## 9. Type Compatibility Rules

This area was too vague in Plan A. It must be explicit.

### 9.1 Compatibility Philosophy

Rules should be:

- predictable
- sparse
- documented
- inspectable
- conservative

The app should not silently coerce complex structures in surprising ways.

### 9.2 Compatibility Classes

#### Exact compatibility

Source and target types are identical.

Examples:

- `script -> script`
- `sceneList -> sceneList`
- `videoAsset -> videoAsset`

#### Safe scalar-to-list wrapping

Allowed with warning or info badge.

Examples:

- `text -> textList`
- `prompt -> promptList`

#### Schema-backed structural compatibility

Allowed only if the target node definition explicitly supports it.

Example:

- `sceneList` accepted from `script` only through a node template adapter rule, not globally

#### Incompatible without adapter

Examples:

- `imageFrameList -> imageAssetList`
- `script -> subtitleAsset`
- `promptList -> videoAsset`

### 9.3 Compatibility Matrix Examples

- `prompt -> prompt`: yes
- `prompt -> promptList`: yes, auto-wrap single item
- `promptList -> prompt`: no
- `sceneList -> promptList`: no, use `promptRefiner`
- `imageFrameList -> imageAssetList`: no, use `imageAssetMapper`
- `script -> subtitleAsset`: no, use `subtitleFormatter`
- `imageAssetList -> videoAsset`: no direct edge unless the target node is `videoComposer`

### 9.4 UX For Coercions

If coercion is applied:

- show a badge on the edge
- show it in validation summary as info or warning
- show exact transformation in the Data Inspector

Never apply silent destructive coercion.

---

## 10. Preview Engine

### 10.1 Purpose

The preview engine provides instant, deterministic, synchronous or near-synchronous derived outputs as users edit the graph. It exists to support authoring, not to simulate runtime latency.

### 10.2 Responsibilities

- derive sample outputs from config and upstream previews
- recompute incrementally
- invalidate downstream previews when upstream changes
- stay deterministic
- support fixture selection

### 10.3 Preview vs Mock Execution

Preview is not the same as mock execution.

Preview:

- immediate
- cheap
- derived
- UI-focused
- may omit runtime metadata

Mock execution:

- run-oriented
- produces statuses and timings
- writes run records
- can be cancelled
- can reuse cache
- produces `PortPayload` wrappers

The UI should show both when relevant.

Inspector precedence rules:

- default to last successful run output when present
- fall back to preview output when no successful run exists
- always label the source as `preview` or `lastRun`
- allow side-by-side comparison when both exist

### 10.4 Incremental Recompute

When a node changes:

- recompute that node preview
- invalidate downstream preview caches
- recompute downstream previews in topological order
- stop at invalid nodes if required inputs are missing

Performance rule for v1:

- topology-change recompute should be immediate
- text-entry config recompute should debounce by about 150 ms
- expensive preview formatting should memoize by node input/config hash

### 10.5 Preview Determinism

Preview output must be stable for the same:

- node config
- upstream preview inputs
- selected fixture

This avoids confusing the user during editing.

---

## 11. Mock Execution Engine

### 11.1 Why Mock Execution Exists

Mock execution exists to give the user a believable, inspectable behavioral simulation of the pipeline without invoking real external systems.

It is not a half-hearted proto-backend. It is a product feature.

### 11.2 Execution Modes

V1 supports only one real mode:

- `mock`

Within mock mode, the user may trigger:

- run workflow
- run selected node
- run from here
- run up to here

All runs execute against an immutable workflow snapshot captured at run start.

### 11.3 Run Planning

The `RunPlanner` determines:
- `resolvedNodeIds`: nodes needed to hydrate inputs, including non-executable providers
- `executedNodeIds`: executable nodes that may actually run

#### `runWorkflow`

Plan all executable nodes in the current workflow document that are not excluded by validation or explicit disablement.

#### `runNode`

Run only the selected node if all required upstream inputs can be resolved from one of:

- a successful upstream node included in the current execution plan
- a reusable cache entry for an upstream executable node
- a wrapped preview payload from an upstream non-executable node

`runNode` does not silently substitute preview output for an upstream executable node that has never completed successfully for the current input/config shape.

#### `runFromHere`

Run the selected node and all downstream executable dependents.

#### `runUpToHere`

Run all upstream executable dependencies needed to produce the selected node's required inputs, and optionally the selected node.

For v1, `runUpToHere` includes the selected node by default.

#### 11.3.1 Execution Planning Types

```ts
export interface ExecutionPlan {
  readonly runId: string;
  readonly workflowId: string;
  readonly trigger: ExecutionRun['trigger'];
  readonly targetNodeId?: string;
  readonly scopeNodeIds: readonly string[];
  readonly orderedNodeIds: readonly string[];
  readonly skippedNodeIds: readonly string[];
}
```

#### 11.3.2 Scope Extraction

`RunPlanner` should derive the execution scope in two phases:

1. compute the candidate node set for the trigger
2. prune to executable nodes plus any non-executable upstream providers required to hydrate inputs

Use graph traversal helpers:

```ts
function collectUpstreamNodeIds(
  targetNodeId: string,
  incomingByNode: ReadonlyMap<string, readonly WorkflowEdge[]>,
): Set<string> {
  const visited = new Set<string>();
  const stack = [targetNodeId];

  while (stack.length > 0) {
    const current = stack.pop()!;
    for (const edge of incomingByNode.get(current) ?? []) {
      if (!visited.has(edge.sourceNodeId)) {
        visited.add(edge.sourceNodeId);
        stack.push(edge.sourceNodeId);
      }
    }
  }

  return visited;
}

function collectDownstreamNodeIds(
  sourceNodeId: string,
  outgoingByNode: ReadonlyMap<string, readonly WorkflowEdge[]>,
): Set<string> {
  const visited = new Set<string>();
  const stack = [sourceNodeId];

  while (stack.length > 0) {
    const current = stack.pop()!;
    for (const edge of outgoingByNode.get(current) ?? []) {
      if (!visited.has(edge.targetNodeId)) {
        visited.add(edge.targetNodeId);
        stack.push(edge.targetNodeId);
      }
    }
  }

  return visited;
}
```

Trigger rules:

- `runWorkflow`: all executable nodes in the document not excluded by validation or disablement
- `runNode`: selected node only
- `runFromHere`: selected node plus downstream dependents
- `runUpToHere`: upstream dependencies plus selected node

#### 11.3.3 Topological Ordering

Use Kahn's algorithm over the selected subgraph:

```ts
export function topologicallySortSubgraph(args: {
  readonly nodeIds: ReadonlySet<string>;
  readonly edges: readonly WorkflowEdge[];
}): string[] {
  const indegree = new Map<string, number>();
  const outgoing = new Map<string, string[]>();

  for (const nodeId of args.nodeIds) {
    indegree.set(nodeId, 0);
    outgoing.set(nodeId, []);
  }

  for (const edge of args.edges) {
    if (!args.nodeIds.has(edge.sourceNodeId) || !args.nodeIds.has(edge.targetNodeId)) {
      continue;
    }

    indegree.set(edge.targetNodeId, (indegree.get(edge.targetNodeId) ?? 0) + 1);
    outgoing.get(edge.sourceNodeId)!.push(edge.targetNodeId);
  }

  const queue = [...indegree.entries()]
    .filter(([, count]) => count === 0)
    .map(([nodeId]) => nodeId);

  const ordered: string[] = [];

  while (queue.length > 0) {
    const nodeId = queue.shift()!;
    ordered.push(nodeId);

    for (const nextNodeId of outgoing.get(nodeId) ?? []) {
      const nextCount = (indegree.get(nextNodeId) ?? 0) - 1;
      indegree.set(nextNodeId, nextCount);
      if (nextCount === 0) {
        queue.push(nextNodeId);
      }
    }
  }

  if (ordered.length !== args.nodeIds.size) {
    throw new Error('Cannot plan execution for cyclic subgraph');
  }

  return ordered;
}
```

#### 11.3.4 `RunPlanner.plan()` Contract

```ts
export class RunPlanner {
  plan(args: {
    readonly workflow: WorkflowDocument;
    readonly trigger: ExecutionRun['trigger'];
    readonly targetNodeId?: string;
    readonly registry: NodeRegistry;
  }): ExecutionPlan {
    const incomingByNode = indexIncomingEdges(args.workflow.edges);
    const outgoingByNode = indexOutgoingEdges(args.workflow.edges);

    const scopeNodeIds = this.resolveScopeNodeIds({
      trigger: args.trigger,
      targetNodeId: args.targetNodeId,
      workflow: args.workflow,
      incomingByNode,
      outgoingByNode,
    });

    const orderedNodeIds = topologicallySortSubgraph({
      nodeIds: scopeNodeIds,
      edges: args.workflow.edges,
    });

    return {
      runId: crypto.randomUUID(),
      workflowId: args.workflow.id,
      trigger: args.trigger,
      targetNodeId: args.targetNodeId,
      scopeNodeIds: [...scopeNodeIds],
      orderedNodeIds,
      skippedNodeIds: [],
    };
  }
}
```

### 11.4 Execution Ordering

Use topological order over the chosen subgraph.

If multiple independent branches exist, v1 may choose sequential execution first for simplicity, but the architecture should permit later safe parallelization. The run model should not assume parallel execution from day one.

#### 11.4.1 Main Execution Loop

```ts
export class MockExecutor {
  async execute(args: {
    readonly workflow: WorkflowDocument;
    readonly plan: ExecutionPlan;
    readonly registry: NodeRegistry;
    readonly runCache: RunCache;
    readonly signal: AbortSignal;
  }): Promise<ExecutionRun> {
    const runAbortController = new AbortController();
    const forwardAbort = () => runAbortController.abort(args.signal.reason);
    args.signal.addEventListener('abort', forwardAbort, { once: true });

    this.runStore.startRun({
      id: args.plan.runId,
      workflowId: args.workflow.id,
      trigger: args.plan.trigger,
      targetNodeId: args.plan.targetNodeId,
      plannedNodeIds: args.plan.orderedNodeIds,
    });

    try {
      for (const nodeId of args.plan.orderedNodeIds) {
        if (runAbortController.signal.aborted) {
          this.runStore.markPendingNodesCancelled(args.plan.runId);
          return this.runStore.completeRun(args.plan.runId, 'cancelled');
        }

        const node = getNodeOrThrow(args.workflow, nodeId);
        const template = args.registry.get(node.type);

        if (node.disabled) {
          this.runStore.writeSkippedNode(args.plan.runId, nodeId, 'disabled');
          continue;
        }

        const resolvedInputs = this.resolveNodeInputs({
          workflow: args.workflow,
          node,
          template,
          runId: args.plan.runId,
          runCache: args.runCache,
        });

        if (!resolvedInputs.ok) {
          this.runStore.writeSkippedNode(
            args.plan.runId,
            nodeId,
            resolvedInputs.reason,
            resolvedInputs.blockedByNodeIds,
          );
          continue;
        }

        if (!template.executable) {
          const outputPayloads = this.wrapPreviewAsOutputs({
            node,
            template,
            inputs: resolvedInputs.inputPayloads,
          });

          this.runStore.writeSucceededNode(args.plan.runId, nodeId, {
            inputPayloads: resolvedInputs.inputPayloads,
            outputPayloads,
            usedCache: false,
          });
          continue;
        }

        const cacheHit = args.runCache.getReusableEntry({
          workflowId: args.workflow.id,
          node,
          inputPayloads: resolvedInputs.inputPayloads,
        });

        if (cacheHit) {
          this.runStore.writeSucceededNode(args.plan.runId, nodeId, {
            inputPayloads: resolvedInputs.inputPayloads,
            outputPayloads: cacheHit.outputPayloads,
            usedCache: true,
          });
          continue;
        }

        this.runStore.markNodeRunning(args.plan.runId, nodeId, resolvedInputs.inputPayloads);

        const nodeAbortController = new AbortController();
        const abortNode = () => nodeAbortController.abort(runAbortController.signal.reason);
        runAbortController.signal.addEventListener('abort', abortNode, { once: true });

        const startedAt = performance.now();

        try {
          const outputPayloads = await template.mockExecute!({
            nodeId,
            config: node.config,
            inputs: resolvedInputs.inputPayloads,
            signal: nodeAbortController.signal,
            runId: args.plan.runId,
          });

          args.runCache.put({
            workflowId: args.workflow.id,
            node,
            inputPayloads: resolvedInputs.inputPayloads,
            outputPayloads,
          });

          this.runStore.writeSucceededNode(args.plan.runId, nodeId, {
            inputPayloads: resolvedInputs.inputPayloads,
            outputPayloads,
            usedCache: false,
            durationMs: performance.now() - startedAt,
          });
        } catch (error) {
          if (nodeAbortController.signal.aborted) {
            this.runStore.writeCancelledNode(args.plan.runId, nodeId);
            this.runStore.markPendingNodesCancelled(args.plan.runId);
            return this.runStore.completeRun(args.plan.runId, 'cancelled');
          }

          this.runStore.writeErroredNode(
            args.plan.runId,
            nodeId,
            error instanceof Error ? error.message : 'Unknown mock execution error',
          );
        } finally {
          runAbortController.signal.removeEventListener('abort', abortNode);
        }
      }

      return this.runStore.completeRunFromNodeStates(args.plan.runId);
    } finally {
      args.signal.removeEventListener('abort', forwardAbort);
    }
  }
}
```

#### 11.4.2 Input Resolution Rules

Resolve inputs in this order:

1. successful upstream output from the active run
2. reusable cache entry for an upstream executable node
3. wrapped preview output from an upstream non-executable node
4. unresolved

If any required input remains unresolved, mark the node `skipped`.

### 11.5 Node Status Lifecycle

A node run record should move through:

- `pending`
- `running`
- `awaitingReview`
- `success`
- `error`
- `skipped`
- `cancelled`

A node is `skipped` if:

- it is disabled
- an upstream required dependency failed
- cache reuse short-circuits active execution but still produces valid payloads

The `disabled` flag is read from the run-start snapshot.
Changing `disabled` during an active run affects only future runs.

### 11.6 Cancellation

Every active node execution should receive an `AbortSignal`. If the user clicks cancel:

- active signals abort
- current running node transitions to `cancelled`
- downstream pending nodes transition to `cancelled`
- run status becomes `cancelled`

Cancellation is necessary even in mock mode because it creates the right abstraction boundary for later real execution.

### 11.7 Cache Reuse

Mock execution may reuse previously computed outputs only if all of the following match:

- same node type
- same config hash
- same normalized input payload hash
- same workflow schema version
- same node template version

If cache is reused:

- mark `usedCache: true`
- display that in the inspector
- still expose outputs as if the node had completed successfully

Cache hits remain `success` with `usedCache: true`.

### 11.8 Mock Artifact Strategy

Mock-generated asset-like outputs should use synthetic but realistic descriptors, not large embedded binaries.

Examples:

- `previewUrl: blob:` or generated data URL thumbnails
- placeholder asset metadata
- duration, resolution, caption, prompt info

Avoid storing large base64 payloads in IndexedDB unless size is tightly bounded.

### 11.9 Failure Semantics

Mock execution can fail for:

- config validation failure
- missing required inputs
- intentional fixture-based failure case
- internal mock executor exception
- user cancellation

Failures should be deterministic when tied to invalid inputs/config.

#### 11.9.1 Downstream Error Propagation

Downstream nodes are not eagerly marked failed. Instead:

- the node that throws becomes `error`
- any later node whose required inputs depend on that failed output becomes `skipped`
- the skip reason is `upstreamFailed`
- optional inputs from a failed node may be omitted without blocking execution if all required inputs are still satisfied

This makes `error` mean "this node itself failed" and `skipped` mean "this node was not runnable".

### 11.10 Manual Review Nodes

`reviewCheckpoint` needs special behavior.

Options:

- auto-approve in full workflow mock mode
- pause and prompt in manual review mode
- store decision payload as `reviewDecision`

When `manualApprove` is active, the node enters `awaitingReview` and the run pauses until the user approves or rejects.

This preserves human-in-the-loop realism without needing collaboration features.

---

## 12. Persistence, Import/Export, And Recovery

### 12.1 Persistence Strategy

Use Dexie over IndexedDB for:

- workflows
- templates
- recent runs
- snapshots
- run cache metadata

Do not rely on localStorage except possibly for tiny non-critical preferences.

#### 12.1.1 Multi-Tab Safety

Even though true multi-tab sync is out of scope, the app should still detect concurrent editing:

- use `BroadcastChannel` when available to announce active workflow sessions
- each tab gets a `sessionId`
- broadcast a heartbeat every 5 seconds
- expire sessions after 15 seconds without heartbeat
- ignore announcements from the current `sessionId`
- if the same workflow opens in a second tab, show a warning banner in both tabs
- use a soft lock, not a hard block
- last writer wins for persistence, but the risk is made explicit in the UI

### 12.2 Database Tables

Recommended tables:

- `workflows`
- `workflowSnapshots`
- `executionRuns`
- `nodeRunRecords`
- `runCacheEntries`
- `appPreferences`

#### 12.2.1 Concrete Dexie Schema

```ts
import Dexie, { type Table } from 'dexie';

export interface StoredWorkflowRow {
  readonly id: string;
  readonly name: string;
  readonly updatedAt: string;
  readonly basedOnTemplateId?: string;
  readonly tags: readonly string[];
  readonly document: WorkflowDocument;
}

export interface NodeRunRecordRow extends NodeRunRecord {
  readonly id: string;
  readonly workflowId: string;
}

export interface RunCacheEntry {
  readonly id: string;
  readonly workflowId: string;
  readonly nodeId: string;
  readonly cacheKey: string;
  readonly nodeType: string;
  readonly nodeTemplateVersion: string;
  readonly createdAt: string;
  readonly lastAccessedAt: string;
  readonly expiresAt?: string;
  readonly outputPayloads: Readonly<Record<string, PortPayload>>;
}

export interface AppPreferenceRow {
  readonly key: string;
  readonly value: unknown;
}

export class WorkflowDexie extends Dexie {
  workflows!: Table<StoredWorkflowRow, string>;
  workflowSnapshots!: Table<WorkflowSnapshot, string>;
  executionRuns!: Table<ExecutionRun, string>;
  nodeRunRecords!: Table<NodeRunRecordRow, string>;
  runCacheEntries!: Table<RunCacheEntry, string>;
  appPreferences!: Table<AppPreferenceRow, string>;

  constructor() {
    super('ai-video-builder');

    this.version(1).stores({
      workflows: 'id, updatedAt, name, *tags',
      workflowSnapshots: 'id, workflowId, kind, savedAt',
      executionRuns: 'id, workflowId, status, startedAt',
      nodeRunRecords: 'id, runId, workflowId, nodeId, status',
      runCacheEntries: 'id, workflowId, nodeId, cacheKey, createdAt, lastAccessedAt',
      appPreferences: 'key',
    });

    this.version(2).stores({
      workflows: 'id, updatedAt, name, basedOnTemplateId, *tags',
      workflowSnapshots: 'id, workflowId, kind, savedAt, interruptedRunId',
      executionRuns: 'id, workflowId, status, trigger, startedAt',
      nodeRunRecords: 'id, runId, workflowId, nodeId, status, completedAt',
      runCacheEntries: 'id, workflowId, nodeId, cacheKey, nodeType, lastAccessedAt, expiresAt',
      appPreferences: 'key',
    }).upgrade(async (tx) => {
      await tx.table('workflows').toCollection().modify((row: StoredWorkflowRow) => {
        row.basedOnTemplateId ??= row.document.basedOnTemplateId;
      });

      await tx.table('runCacheEntries').toCollection().modify((row: RunCacheEntry) => {
        row.lastAccessedAt ??= row.createdAt;
      });
    });
  }
}
```

#### 12.2.2 Repository Opening Strategy

```ts
export type PersistenceMode = 'indexeddb' | 'memory-fallback' | 'unavailable';

export async function openWorkflowRepository(): Promise<{
  readonly mode: PersistenceMode;
  readonly db?: WorkflowDexie;
  readonly reason?: string;
}> {
  if (typeof indexedDB === 'undefined') {
    return { mode: 'memory-fallback', reason: 'IndexedDB API unavailable' };
  }

  try {
    const db = new WorkflowDexie();
    await db.open();
    return { mode: 'indexeddb', db };
  } catch (error) {
    return {
      mode: 'memory-fallback',
      reason: error instanceof Error ? error.message : 'Failed to open IndexedDB',
    };
  }
}
```

### 12.3 Autosave

Autosave should occur:

- after command batches
- after a debounce on config edits
- after topology changes
- after metadata edits

Autosave should save a document snapshot, not transient UI noise.

During an active run, autosave must write:
- the current document snapshot
- a separate recovery snapshot keyed by `documentHash` and `activeRunId`

These writes must be committed atomically.

#### 12.3.1 Retention Policy

Set explicit retention defaults:

- keep the latest 1 committed workflow row per workflow id
- keep the latest 20 autosave snapshots per workflow
- keep the latest 10 execution runs per workflow
- keep the latest 3 reusable cache entries per node/config/input hash family

Garbage collection triggers:

- after successful autosave
- after run completion
- after quota-recovery pruning

Never prune:
- the latest recovery snapshot per workflow
- any run referenced by a recovery snapshot
- cache rows referenced by the latest successful run for the currently open workflow

### 12.4 Undo/Redo

Undo/redo should apply to workflow authoring changes only:

- add/remove node
- connect/disconnect edge
- move node
- change config
- rename node
- edit metadata

Undo/redo should not include:

- run status changes
- inspector tab switches
- selection changes
- temporary hover state

If a node or edge participating in an active run is deleted, the active run continues against its captured snapshot and the edited workflow is marked `runStale` until the next run.

### 12.5 Import/Export

Export format should be versioned JSON.

On import:

1. parse JSON
2. validate outer document shape
3. check version
4. run migrations if needed
5. validate every node against registry
6. validate edge references
7. validate semantic compatibility for every edge against the compatibility registry
8. validate configs
9. surface import report:
   - imported successfully
   - migrated
   - warnings
   - errors
   - semantically broken edges

If a workflow references an unknown node type or unsupported node template version:

- import the workflow in degraded mode rather than failing the whole document
- render the node as an `Unsupported Node` shell with preserved raw config
- block execution through that node
- allow export without data loss
- surface a clear warning in the inspector and workflow summary

### 12.6 Migration Strategy

Each workflow document version should have migration functions.

Example:

- v1 -> v2: rename `scenePlanner` to `sceneSplitter`, migrate port types, add missing metadata defaults

Migrations should be tested with fixtures.

Migration layers must remain distinct:

- Dexie database version migrations for storage layout
- workflow JSON migrations for document schema
- node-template migrations for per-node config shape changes

Do not collapse these into one mechanism.

### 12.7 Crash Recovery

The system should maintain recovery snapshots when:

- the document is dirty
- there is an active run
- the app is about to unload and unsaved deltas exist

On restart:

- detect recovery snapshot
- compare snapshot timestamp vs last saved workflow
- offer restore UI
- mark interrupted run records as `interrupted`
- reserve `cancelled` for explicit user cancellation

### 12.8 Corruption Handling

If a Dexie or IndexedDB read fails:

- show explicit recovery UI
- allow export of salvageable data if possible
- allow reset local data
- never silently discard corrupted workflow state

#### 12.8.1 Quota And Unavailability Handling

The app must distinguish between:

- IndexedDB unavailable at boot
- transient transaction failure
- quota exceeded
- corrupted row payload

```ts
async function persistWithQuotaRecovery<T>(write: () => Promise<T>): Promise<T> {
  try {
    return await write();
  } catch (error) {
    if (error instanceof Dexie.QuotaExceededError) {
      await pruneRunCacheAndOldSnapshots();
      return await write();
    }
    throw error;
  }
}
```

If the retry also fails:

- preserve in-memory workflow state
- disable non-essential artifact and cache writes for the session
- keep lightweight document persistence if still possible
- show a blocking warning with `Clear Run Cache` and `Export Workflow JSON`
- never silently drop user edits

---

## 13. File Structure

```text
src/
├── app/
│   ├── app.tsx
│   ├── providers.tsx
│   ├── routes.tsx
│   └── layout/
│       ├── app-shell.tsx
│       └── app-header.tsx
├── features/
│   ├── canvas/
│   │   ├── components/
│   │   │   ├── workflow-canvas.tsx
│   │   │   ├── workflow-node-card.tsx
│   │   │   ├── workflow-edge.tsx
│   │   │   ├── canvas-empty-state.tsx
│   │   │   └── run-toolbar.tsx
│   │   └── hooks/
│   │       ├── use-canvas-shortcuts.ts
│   │       └── use-node-dnd.ts
│   ├── node-library/
│   │   └── components/
│   │       ├── node-library-panel.tsx
│   │       ├── node-search.tsx
│   │       └── node-library-item.tsx
│   ├── inspector/
│   │   └── components/
│   │       ├── inspector-panel.tsx
│   │       ├── node-config-tab.tsx
│   │       ├── validation-tab.tsx
│   │       └── metadata-tab.tsx
│   ├── data-inspector/
│   │   └── components/
│   │       ├── data-inspector-panel.tsx
│   │       ├── payload-viewer.tsx
│   │       ├── schema-diff-view.tsx
│   │       └── lineage-view.tsx
│   ├── workflow/
│   │   ├── store/
│   │   │   ├── workflow-store.ts
│   │   │   └── workflow-selectors.ts
│   │   └── commands/
│   │       ├── add-node.ts
│   │       ├── connect-ports.ts
│   │       ├── disconnect-edge.ts
│   │       ├── insert-node-on-edge.ts
│   │       ├── delete-selection.ts
│   │       ├── duplicate-node.ts
│   │       ├── update-node-config.ts
│   │       └── history.ts
│   ├── execution/
│   │   ├── store/
│   │   │   ├── run-store.ts
│   │   │   └── run-selectors.ts
│   │   ├── commands/
│   │   │   ├── run-workflow.ts
│   │   │   ├── run-node.ts
│   │   │   ├── run-from-here.ts
│   │   │   ├── run-up-to-here.ts
│   │   │   └── cancel-run.ts
│   │   ├── domain/
│   │   │   ├── run-planner.ts
│   │   │   ├── mock-executor.ts
│   │   │   ├── run-cache.ts
│   │   │   └── execution-types.ts
│   │   └── utils/
│   │       └── payload-hashing.ts
│   ├── workflows/
│   │   ├── data/
│   │   │   ├── workflow-db.ts
│   │   │   ├── workflow-repository.ts
│   │   │   ├── workflow-migrations.ts
│   │   │   └── crash-recovery.ts
│   │   └── domain/
│   │       ├── workflow-types.ts
│   │       ├── graph-validator.ts
│   │       ├── type-compatibility.ts
│   │       └── preview-engine.ts
│   ├── node-registry/
│   │   ├── node-registry.ts
│   │   ├── fixtures/
│   │   └── templates/
│   │       ├── user-prompt.ts
│   │       ├── script-writer.ts
│   │       ├── scene-splitter.ts
│   │       ├── prompt-refiner.ts
│   │       ├── image-generator.ts
│   │       ├── image-asset-mapper.ts
│   │       ├── tts-voiceover-planner.ts
│   │       ├── subtitle-formatter.ts
│   │       ├── video-composer.ts
│   │       ├── review-checkpoint.ts
│   │       └── final-export.ts
│   └── templates/
│       └── built-in-templates.ts
├── shared/
│   ├── lib/
│   │   ├── ids.ts
│   │   ├── time.ts
│   │   ├── zod-helpers.ts
│   │   └── formatters.ts
│   └── ui/
│       ├── button.tsx
│       ├── panel.tsx
│       ├── tabs.tsx
│       ├── badge.tsx
│       └── dialog.tsx
└── tests/
    ├── fixtures/
    ├── unit/
    ├── integration/
    └── e2e/
```

---

## 14. Key Technical Decisions

### Decision 1: Local-First Persistence With Dexie

Use IndexedDB through Dexie, not localStorage.

Why:

- workflows and run artifacts will outgrow localStorage comfort
- snapshots and history need structured querying
- crash recovery is easier with explicit tables

### Decision 2: Schema-Driven Node Registry

Every node template must define:

- `configSchema`
- `inputs`
- `outputs`
- `fixtures`
- `buildPreview`
- `executable`
- optional `mockExecute`

Why:

- single source of truth
- form generation and validation alignment
- reliable previews
- predictable testing

### Decision 3: DAG-Only In V1

No loops, conditions, retries, or schedulers.

Why:

- preserves graph clarity
- keeps validation tractable
- avoids premature workflow-engine complexity

### Decision 4: Semantic Types With Explicit Compatibility Rules

Use semantic port types plus a compatibility registry.

Why:

- preserves clarity
- avoids "everything is JSON"
- makes edge validation meaningful
- supports adapter recommendations

### Decision 5: Preview Plus Mock Execution

Do not stop at previews. Add deterministic mock execution.

Why:

- preview-only is too weak
- real execution is too expensive
- mock execution is the right middle ground

### Decision 6: Separate Workflow Store And Run Store

Why:

- cleaner undo/redo
- cleaner persistence
- easier crash recovery
- less accidental coupling

### Decision 7: Data Inspector As A Pillar, Not A Panel

Why:

- the biggest problem in AI workflows is often not generation itself, but understanding intermediate data and failures
- inspectability is the product moat

### Decision 8: Manual Run First, Workflow Run Second

Primary interaction should be node-focused.

Why:

- matches debugging reality
- reduces perceived system complexity
- supports builder-first behavior

### Decision 9: Bounded Mock Artifacts

Use metadata-rich placeholders, not full binary storage.

Why:

- protects memory and IndexedDB health
- sufficient for v1 validation
- cleaner future migration path

### Decision 10: Import/Migration/Recovery Are Core Features

Why:

- local-first apps feel amateurish when imports are brittle or recovery is absent
- workflow JSON is a product artifact, not an afterthought

---

## 15. Example Node Template

```ts
import { z } from 'zod';

const scriptWriterConfigSchema = z.object({
  style: z.string().min(1),
  structure: z.string().min(1),
  includeHook: z.boolean(),
  includeCTA: z.boolean(),
  targetDurationSeconds: z.number().int().min(15).max(180),
});

type ScriptWriterConfig = z.infer<typeof scriptWriterConfigSchema>;

export const scriptWriterTemplate: NodeTemplate<ScriptWriterConfig> = {
  type: 'scriptWriter',
  templateVersion: '1.0.0',
  title: 'Script Writer',
  category: 'script',
  description: 'Generates a short-form video script from an upstream prompt.',
  executable: true,
  inputs: [
    {
      key: 'prompt',
      label: 'Prompt',
      direction: 'input',
      dataType: 'prompt',
      required: true,
      multiple: false,
    },
  ],
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
    style: 'educational',
    structure: 'hook-body-cta',
    includeHook: true,
    includeCTA: true,
    targetDurationSeconds: 45,
  },
  configSchema: scriptWriterConfigSchema,
  fixtures: [
    {
      id: 'default-educational',
      label: 'Educational explainer',
    },
    {
      id: 'dramatic-launch',
      label: 'Dramatic product launch',
      config: {
        style: 'dramatic',
        targetDurationSeconds: 60,
      },
    },
  ],
  buildPreview: ({ config, inputs }) => {
    const promptInput = inputs.prompt?.value as { goal?: string } | undefined;

    return {
      script: {
        value: {
          title: `Script (${config.style})`,
          hook: config.includeHook
            ? `In ${config.targetDurationSeconds} seconds, discover something new.`
            : undefined,
          beats: [
            'Open with a striking visual',
            'Explain the core idea simply',
            'Show why the workflow matters',
          ],
          cta: config.includeCTA ? 'Try building your own workflow.' : undefined,
          goal: promptInput?.goal ?? 'Explain the concept clearly',
        },
        status: 'ready',
        schemaType: 'script',
        producedAt: new Date().toISOString(),
        sourceNodeId: undefined,
        sourcePortKey: 'script',
        previewText: `${config.style} script with 3 beats`,
      },
    };
  },
  mockExecute: async ({ nodeId, config, inputs, signal, runId }) => {
    if (signal.aborted) {
      throw new DOMException('Execution aborted', 'AbortError');
    }

    const promptPayload = inputs.prompt;
    const promptValue = promptPayload?.value as { topic?: string; goal?: string } | null;

    const topic = promptValue?.topic ?? `Script (${config.style})`;

    await new Promise((resolve) => setTimeout(resolve, 150));

    if (signal.aborted) {
      throw new DOMException('Execution aborted', 'AbortError');
    }

    return {
      script: {
        value: {
          title: topic,
          hook: config.includeHook
            ? `In ${config.targetDurationSeconds} seconds, explain ${topic}.`
            : undefined,
          narration: `This is a ${config.style} script for ${topic}.`,
          beats: [
            'Hook the viewer',
            'Explain the concept',
            'Reveal the result',
          ],
          cta: config.includeCTA ? 'Build the workflow visually.' : null,
          runId,
        },
        status: 'success',
        schemaType: 'script',
        producedAt: new Date().toISOString(),
        sourceNodeId: nodeId,
        sourcePortKey: 'script',
        previewText: `${topic} script with 3 beats`,
      },
    };
  },
};
```

---

## 16. Validation Rules

### 16.1 Workflow-Level Rules

- workflow must have a non-empty name
- node ids must be unique
- edge ids must be unique
- source and target node ids must exist
- ports referenced by edges must exist on the respective node templates
- graph must be acyclic
- disabled nodes with connected required outputs should produce warnings for downstream consumers

### 16.2 Node-Level Rules

- config must satisfy node schema
- required inputs must be connected or resolvable through allowed defaults/fixtures
- nodes with unsupported mixed modes should error
- review nodes must have valid review mode config

### 16.3 Edge-Level Rules

- source port must be output
- target port must be input
- compatibility result must be valid or warnable
- duplicate incompatible edges into single-value ports should error
- self-loop edges should error

For `multiple: true` inputs, resolved input arrays are ordered by `targetOrder`, then edge creation time.

### 16.4 Runtime Validation Rules

Before mock execution:

- node config must validate
- required upstream payloads must exist or be derivable
- run scope must be non-empty
- any stale cache mismatch should invalidate reuse

### 16.5 Validation Surfacing

Errors and warnings should be visible in:

- node badges
- edge badges
- inspector validation tab
- workflow summary
- import report
- run report

---

## 17. Template System

### 17.1 Why Templates Matter

Templates make the product useful before real providers exist. They reduce blank-canvas friction and teach graph patterns.

### 17.2 Built-In Templates For V1

Recommended initial templates:

- `NarratedStoryVideo`
- `ProductLaunchTeaser`
- `EducationalExplainer`
- `SilentVisualStoryboard`
- `ScriptToSubtitledPromo`

Each template should include:

- pre-positioned nodes
- valid edges
- sensible defaults
- preview fixtures
- a short description
- tags

### 17.3 Template Requirements

A built-in template is not ready until:

- it validates cleanly
- it mock-runs successfully
- its payloads are inspectable
- its empty states are polished
- it demonstrates a meaningful workflow pattern

Each built-in template must declare:
- `templateVersion`
- `registryVersion`
- `minimumWorkflowSchemaVersion`

Template instantiation must validate against the current node registry before creation.

---

## 18. Browser And Runtime Constraints

### 18.1 No Real Provider Calls In V1

This is both a product decision and a browser practicality decision.

Avoiding real provider calls in v1 eliminates:

- CORS complexity
- secret handling
- long polling
- webhook needs
- rate limiting
- cost control
- media upload/download complexity

### 18.2 Memory Constraints

Do not store large artifacts in memory or IndexedDB indiscriminately. Prefer:

- metadata descriptors
- small thumbnails
- truncated previews
- synthetic URLs

Graph-size target for v1:
- support smooth authoring up to 15 nodes / 25 edges on a mid-range laptop
- warn when the workflow exceeds that envelope

Persistence budget for v1:
- target serialized `WorkflowDocument` size <= 1 MB
- warn at 750 KB
- degrade rich artifact persistence to metadata-only above 1 MB

#### 18.2.1 Mock Video Preview Contract

```ts
export interface MockVideoAsset {
  readonly kind: 'mockVideoAsset';
  readonly posterUrl: string;
  readonly durationMs: number;
  readonly width: number;
  readonly height: number;
  readonly timeline: readonly {
    readonly sceneId: string;
    readonly startMs: number;
    readonly endMs: number;
    readonly imageUrl: string;
    readonly caption?: string;
    readonly subtitleLines?: readonly string[];
    readonly transition?: 'cut' | 'fade' | 'slide';
  }[];
  readonly previewMode: 'storyboard-player';
}
```

The user-visible preview should include:

- poster frame before playback
- play/pause
- timed scene switching
- optional subtitle overlay
- metadata badges for aspect ratio, duration, fps, and transition style

If `timeline.length === 0` or upstream image generation yields no images:
- render an empty-state poster area
- show subtitle/audio metadata if present
- label the preview `metadataOnly`
- never present playback as successful video output

#### 18.2.2 V2 Rendering Strategy

Recommended progression:

1. `Canvas API + MediaRecorder` for lightweight local preview rendering
2. optional `FFmpeg.wasm` for advanced local export/transcode workflows
3. server-side rendering only when real execution or durable exports justify it

Stage plan:

- v1: storyboard preview only
- v2a: Canvas API + MediaRecorder
- v2b: lazy-loaded FFmpeg.wasm
- later: server-side rendering

#### 18.2.3 FFmpeg.wasm Constraints

If FFmpeg.wasm is introduced later:

- expect roughly 25-31 MB initial download depending on build
- load it only via dynamic import
- initialize only on explicit render/export action
- run it in a worker, never on the main thread

```ts
const loadFfmpeg = () => import('../rendering/ffmpeg-renderer');
```

#### 18.2.4 Browser Memory Budget

Set explicit budgets for v1:

- max persisted mock artifact payload per workflow: about 40 MB
- max in-memory active preview media budget per session: about 80 MB
- poster or thumbnail target size: <= 512 KB each
- revoke object URLs on unmount or payload replacement
- never persist raw frame arrays or large base64 blobs

If the budget is exceeded:

- degrade to metadata-only preview for oversized payloads
- preserve the run record
- show a warning that rich preview was skipped due to size limits

### 18.3 Future CORS Awareness

The architecture should acknowledge that future real execution may require:

- a local desktop bridge
- a localhost proxy
- a backend executor

But none of these belong in v1 delivery.

---

## 19. Testing Strategy

### 19.1 Testing Philosophy

Tests should verify product behavior, not merely implementation trivia.

The highest-value risk areas are:

- graph validity
- type compatibility
- preview correctness
- mock execution correctness
- partial rerun correctness
- persistence and migration correctness
- crash recovery

### 19.2 Unit Tests

Test:

- topological sort
- cycle detection
- retention pruning preserves protected recovery rows
- quota recovery retries once after cache pruning
- compatibility matrix
- coercion rules
- config schema validation
- preview propagation
- run planning
- cache eligibility
- payload hashing
- migration functions

### 19.3 Component Tests

Test:

- node library filtering
- inspector form rendering
- payload truncation, virtualization, and copy/download behavior at each threshold
- validation badges
- edge selection behavior
- data inspector JSON/raw toggle
- run toolbar button states
- recovery dialog

### 19.4 Integration Tests

Test:

- boot with IndexedDB available
- boot in memory-fallback mode
- boot with recovery snapshot present
- boot with fatal repository failure
- editing config recomputes preview
- running a node writes run records
- run-from-here reuses upstream cache
- cancelling a run updates node statuses correctly
- importing older workflow versions migrates successfully
- insert adapter node on an edge as one atomic command
- undo restores the original edge in one history step
- redo re-inserts the node with the same resolved ports

### 19.5 E2E Tests

Playwright scenarios:

1. Create a valid workflow from scratch.
2. Reject an incompatible connection.
3. Insert adapter node to fix connection.
4. Run workflow in mock mode.
5. Inspect edge payload.
6. Fail a node due to invalid config.
7. Fix config and run from here.
8. Export and reimport workflow.
9. Refresh during dirty edit and recover draft.
10. Refresh during active run and recover interrupted state.
11. Open the same workflow in two tabs and show the soft-lock warning in both.
12. Close one tab and clear the warning after heartbeat expiry.

### 19.6 Fixtures

Each node should ship with fixtures for:

- happy path
- minimal valid config
- edge-case config
- intentional failure case where appropriate

This is one of the best ideas from Plan B and should be non-negotiable.

### 19.7 Coverage Guidance

Do not chase vanity coverage across the whole app. Instead, target very high confidence on:

- validation
- planning
- execution state transitions
- migrations
- persistence

---

## 20. Risks And Unknowns

### 20.1 Product Risks

Risk:
Builder-first may still feel abstract.

Mitigation:
Strong templates, Data Inspector, realistic mock execution, believable asset placeholders.

Risk:
Too many node types too early could create cognitive overload.

Mitigation:
Keep v1 catalog curated and opinionated.

### 20.2 Technical Risks

Risk:
React Flow handle updates and derived validation can get out of sync.

Mitigation:
Use stable template definitions, derived selectors, and explicit recompute boundaries.

Risk:
Undo/redo becomes fragile if commands are too granular.

Mitigation:
Use command batching and store only authoring state in history.

Risk:
IndexedDB grows from snapshots and cache.

Mitigation:
Bound cache size, age out old runs, keep artifacts lightweight.

Risk:
Cancellation races.

Mitigation:
Centralize `AbortController` ownership in `runStore`, ensure final status writes are guarded.

### 20.3 Scope Risks

Risk:
Temptation to add real providers because mock execution works well.

Mitigation:
Treat `RealExecutor` as a future extension point only. Do not add provider UX in v1.

Risk:
Trying to solve branching, loops, or batching too early.

Mitigation:
Keep DAG-only rule absolute for v1.

### 20.4 Unknowns

- the ideal granularity of `scene` data for downstream composition
- whether `subtitleFormatter` should operate from script alone or require audio timings for best results later
- whether `reviewCheckpoint` needs one or multiple review modes in v1
- whether node-level thumbnails are worth the complexity in the first release

These should be resolved through product iteration, not infrastructure expansion.

---

## 21. Implementation Roadmap

### Milestone 1: Document Model And Registry

Build:

- workflow types
- node registry
- node templates
- config schemas
- fixtures

Success criteria:

- nodes can be instantiated from registry
- schema validation works
- template metadata renders in library

### Milestone 2: Canvas And Authoring Shell

Build:

- app shell
- canvas
- node library
- inspector
- add/connect/delete/move commands
- selection model

Success criteria:

- user can author a graph comfortably
- workflow validates structurally

### Milestone 3: Validation And Preview

Build:

- graph validator
- type compatibility registry
- preview engine
- inspector validation tab
- edge inspector

Success criteria:

- invalid graphs explain themselves
- previews update on config and connection changes

### Milestone 4: Mock Execution

Build:

- run store
- run planner
- mock executor
- run toolbar
- node statuses
- cancellation

Success criteria:

- node, branch, and workflow mock runs function
- partial rerun works
- payloads are inspectable

### Milestone 5: Persistence And Recovery

Build:

- Dexie schema
- repository
- autosave
- import/export
- migrations
- recovery flow

Success criteria:

- workflow documents survive reloads
- older versions import safely
- interrupted state can be restored

### Milestone 6: Templates And Polish

Build:

- built-in templates
- keyboard shortcuts
- empty states
- a11y passes
- performance polish

Success criteria:

- new users can be productive quickly
- app feels intentional rather than skeletal

---

## 22. Final Recommendation

The revised plan should **not** abandon Plan A's builder-first conviction. That was the best strategic call in the original set. The mistake would be to swing too far toward the other proposals and accidentally build the hardest parts of an execution platform before the product has proven its design value.

But Plan A did need major strengthening. The three biggest corrections are:

- add deterministic mock execution
- make the Data Inspector a primary feature
- define concrete run, payload, compatibility, recovery, and node models

That hybrid is materially better than the original Plan A and materially more disciplined than Plans B, C, and D taken literally.

The best v1 is therefore:

- local-first
- single-user
- builder-first
- contract-aware
- deeply inspectable
- mock-executable
- resilient
- intentionally constrained

That is the version most likely to ship cleanly, demo strongly, and evolve into a real execution platform later without regretting its foundations.
