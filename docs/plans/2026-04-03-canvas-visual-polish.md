# Canvas Visual Polish Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Improve canvas visual quality — node cards with better depth/contrast, and edges with data-type color coding and always-visible labels.

**Architecture:** Pure CSS/styling changes across 3 files: theme tokens (index.css), node card (workflow-node-card.tsx), edge component (workflow-edge.tsx), and edge data plumbing (canvas-surface.tsx).

**Tech Stack:** React, Tailwind CSS, @xyflow/react

---

### Task 1: Theme Token — Brighter Card Background

**Files:**
- Modify: `src/index.css:85` (dark theme `--card` value)

**Step 1: Update dark card token**

Change `--card: #11141B` to `--card: #131720` in the `.dark` block. This gives nodes more contrast against the `#0B0D12` canvas background.

```css
--card: #131720;
```

**Step 2: Verify visually**

Run: `npm run dev`
Expected: Node cards appear slightly brighter against the canvas.

**Step 3: Commit**
```bash
git add src/index.css
git commit -m "style: bump dark card bg for better canvas contrast"
```

---

### Task 2: Node Card Visual Polish

**Files:**
- Modify: `src/features/canvas/components/workflow-node-card.tsx`

**Step 1: Replace shadow-sm with stronger shadow, add hover lift**

In the main card `className`, replace:
- `shadow-sm` → `shadow-[0_2px_8px_rgba(0,0,0,0.3)]`
- Add hover: `hover:-translate-y-px hover:shadow-[0_4px_12px_rgba(0,0,0,0.4)]`
- On selected, amplify glow: `shadow-[0_0_16px_rgba(56,189,248,0.15)]` → `shadow-[0_0_20px_rgba(56,189,248,0.2)]`

**Step 2: Move category accent from top bar to left border strip**

Replace the 2px top bar div with a 3px left border running full height:
- Remove `<div className="absolute inset-x-0 top-0 h-0.5 rounded-t-lg ..." />`
- Change accent map from `bg-*` classes to `border-l-*` classes
- Add `border-l-[3px]` with category color to the card root

Need new accent map using `border-l-node-{category}` instead of `bg-node-{category}/70`.

**Step 3: Make title font-semibold**

Change `font-medium` to `font-semibold` on the `<h3>` element.

**Step 4: Commit**
```bash
git add src/features/canvas/components/workflow-node-card.tsx
git commit -m "style: polish node cards — stronger shadow, left accent, hover lift"
```

---

### Task 3: Edge Color Coding — Data Plumbing

**Files:**
- Modify: `src/features/canvas/components/canvas-surface.tsx:82-117` (`toReactFlowEdge` function)

**Step 1: Pass sourceDataType to edge data**

The edge component already reads `data.sourceDataType` but `toReactFlowEdge` never sets it. Add `sourceDataType: sourcePort.dataType` to the edge data.

```typescript
if (sourcePort && targetPort) {
  const compat = checkCompatibility(sourcePort.dataType, targetPort.dataType);
  if (!compat.compatible) {
    edgeData = { validationStatus: 'invalid', sourceDataType: sourcePort.dataType };
  } else if (compat.coercionApplied) {
    edgeData = { validationStatus: 'warning', sourceDataType: sourcePort.dataType };
  } else {
    edgeData = { validationStatus: 'valid', sourceDataType: sourcePort.dataType };
  }
}
```

Also handle the case where ports aren't found but we still want color:
```typescript
if (sourcePort) {
  edgeData.sourceDataType = sourcePort.dataType;
}
```

**Step 2: Commit**
```bash
git add src/features/canvas/components/canvas-surface.tsx
git commit -m "feat: pass sourceDataType through to edge component"
```

---

### Task 4: Edge Visual Polish

**Files:**
- Modify: `src/features/canvas/components/workflow-edge.tsx`

**Step 1: Add stroke color map by data category**

Add a new lookup similar to `pillStyles` but for stroke classes:

```typescript
const strokeStyles: Record<EdgeDataCategory, string> = {
  video: 'stroke-amber-400/50',
  audio: 'stroke-teal-400/50',
  image: 'stroke-cyan-400/50',
  data: 'stroke-violet-400/40',
  control: 'stroke-border/60',
};
```

**Step 2: Apply stroke color to BaseEdge**

Replace `'stroke-border/80'` default with `strokeStyles[category]` as the base stroke.

**Step 3: Increase stroke width**

Change `strokeWidth`: default from 1.5 → 2, selected from 2 → 2.5.

**Step 4: Always-visible type labels**

Change the pill opacity from `hovered || selected ? 'opacity-100' : 'opacity-0'` to `hovered || selected ? 'opacity-100' : 'opacity-60'`.

**Step 5: Commit**
```bash
git add src/features/canvas/components/workflow-edge.tsx
git commit -m "style: color-coded edges, thicker strokes, always-visible labels"
```

---

### Task 5: Final Visual Verification

Run the dev server and verify:
- [ ] Node cards have visible shadow/depth on canvas
- [ ] Category accent shows as colored left border
- [ ] Hover on node shows subtle lift
- [ ] Selected node has blue glow
- [ ] Edges are color-tinted by data type
- [ ] Edge type pills visible at 60% opacity
- [ ] Edges are slightly thicker and easier to follow

**Final commit (all together if not committed per-task):**
```bash
git add -A
git commit -m "style: canvas visual polish — node depth, edge color coding"
```
