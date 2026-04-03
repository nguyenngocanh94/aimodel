## Design Exploration: Three-Panel Workflow Builder

This proposal outlines two distinct visual languages for a three-panel workflow builder, both adhering to a developer-first, dark mode aesthetic using Tailwind CSS and `shadcn/ui` primitives. We draw inspiration from tools like Figma (clean UI, nesting), n8n (node variety, execution states), Comfy UI (technical density, connection types), and React Flow (graph interaction).

---

### Model 1: "Slate Utility" (Minimalist & Structured)

This model focuses on maximizing screen real estate for the canvas, using a flat, structured layout typical of a modern IDE or utility dashboard. It emphasizes clear boundaries and high contrast for readability.

#### Visual Representation

#### Design Specifications

* **Color System:**
    * **Backgrounds:** `slate-950` (canvas), `slate-900` (panels, node bodies)
    * **Borders/Dividers:** `slate-800` / `slate-700`
    * **Text:** `slate-200` (primary), `slate-400` (muted)
    * **Accent (Interactive):** `sky-500` (focus borders, active toggles)
    * **States:** `green-500` (success), `amber-400` (running), `red-500` (error)
* **Typography:**
    * **UI Font:** Inter (Sans-serif, `font-sans`), optimized for readability at small sizes.
    * **Code/Data Font:** JetBrains Mono (`font-mono`) for monospace data previews.
* **Node Card Component:** Rectangular cards (`rounded-md`, `border`). Header section (`bg-slate-800`, `p-2`) contains node type icon and title. Body section (`p-3`, `bg-slate-900`) lists input/output handles (dots) and minimal previews.
    * *States:* Default, Hover (subtle `border-slate-600`), Selected (`border-2 border-sky-500`), Executing (`border-2 border-amber-400`, subtle pulse), Error (`border-2 border-red-500`).
* **Edge Design:** Typed edges are straight `slate-600` lines with small directional arrows.
    * *States:* Default, Hover (`stroke-sky-400`, thicker line), Active Connection (`stroke-sky-500`, animated dash array).
* **Inspector Layout:** Vertically stacked `shadcn/ui` tabs.
    * *Tabs:* `Config` (Form fields: input, select, switch); `Input/Output` (Monospace JSON tree view of data passing through the selected node).
* **Run Toolbar:** A fixed horizontal bar at the top right of the canvas.
    * *States:* `Idle` (▶️ Run, ⏹️ Stop [disabled]); `Running` (⏹️ Stop, `Running...` indicator, execution time); `Success` (✅ Success, `Run Again`); `Error` (⚠️ Error, `View Logs`, `Run Again`).
* **Empty State:** When no workflow is loaded, the central canvas displays a subtle centered `shadcn/ui` Card with an icon, a "Create New Workflow" button, and a "Import from JSON" utility link.

---

### Model 2: "Slate Technical" (Information Dense & Expressive)

This model prioritizes technical context and information density. It uses more expressive component designs (like color-coded nodes and connection types) and a slightly deeper color palette to manage high visual complexity, similar to Comfy UI or dense n8n deployments.

#### Visual Representation

#### Design Specifications

* **Color System:**
    * **Backgrounds:** `slate-950` (canvas), `slate-900` (panel bg, base node), `slate-800` (panel headers)
    * **Borders/Dividers:** `slate-700` / `slate-600`
    * **Text:** `slate-100` (primary), `slate-400` (muted)
    * **Accent (Interactive):** `violet-500` (focus borders, active toggles)
    * **States/Types:** `cyan-400` (Trigger nodes), `emerald-500` (Data nodes), `violet-400` (Logic nodes), `amber-400` (Executing/Warning), `red-500` (Error).
* **Typography:**
    * **UI/Labels:** Roboto Mono (`font-mono`) – giving the entire UI a technical, precise feel.
    * **Code/Data Font:** Roboto Mono (`font-mono`).
* **Node Card Component:** Slightly rounded (`rounded-lg`, `border`). Node headers feature a category icon and distinct color band (e.g., `bg-emerald-950` border-emerald-500`). Body sections show more compact, interactive data previews (small inputs or JSON snippets). Handles (ports) are prominent, colored dots.
    * *States:* Default, Hover (`border-slate-500`), Selected (`border-2 border-violet-500`, subtle outer glow), Executing (`border-2 border-amber-400`, pulse animation), Error (`border-2 border-red-500`).
* **Edge Design:** Curved, colored bezier curves indicating data type (e.g., `orange` for JSON objects, `blue` for binary streams).
    * *States:* Default, Hover (`stroke-violet-400`, thicker line), Active Connection (`stroke-violet-500`, animated flow/particles).
* **Inspector Layout:** Denser `shadcn/ui` Accordion or horizontal Tabs.
    * *Accordion Sections:* `Node Settings` (Complex `shadcn/ui` forms); `Execution Data` (Nested monospace JSON viewer, showing input and output per execution run).
* **Run Toolbar:** A prominent, styled button group positioned centrally in the main app header.
    * *States:* `Idle` (`▶️ Run Pipeline`, `⏹️ Stop` [disabled]); `Running` (`⏹️ Stop Pipeline`, `Executing Step 4/10...`, mini-progress bar); `Success` (`✅ Pipeline Success`, `Run Again`); `Error` (`⚠️ Pipeline Failed`, `View Error Log`, `Run Again`).
* **Empty State:** The central canvas shows a complex network pattern background and a centered, actionable `shadcn/ui` Alert/Card containing a "Workflow Wizard" (start from template) or "Blank Canvas" button.

---

### Comparison of Approaches

| Feature | Model 1: "Slate Utility" | Model 2: "Slate Technical" |
| :--- | :--- | :--- |
| **Primary Focus** | Clarity, Screen Real Estate, Cleanliness | Information Density, Technical Context |
| **Aesthetic** | Flat, Minimalist IDE | Dense, Expressive, Color-Coded |
| **Layout** | Rigid Panels, Simple Grid Canvas | Collapsible Panels, Patterned Canvas |
| **Nodes** | Rectangular, subtle states | Curved, color-banded, prominent states |
| **Edges** | Straight, gray, simple state | Curved, color-coded (data types), active flow |
| **Typography** | Inter (Sans) for UI, Mono for Data | Roboto Mono for entire technical UI |
| **Inspector** | Vertical Tabs | Accordion / Horizontal Tabs (Dense) |
| **Use Case** | General purpose API orchestration | Complex data pipelines, ML workflows |
