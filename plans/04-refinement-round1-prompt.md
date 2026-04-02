# Refinement Round 1 Prompt for GPT Pro

> Fresh conversation. Extended Reasoning ON. Paste everything below the line.

---

Below is a design plan for an AI Video Workflow Builder. It has been through multi-model synthesis and is ~80% complete. A senior engineer reviewed it and identified specific gaps that need filling.

Your job: carefully review the entire plan, then provide revisions in git-diff style for each gap listed below. After the diffs, provide any ADDITIONAL issues you find that aren't in my list.

For each proposed change, give detailed analysis and rationale along with the git-diff style changes relative to the original plan.

## SPECIFIC GAPS TO FIX

### Gap 1: Execution Engine Implementation Detail
The plan has excellent TypeScript types for `RunPlanner`, `MockExecutor`, `RunCache` but ZERO implementation code. Add:
- Topological sort implementation (Kahn's algorithm)
- Subgraph extraction for "run from here" / "run up to here"
- The main `MockExecutor.execute()` loop with AbortController wiring
- How `RunPlanner` determines which nodes to run for each trigger type
- Error propagation: what happens to downstream nodes when one fails

### Gap 2: Dexie/IndexedDB Schema
The plan lists table names but no actual Dexie code. Add:
- Complete Dexie database class definition
- Table schemas with indexes
- Version migration strategy (concrete code, not just description)
- How the app handles IndexedDB unavailability or quota exceeded

### Gap 3: App Boot Sequence
`app.tsx`, `providers.tsx`, `routes.tsx` are listed but never specified. Add:
- What providers wrap the app (Dexie, Zustand, React Flow)
- The boot sequence: check IndexedDB → detect recovery snapshot → show recovery dialog OR load last workflow OR show empty state
- How the initial render works
- Route structure (is it single-route? multi-route?)

### Gap 4: FFmpeg.wasm / Video Preview Strategy
`videoComposer` says "produces a mock composed asset descriptor" but never addresses what the user actually SEES. Add:
- What does a "mock video" look like in the preview? (static image? animated sequence? metadata-only?)
- V2 strategy: FFmpeg.wasm vs server-side vs Canvas API + MediaRecorder
- How large is FFmpeg.wasm (~25MB)? When is it loaded? Dynamic import?
- Memory budget for video assets in browser

### Gap 5: Edge Insertion UX
Journey 2 says "click quick action to insert Image Asset Mapper between two nodes, reconnects edges automatically." Add:
- How auto-reconnection works (port matching algorithm)
- What happens when ports don't match exactly
- Is this a first-class command in the store? What does the undo look like?

### Gap 6: Clean Up Structure
The plan currently starts with 230 lines of comparison notes (Plan A vs B vs C vs D). These are useful context but don't belong in the final spec. The plan should start at "Product Thesis." Remove the comparison section or move it to an appendix.

## ALSO: Find What I Missed

After fixing the above gaps, do your own thorough review. Use the "lie to them" technique on yourself — assume there are at least 30 more issues I didn't list. Look for:
- Contradictions within the plan
- Underspecified interactions
- Missing error states
- Unclear ownership between components
- Testing gaps
- Accessibility gaps
- Performance concerns
- Any section that says "should" without saying "how"

For each issue found, provide the same git-diff treatment.

## THE PLAN

[Paste the FULL contents of plans/03-synthesized-plan.md here, starting from "# Hybrid AI Video Builder Revision" through the end]
