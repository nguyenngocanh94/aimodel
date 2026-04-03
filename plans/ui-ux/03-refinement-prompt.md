# UI/UX Refinement Prompt for GPT Pro

> Fresh conversation. Extended Reasoning ON. Paste everything below the line.

---

Below is a UI/UX design system for an AI Video Workflow Builder. It's already strong but needs one refinement pass.

Your job: review the entire design system, fix the specific issues listed below, and find any additional problems. Provide git-diff style changes plus the FULL revised document.

## SPECIFIC FIXES NEEDED

### Fix 1: Keyboard Shortcuts Must Match Product Plan
The product plan defines these shortcuts (non-negotiable):
- Cmd/Ctrl+S: save committed local snapshot
- Cmd/Ctrl+Shift+E: export workflow JSON
- Cmd/Ctrl+Z: undo
- Cmd/Ctrl+Shift+Z: redo
- Backspace/Delete: delete selection
- Space: pan mode while held
- A: quick-add node (command menu)
- Enter: inspect selected item
- R: run selected node
- Shift+R: run workflow
- C: connect from selected node/port (searchable dialog)
- Escape: clear selection / close menus

Align section 10 keyboard patterns with these.

### Fix 2: Add Concrete Tailwind Classes
The design describes states but rarely gives actual Tailwind classes. For each major component (node card, edge, inspector, toolbar), add concrete Tailwind class examples that a developer can copy.

### Fix 3: Node Category Colors
Consider adding subtle category color accents to node card headers:
- Input: slate/neutral
- Script: blue
- Visuals: violet
- Audio: teal
- Video: amber
- Utility: gray
- Output: emerald

These should be subtle (header accent line or icon tint), not overwhelming.

### Fix 4: shadcn/ui CSS Variable Alignment
Ensure the token system uses shadcn/ui's actual CSS variable naming convention:
- --background, --foreground
- --card, --card-foreground
- --primary, --primary-foreground
- --secondary, --secondary-foreground
- --muted, --muted-foreground
- --accent, --accent-foreground
- --destructive, --destructive-foreground
- --border, --input, --ring

Map your custom tokens (--signal, --success, --warning) as extensions, not replacements.

### Fix 5: Add data-testid Strategy
For E2E testing with Playwright, define a data-testid naming convention for all interactive elements. Examples:
- `data-testid="node-card-{nodeId}"`
- `data-testid="edge-{edgeId}"`
- `data-testid="inspector-tab-{tabName}"`
- `data-testid="run-btn-workflow"`

### Also: Find 15+ Additional Issues
Assume there are at least 15 more issues in the design system. Look for:
- Missing states or transitions
- Accessibility gaps (focus, contrast, screen readers)
- Responsive behavior (panel resize, collapse)
- Animation/transition timing specs
- Missing loading states
- Missing tooltip content
- Z-index stacking rules

## THE DESIGN SYSTEM

[Paste the FULL contents of plans/ui-ux/02-compete-gpt.md here]
