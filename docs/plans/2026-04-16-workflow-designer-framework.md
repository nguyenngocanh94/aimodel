# Node Framework: workflow-designer (Planner Agent)

> Part of AiModel-645.9 — Research node frameworks for vibe-controlled AI workflow design

## 1. Purpose & Position

The workflow-designer is not a node in the video pipeline — it is the **meta-agent that creates the pipeline itself**. It is an alternative to the visual canvas editor: instead of dragging nodes and setting configs manually, the user describes the desired vibe in natural language, and the planner outputs a complete `WorkflowDocument`.

```
User: "I want a funny genz storytelling workflow for TikTok Vietnam"
    ↓
workflow-designer (planner agent)
    ↓
WorkflowDocument (saved to database)
    ↓
User can: run it / save as template / open in canvas to tweak / reuse with any product
```

**The planner produces the same format as the canvas editor.** No special output format. The existing `RunExecutor` runs it directly. The existing canvas can open and edit it.

**Why it's an agent, not a node:** The planner runs once to create a workflow. It doesn't run every time a video is generated. It's a design-time tool, not a runtime node.

## 2. Input

### From user: natural language vibe description

```
"funny genz storytelling for TikTok Vietnam, entertainment first, 
not an ad, product should appear as a surprise/twist"
```

Or structured:
```json
{
  "vibe_description": "funny genz storytelling",
  "platform": "tiktok",
  "market": "vi-VN",
  "entertainment_vs_info": "entertainment first",
  "product_role": "surprise/twist",
  "constraints": ["not an ad", "short, under 30s"]
}
```

The planner accepts both forms. Natural language is parsed into structured intent. Structured input skips the parsing.

### From system: node cards

All available node templates, each as a compact card (~300 tokens):

```yaml
node_id: format-library-matcher
purpose: Select short-video format archetypes from curated library + LLM generation.
position: after truth-constraint-gate, before hook-angle-generator
vibe_impact: critical
human_gate: no
knobs:
  vibe_mode:
    type: enum [funny_storytelling, clean_education, aesthetic_mood, raw_authentic]
    effect: drives which formats are eligible
  entertainment_ratio:
    type: enum [pure_entertainment, entertainment_leading, balanced, info_leading]
    effect: prevents edu formats winning when entertainment demanded
  # ... all knobs with type + effect
connects_to:
  reads_from: [intent-outcome-selector, truth-constraint-gate]
  writes_to: [hook-angle-generator]
when_to_include: always
```

12 node cards × ~300 tokens = ~3,600 tokens total context.

### From system: pipeline rules

Constraints the planner must respect:
- Every workflow needs: brief-ingest, truth-constraint-gate (compliance is not optional)
- Story variant selection: exactly one of story-writer / beat-planner / mood-sequencer
- Human gates: required after hook-angle-generator and after story variant
- Casting: required before shot-compiler
- Shot-compiler + prompt-enhancer: always at the end before API

## 3. Output: WorkflowDocument

The planner outputs a standard `WorkflowDocument` — the same JSON format the canvas editor produces.

```json
{
  "id": "auto-generated",
  "schemaVersion": 1,
  "name": "Funny Genz Storytelling — TikTok VN",
  "description": "AI-generated workflow for funny storytelling vibe. Entertainment-first, product appears as twist.",
  "tags": ["funny", "genz", "storytelling", "tiktok", "vi-VN"],
  "nodes": [
    {
      "id": "node-1",
      "type": "briefIngest",
      "label": "Brief Ingest",
      "position": { "x": 0, "y": 0 },
      "config": {}
    },
    {
      "id": "node-2",
      "type": "intentOutcomeSelector",
      "label": "Intent & Outcome Selector",
      "position": { "x": 300, "y": 0 },
      "config": {
        "vibe_mode": "funny_storytelling",
        "entertainment_ratio": "entertainment_leading",
        "angle_generation_count": 1
      }
    },
    {
      "id": "node-3",
      "type": "truthConstraintGate",
      "label": "Truth & Constraint Gate",
      "position": { "x": 600, "y": 0 },
      "config": {}
    },
    {
      "id": "node-4",
      "type": "formatLibraryMatcher",
      "label": "Format Library Matcher",
      "position": { "x": 900, "y": 0 },
      "config": {
        "vibe_mode": "funny_storytelling",
        "entertainment_ratio": "entertainment_leading",
        "format_preference_tags": ["skit", "pov", "reaction", "confession"],
        "format_avoid_tags": ["ingredient_breakdown", "expert_review", "listicle"],
        "priority_sliders": {
          "virality_priority": 0.65,
          "brand_safety": 0.20,
          "production_ease": 0.15
        },
        "library_ratio": "hybrid",
        "min_novelty_threshold": 0.6,
        "max_formats_returned": 2
      }
    },
    {
      "id": "node-5",
      "type": "hookAngleGenerator",
      "label": "Hook & Angle Generator",
      "position": { "x": 1200, "y": 0 },
      "config": {
        "hook_tension_level": "high",
        "hook_humor_level": "moderate",
        "hook_vibe_styles": ["confession", "absurd_question", "hot_take", "humor_contrast"],
        "must_include_twist": true,
        "library_retrieval_count": 1,
        "llm_generation_count": 2,
        "hooks_presented_to_human": 3
      }
    },
    {
      "id": "node-6",
      "type": "humanGate",
      "label": "Hook Selection Gate",
      "position": { "x": 1500, "y": 0 },
      "config": {
        "channel": "telegram",
        "messageTemplate": "Pick a hook for your video:\n\n{{hooks}}\n\nReply with 1, 2, or 3. Or send feedback to adjust."
      }
    },
    {
      "id": "node-7",
      "type": "storyWriter",
      "label": "Story Writer",
      "position": { "x": 1800, "y": 0 },
      "config": {
        "story_tension_curve": "fast_hit",
        "product_appearance_moment": "twist",
        "humor_density": "throughout",
        "story_versions_for_human": 2,
        "max_moments": 5,
        "target_duration_sec": 30,
        "ending_type_preference": "twist_reveal"
      }
    },
    {
      "id": "node-8",
      "type": "humanGate",
      "label": "Story Selection Gate",
      "position": { "x": 2100, "y": 0 },
      "config": {
        "channel": "telegram",
        "messageTemplate": "Pick a story version:\n\n{{stories}}\n\nReply with 1 or 2. Or send feedback to adjust."
      }
    },
    {
      "id": "node-9",
      "type": "casting",
      "label": "Casting",
      "position": { "x": 2400, "y": 0 },
      "config": {
        "visual_polish_level": "natural_clean",
        "casting_mode": "library_or_create",
        "match_strictness": "flexible"
      }
    },
    {
      "id": "node-10",
      "type": "shotCompiler",
      "label": "Shot Compiler",
      "position": { "x": 2700, "y": 0 },
      "config": {
        "camera_style": "mixed",
        "visual_polish_level": "natural_clean",
        "transition_style": "jump_cut",
        "clips_per_moment": 1,
        "text_overlay_density": "key_moments_only"
      }
    },
    {
      "id": "node-11",
      "type": "promptEnhancer",
      "label": "Prompt Enhancer",
      "position": { "x": 3000, "y": 0 },
      "config": {
        "video_provider": "runway",
        "generation_quality": "standard",
        "reference_image_mode": "face_lock"
      }
    },
    {
      "id": "node-12",
      "type": "editAudioCaptionFinalizer",
      "label": "Edit & Package",
      "position": { "x": 3300, "y": 0 },
      "config": {}
    }
  ],
  "edges": [
    { "id": "e1", "sourceNodeId": "node-1", "sourcePortKey": "brief", "targetNodeId": "node-2", "targetPortKey": "brief" },
    { "id": "e2", "sourceNodeId": "node-2", "sourcePortKey": "intent_pack", "targetNodeId": "node-3", "targetPortKey": "intent_pack" },
    { "id": "e3", "sourceNodeId": "node-1", "sourcePortKey": "brief", "targetNodeId": "node-3", "targetPortKey": "brief" },
    { "id": "e4", "sourceNodeId": "node-3", "sourcePortKey": "grounding", "targetNodeId": "node-4", "targetPortKey": "grounding" },
    { "id": "e5", "sourceNodeId": "node-2", "sourcePortKey": "intent_pack", "targetNodeId": "node-4", "targetPortKey": "intent_pack" },
    { "id": "e6", "sourceNodeId": "node-4", "sourcePortKey": "format_shortlist", "targetNodeId": "node-5", "targetPortKey": "format_shortlist" },
    { "id": "e7", "sourceNodeId": "node-3", "sourcePortKey": "grounding", "targetNodeId": "node-5", "targetPortKey": "grounding" },
    { "id": "e8", "sourceNodeId": "node-2", "sourcePortKey": "intent_pack", "targetNodeId": "node-5", "targetPortKey": "intent_pack" },
    { "id": "e9", "sourceNodeId": "node-5", "sourcePortKey": "hook_pack", "targetNodeId": "node-6", "targetPortKey": "data" },
    { "id": "e10", "sourceNodeId": "node-6", "sourcePortKey": "response", "targetNodeId": "node-7", "targetPortKey": "selected_hook" },
    { "id": "e11", "sourceNodeId": "node-2", "sourcePortKey": "intent_pack", "targetNodeId": "node-7", "targetPortKey": "intent_pack" },
    { "id": "e12", "sourceNodeId": "node-3", "sourcePortKey": "grounding", "targetNodeId": "node-7", "targetPortKey": "grounding" },
    { "id": "e13", "sourceNodeId": "node-4", "sourcePortKey": "vibe_state", "targetNodeId": "node-7", "targetPortKey": "vibe_state" },
    { "id": "e14", "sourceNodeId": "node-7", "sourcePortKey": "story_pack", "targetNodeId": "node-8", "targetPortKey": "data" },
    { "id": "e15", "sourceNodeId": "node-8", "sourcePortKey": "response", "targetNodeId": "node-9", "targetPortKey": "story_approved" },
    { "id": "e16", "sourceNodeId": "node-9", "sourcePortKey": "cast", "targetNodeId": "node-10", "targetPortKey": "cast" },
    { "id": "e17", "sourceNodeId": "node-8", "sourcePortKey": "response", "targetNodeId": "node-10", "targetPortKey": "moments" },
    { "id": "e18", "sourceNodeId": "node-3", "sourcePortKey": "grounding", "targetNodeId": "node-10", "targetPortKey": "grounding" },
    { "id": "e19", "sourceNodeId": "node-10", "sourcePortKey": "clip_pack", "targetNodeId": "node-11", "targetPortKey": "clip_pack" },
    { "id": "e20", "sourceNodeId": "node-9", "sourcePortKey": "cast", "targetNodeId": "node-11", "targetPortKey": "cast" },
    { "id": "e21", "sourceNodeId": "node-11", "sourcePortKey": "api_ready_clips", "targetNodeId": "node-12", "targetPortKey": "clips" }
  ],
  "viewport": { "x": 0, "y": 0, "zoom": 0.5 },
  "createdAt": "auto",
  "updatedAt": "auto"
}
```

This is a real, runnable workflow. The existing `RunExecutor` can execute it immediately. The canvas can open and display it. The user can manually tweak any config before running.

## 4. Config (of the planner agent itself)

The planner is not a pipeline node — it's a standalone agent. But it has its own configuration:

| Config | Type | What it controls |
|--------|------|-----------------|
| `available_node_cards` | path/URL | Where to load node cards from. Default: all registered templates. |
| `pipeline_rules` | object | Required nodes, valid orderings, human gate requirements. Prevents invalid workflows. |
| `default_provider` | enum | Default video API provider. User can override. |
| `iteration_mode` | bool | If true, planner keeps conversation state for iterative refinement ("make it funnier"). |

## 5. How the Planner Works

### Step 1: Parse vibe description

User says: "funny genz storytelling for TikTok Vietnam, entertainment first"

Planner extracts:
```json
{
  "vibe_mode": "funny_storytelling",
  "entertainment_ratio": "entertainment_leading",
  "platform": "tiktok",
  "market": "vi-VN",
  "product_role_preference": "twist"
}
```

### Step 2: Select nodes

From node cards, planner decides:
- Always include: brief-ingest, truth-constraint-gate, format-library-matcher, hook-angle-generator, casting, shot-compiler, prompt-enhancer, edit-audio-caption-finalizer
- Story variant: `funny_storytelling` → **story-writer** (not beat-planner or mood-sequencer)
- Human gates: after hook-angle-generator, after story-writer
- Skip: beat-planner, mood-sequencer (not this vibe)

### Step 3: Set knobs per node

For each selected node, planner reads the node card's knobs section and sets values based on the parsed vibe:

- `entertainment_ratio: entertainment_leading` → propagates to intent-outcome-selector, format-library-matcher
- `funny_storytelling` → format_preference_tags: [skit, pov, confession], hook_tension_level: high, humor_density: throughout
- `product_role: twist` → product_appearance_moment: twist

The planner uses the agent-friendly config guides (Section 9 of each framework) as decision rules. "If user says funny → set hook_tension_level to high."

### Step 4: Wire edges

Planner connects output ports to input ports based on the `connects_to` field in node cards. This is deterministic — for a given set of nodes, the edges follow a fixed pattern defined by port compatibility.

### Step 5: Validate

Before outputting, planner checks:
- Every required node is present
- No cycles in the graph
- Every required input port has an incoming edge
- Config values are within valid ranges
- Human gates are placed after creative decision nodes

### Step 6: Output WorkflowDocument

Save to database. User can run immediately or review first.

## 6. Iteration Support

The planner supports conversational refinement:

```
User: "create a funny genz storytelling workflow"
Planner: [creates workflow] "Here's your workflow. Story-writer with fast_hit tension, 
         twist ending, humor throughout. Hook tension high. Want to adjust?"

User: "make it less product-heavy, more about the situation"
Planner: [adjusts] product_appearance_moment: end → twist, 
         format_preference_tags: remove ingredient-related, 
         add [skit, reaction]

User: "also try Kling instead of Runway"
Planner: [adjusts] prompt-enhancer video_provider: kling, 
         reference_image_mode: face_lock (Kling is good at this)

User: "looks good, save it"
Planner: [saves WorkflowDocument to database]
```

Each iteration updates the WorkflowDocument in place. The user sees the changes reflected. They can also open the canvas to verify visually.

## 7. Contrasting Workflow Examples

### Vibe: funny genz storytelling

```
Nodes: brief-ingest → intent-outcome-selector → truth-constraint-gate 
       → format-library-matcher → hook-angle-generator → [HUMAN GATE] 
       → story-writer → [HUMAN GATE] → casting → shot-compiler 
       → prompt-enhancer → edit-audio-caption-finalizer

Key configs:
  entertainment_ratio: entertainment_leading
  format_preference_tags: [skit, pov, confession]
  hook_tension_level: high
  humor_density: throughout
  product_appearance_moment: twist
  story_tension_curve: fast_hit
  camera_style: mixed
  visual_polish_level: natural_clean
```

### Vibe: clean product education

```
Nodes: brief-ingest → intent-outcome-selector → truth-constraint-gate 
       → format-library-matcher → hook-angle-generator → [HUMAN GATE] 
       → beat-planner → [HUMAN GATE] → casting → shot-compiler 
       → prompt-enhancer → edit-audio-caption-finalizer

Key configs:
  entertainment_ratio: info_leading
  format_preference_tags: [ingredient_breakdown, how_to, myth_fact]
  hook_tension_level: medium
  humor_density: none
  information_depth: moderate
  proof_type: both
  camera_style: close_up_intimate
  visual_polish_level: natural_clean
```

### Vibe: aesthetic mood

```
Nodes: brief-ingest → intent-outcome-selector → truth-constraint-gate 
       → format-library-matcher → hook-angle-generator → [HUMAN GATE] 
       → mood-sequencer → [HUMAN GATE] → casting → shot-compiler 
       → prompt-enhancer → edit-audio-caption-finalizer

Key configs:
  entertainment_ratio: entertainment_leading
  format_preference_tags: [texture_asmr, routine_pov, mood_reel]
  hook_tension_level: low
  humor_density: none
  sensory_focus: ritual
  audio_priority: asmr_sounds
  text_density: product_name_only
  pacing: slow_meditative
  camera_style: close_up_intimate
  visual_polish_level: polished_minimal
```

Three different workflows from three vibe descriptions. Different story variants selected, different knobs set, same infrastructure executing them.

## 8. Node Card Contract

Each node template must provide a node card for the planner. This is the contract defined in bead 645.1.

```yaml
# Node Card Schema
node_id: string                    # matches node type in system
purpose: string                    # 1-2 sentences, what it does
position: string                   # where in the chain
vibe_impact: critical | neutral    # how much it affects video feel
human_gate: boolean                # does it have/need a human gate

knobs:                             # config the planner can set
  knob_name:
    type: string                   # enum, int, float, string[], bool
    options: [...]                 # valid values for enums
    default: value                 # sensible default
    effect: string                 # 1 sentence: what this changes creatively
    vibe_mapping:                  # optional: quick lookup for planner
      funny: value
      education: value
      aesthetic: value

connects_to:
  reads_from: [node_ids]           # upstream nodes
  writes_to: [node_ids]            # downstream nodes
  ports:
    inputs: [{key, type, required}]
    outputs: [{key, type}]

when_to_include: string            # always | when vibe is X | optional
when_to_skip: string               # never | when vibe is X
```

The `vibe_mapping` under each knob is the shortcut. The planner reads: "user wants funny → set this knob to this value." No need to reason from first principles every time.

## 9. Token Budget

```
Input:
  System prompt (planner instructions)     ~500 tokens
  Node cards × 12 × ~300 tokens           ~3,600 tokens
  Pipeline rules                           ~200 tokens
  User vibe description                    ~50 tokens
  ─────────────────────────────────────
  Total input                              ~4,350 tokens

Output:
  WorkflowDocument JSON                    ~2,000 tokens
  Rationale (optional)                     ~300 tokens
  ─────────────────────────────────────
  Total output                             ~2,300 tokens

Total per call: ~6,650 tokens
Iteration call: ~4,500 tokens (same input + previous config + user feedback)
```

Cheap. One Sonnet call per workflow creation. Iteration is even cheaper.

## 10. What the Planner Does NOT Do

- **Does not execute the pipeline.** It creates the workflow document. Execution is handled by `RunExecutor`.
- **Does not interact with users during video generation.** Human gates handle that. The planner only talks to the user during workflow design.
- **Does not know about specific products.** The workflow is product-agnostic. Product data enters at runtime via brief-ingest.
- **Does not replace the canvas editor.** Users can still build workflows visually. The planner is an alternative entry point.
- **Does not make runtime decisions.** All decisions are made at design time (knob values). Nodes execute those decisions at runtime.

---

**Reference artifacts:**
- Workflow types: `frontend/src/features/workflows/domain/workflow-types.ts`
- Run executor: `backend/app/Domain/Execution/RunExecutor.php`
- Execution planner: `backend/app/Domain/Execution/ExecutionPlanner.php`
- Node template base: `backend/app/Domain/Nodes/NodeTemplate.php`
- Human gate: `backend/app/Domain/Nodes/Templates/HumanGateTemplate.php`
- All node frameworks: `docs/plans/2026-04-14-*.md` and `docs/plans/2026-04-15-*.md`
