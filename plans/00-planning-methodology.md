# Planning Methodology: The Author's Actual Process

> Reverse-engineered from Jeffrey Emanuel's Agent Flywheel (https://agent-flywheel.com/complete-guide)
> and the CASS Memory System competing plans (https://github.com/Dicklesworthstone/cass_memory_system)
> Used across 10+ sessions spanning 7+ projects.

---

## The Rule: 85% Planning, 15% Implementation

> "You spend 85% of your time on planning. The first time you try it, feels wrong."

A 6,000-line plan fits trivially in a model's context window. The final codebase won't. This is when models are smartest — when they can see the whole system at once.

---

## The 9-Stage Process

```
Stage 1: Concept → Initial plan (GPT Pro Extended Reasoning)
Stage 2: Competing plans from 4 models independently
Stage 3: Best-of-all-worlds synthesis (GPT Pro)
Stage 4: Integration + critical assessment (Claude Code / Codex)
Stage 5: 4-5 fresh refinement rounds (new conversation each time)
Stage 6: Convergence check (is the plan stable?)
Stage 7: Plan → beads conversion (br tool)
Stage 8: 4-6 bead polishing rounds
Stage 9: Swarm launch
```

---

## Stage 1: Concept → Initial Plan

**Tool:** GPT Pro with Extended Reasoning
**Time:** ~1 hour
**Output:** `plans/01-initial-plan.md`

### What you actually type

You don't write a structured spec. You write messy, stream-of-consciousness:

> "I just usually start writing in a messy stream of thought way to convey the basic concept and then collaboratively work the agent to flesh it out in an initial draft."

You explain:
- What you want to build
- Why it matters
- How users will actually use it (workflows, not features)
- The tech stack

### Tech stack: decide BEFORE planning

> "You usually also specify the tech stack. For a web app, it's generally TypeScript, Next.js, React, Tailwind, Supabase. For a CLI tool, usually Go or Rust."

If the stack isn't obvious, do a research round first:
- Have GPT Pro or Gemini study relevant libraries
- Get a recommendation considering your goals
- Lock the stack, then start planning

### What makes the first version good

Move beyond vague abstractions. Instead of "build a notes app", specify actual workflows:

> "Users upload Markdown files through a drag-and-drop UI. The system parses frontmatter tags and stores upload failures for review."

The initial plan should cover:
- User-facing workflows (concrete, step-by-step)
- System architecture (components, how they connect)
- Data model (entities, relationships, key fields)
- Key technical decisions with reasoning

---

## Stage 2: Competing Plans from 4 Models

**Tool:** GPT Pro, Claude Opus, Gemini Deep Think, Grok Heavy
**Time:** ~30-60 min (running in parallel)
**Output:** `plans/02-compete-claude.md`, `plans/02-compete-gemini.md`, `plans/02-compete-grok.md`

### What you do

Give each model the SAME initial concept (NOT GPT Pro's plan). Let each design independently.

Each model produces a surprisingly different output:
- GPT Pro: Conversation → detailed spec with schemas
- Claude: Ready-to-ship package with code, AGENTS.md, README
- Grok: Analytical breakdown from multiple angles → code
- Gemini: Deep research on integration patterns

### What you see from the CASS project

The actual competing plans included:
- Full data model definitions (Sessions, Diary Entries, Playbooks)
- Pipeline overviews with step-by-step integration
- Concrete TypeScript/Bun code (600+ lines)
- JSON schemas, Zod types, file paths
- Package.json with real dependencies

These are NOT abstract design documents. They include implementation-ready code.

---

## Stage 3: Best-of-All-Worlds Synthesis

**Tool:** GPT Pro with Extended Reasoning
**Time:** ~30-60 min
**Output:** Updates to `plans/01-initial-plan.md`

### The exact prompt

Feed GPT Pro its OWN plan + ALL competing plans:

```
I want you to REALLY carefully analyze their plans with an open mind
and be intellectually honest about what they did that's better than
your plan. Then I want you to come up with the best possible revisions
to your plan that artfully and skillfully blends the 'best of all worlds'
to create a true, ultimate, superior hybrid version.

You should provide me with a complete series of git-diff style changes
to your original plan to turn it into the new, enhanced, much longer
and detailed plan.
```

### Why git-diff format matters

It prevents the model from writing vague summaries. Forces structural engagement with the competing plans' actual content. The model must point to specific lines and propose specific replacements.

---

## Stage 4: Integration + Critical Assessment

**Tool:** Claude Code or Codex
**Time:** ~30 min
**Output:** Updated `plans/01-initial-plan.md` (revisions applied in-place)

### The exact prompt

```
OK, now integrate these revisions to the markdown plan in-place;
use ultrathink and be meticulous. At the end, you can tell me which
changes you wholeheartedly agree with, which you somewhat agree with,
and which you disagree with.
```

### Why this matters

Claude critically assesses each suggestion from the synthesis. This is a second layer of quality filtering — not blind integration, but opinionated integration.

---

## Stage 5: Fresh Refinement Rounds (4-5 rounds)

**Tool:** GPT Pro, FRESH conversation each time
**Time:** ~30-60 min per round, ~2-3 hours total
**Output:** Progressively improved `plans/01-initial-plan.md`

### The key word: FRESH

> "Paste the current plan into a fresh GPT Pro conversation. Fresh conversations prevent the model from anchoring on its own prior output."

Each round is a new chat. No history. Just the current plan.

### The exact refinement prompt

```
Carefully review this entire plan for me and come up with your best
revisions in terms of better architecture, new features, changed features,
etc. to make it better, more robust/reliable.

For each proposed change, give me your detailed analysis and
rationale/justification for why it would make the project better
along with the git-diff style changes relative to the original
markdown plan.
```

### The "Lie to Them" technique

Models stop looking for problems after finding ~20-25 issues. When they say "that's everything":

```
Do this again, and actually be super super careful: can you please check
over the plan again and compare it to all that feedback I gave you?
I am positive that you missed or screwed up at least 80 elements of that
complex feedback.
```

This forces exhaustive searching instead of premature satisfaction.

### What happens each round

Each round discovers:
- Architectural issues invisible to previous passes
- Missing features
- Robustness improvements
- Edge cases
- Contradictions

After 4-5 rounds, suggestions become very incremental. That's convergence.

---

## Stage 6: Convergence Check

**Tool:** Your judgment
**Time:** 10 min

### When to STAY in plan refinement

- Whole-workflow questions are still moving around
- Major architecture debates are still open
- Fresh models keep discovering substantial features or constraints

### When to SWITCH to beads

- Plan mostly feels stable
- Remaining improvements are about execution structure, testing obligations, sequencing, and embedded context
- NOT about what the system fundamentally is

> "Stay in plan refinement if whole-workflow questions are still moving around. Switch to beads when the plan mostly feels stable."

---

## Stage 7: Plan → Beads Conversion

**Tool:** Claude Code or agent with br tool
**Time:** ~1 hour
**Output:** Beads in `.beads/`

### The exact prompt

```
OK so please take ALL of that and elaborate on it more and then create
a comprehensive and granular set of beads for all this with tasks,
subtasks, and dependency structure overlaid, with detailed comments
so that the whole thing is totally self-contained and self-documenting.

Use only the br tool to create and modify the beads and add the
dependencies. Use ultrathink.
```

### Critical rule

> "Never write pseudo-beads in markdown documents. Go directly from the markdown plan to actual real beads using the br tool."

---

## Stage 8: Bead Polishing (4-6 rounds)

**Tool:** Fresh agent sessions
**Time:** ~1-2 hours
**Output:** Refined beads

### What polishing looks like

Each round, a fresh agent reviews all beads and checks:
- Is each bead self-contained? (agent needs no other context)
- Are acceptance criteria testable?
- Are dependencies correctly mapped?
- Are there hidden dependencies?
- Is any bead too large? (split it)
- Is any bead too vague? (add detail)

> "Single-pass beads are never optimal."

---

## Stage 9: Swarm Launch

**Tool:** Multiple agents (2-4 to start, up to 25)
**Time:** Varies (CASS: 5 hours for 11,000 lines)

### Standard marching orders

```
Read ALL of AGENTS.md and README.md carefully.
Register with Agent Mail and introduce yourself to other agents.
Check Agent Mail regularly.
Work meticulously on assigned beads, tracking progress via messages.
Use bv to find your next task.
Use ultrathink mode.
```

### Scaling

- Start with 1 agent to learn the flow
- 2 agents to feel coordination benefits
- 4 agents for meaningful swarm behavior
- Stagger starts by 30+ seconds (avoid claim races)

---

## The Idea-Wizard Pipeline (for adding features to existing projects)

When you want to add capabilities to an existing project:

### Phase 1: Ground in reality
Read AGENTS.md and list all existing beads (`br list --json`). Prevents duplicates.

### Phase 2: Generate 30, winnow to 5

```
Come up with 30 ideas for improvements, enhancements, new features,
or fixes for this project. Then winnow to your VERY best 5 and explain
why each is valuable.
```

### Phase 3: Expand to 15

```
OK and your next best 10 and why.
```

> "Having agents brainstorm 30 then winnow to 5 produces much better results than asking for 5 directly because the winnowing forces critical evaluation."

### Phase 4: Human review
You pick which ideas to pursue from the 15 candidates.

### Phase 5: Turn into beads
Selected ideas become beads with descriptions, dependencies, and priority.

### Phase 6: Polish 4-5 times
Same polishing loop as Stage 8.

---

## Research-and-Reimagine Workflow (for major new capabilities)

When integrating ideas from external projects:

### Step 1: Investigate and propose

```
Clone https://github.com/<external-project> to tmp and then investigate it
and look for useful ideas that we can take and reimagine in highly accretive
ways on top of existing <your project> primitives. Write up a proposal document.
```

### Step 2: Push deeper

```
OK, that's a decent start, but you barely scratched the surface here.
You must go way deeper and think more profoundly and with more ambition
and boldness and come up with things that are legitimately 'radically innovative.'
```

### Step 3: Inversion analysis

```
What are things that we can do because we are starting with <your unique
primitives/capabilities> that <the external project> simply could never do
even if they wanted to because they are working from far less rich primitives?
```

### Step 4: Blunder hunt (run 5 times)

```
Look over everything in the proposal for blunders, mistakes, misconceptions,
logical flaws, errors of omission, oversights, sloppy thinking, etc.
```

Run this exact prompt 5 consecutive times.

### Step 5: Close design gaps

```
OK so then add this stuff to the proposal, using the very smartest ideas
from your alien skills to inform it and your best judgment based on the
very latest and smartest academic research.
```

### Step 6: Make self-contained for cross-model review

```
Add comprehensive background sections about what <your project> is and
how it works, what makes it special/compelling, etc.
```

### Step 7: Multi-model feedback
Send to GPT Pro, Claude Opus, Gemini Deep Think, Grok Heavy — request git-diff style changes.

### Step 8: Best-of-all-worlds synthesis
GPT Pro synthesizes all feedback.

### Step 9: Apply and de-slopify
Apply diffs, clean up final proposal.

---

## The Law of Rework Escalation

Why this process is worth 85% of your time:

| Layer | Fix cost | Example |
|-------|----------|---------|
| Plan | 1x | Change a paragraph in the plan |
| Bead | 5x | Rewrite task descriptions + fix dependency graph |
| Code | 25x | Rewrite implementation + fix tests + fix downstream |

> "Injecting identical mistakes at different layers produces escalating costs."

---

## Real Results

| Project | Plan lines | Beads | Agents | Code output | Time |
|---------|-----------|-------|--------|-------------|------|
| CASS Memory System | 5,500 | 347 | 25 | 11,000 lines, 204 commits | ~5 hours |
| GitHub Pages export | 3,500 | — | — | — | ~3 hours planning |
| jeffreysprompts.com | 6,000 | — | — | — | — |
