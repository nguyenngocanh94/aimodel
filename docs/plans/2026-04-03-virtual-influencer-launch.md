# Virtual Influencer Launch — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Execute the AI Virtual Influencer launch by working through sub-beads in dependency order, using the strategy document at `resources/virtual-influencer-launch-plan.md` as the north star.

**Architecture:** Each sub-bead produces a deliverable document or configuration in `resources/`. Skills (image-prompting, video-prompting, marketing-strategy) are already built. The work is strategic/creative, not code.

**Tech Stack:** Markdown documents, beads CLI for task tracking, existing skills for content generation.

---

### Task 1: Close AiModel-up0 (Launch Plan)

**Files:**
- Created: `resources/virtual-influencer-launch-plan.md`
- Created: `docs/plans/2026-04-03-virtual-influencer-launch.md`

**Step 1:** Verify launch plan document is complete and covers all sub-beads.

**Step 2:** Close bead.
```bash
bd close AiModel-up0 --reason="Launch strategy document created at resources/virtual-influencer-launch-plan.md"
```

**Step 3:** Commit.
```bash
git add resources/virtual-influencer-launch-plan.md docs/plans/2026-04-03-virtual-influencer-launch.md
git commit -m "feat: create AI virtual influencer launch strategy"
```

---

### Task 2: AiModel-2ut — Define Persona & Brand Identity

**Files:**
- Create: `resources/persona-profile.md`

**Step 1:** Use image-prompting skill to define Textual DNA from the direction in launch plan Section 3.

**Step 2:** Use marketing-strategy skill to validate persona against Vietnam Gen Z audience.

**Step 3:** Document: name, backstory, Textual DNA, visual style guide, personality traits, voice description.

**Step 4:** Close bead.
```bash
bd close AiModel-2ut
```

---

### Task 3: AiModel-cyn — Select Generative AI Toolset

**Files:**
- Create: `resources/toolset-selection.md`

**Depends on:** AiModel-2ut (persona informs which tools best render the character)

**Step 1:** Evaluate tools from launch plan Section 5 against the finalized persona.

**Step 2:** Document: chosen tools per category (image, video, voice, composition), rationale, cost estimates, API setup instructions.

**Step 3:** Close bead.
```bash
bd close AiModel-cyn
```

---

### Task 4: AiModel-onm — Setup Social Media Channels

**Files:**
- Create: `resources/social-media-setup.md`

**Depends on:** AiModel-2ut (branding comes from persona)

**Step 1:** Document channel setup plan per launch plan Section 6: account names, bios, profile pic specs, hashtag strategy.

**Step 2:** Note: actual account creation is manual (human task). This bead produces the specification.

**Step 3:** Close bead.
```bash
bd close AiModel-onm
```

---

### Task 5: AiModel-4g9 — Develop Content Strategy & Pilot Scripts

**Files:**
- Create: `resources/content-strategy.md`
- Create: `resources/pilot-scripts/week-1-script-01.md`
- Create: `resources/pilot-scripts/week-1-script-02.md`
- Create: `resources/pilot-scripts/week-1-script-03.md`

**Depends on:** AiModel-2ut + AiModel-cyn

**Step 1:** Use marketing-strategy skill to generate week 1 content calendar for Vietnam market.

**Step 2:** Write 3 pilot video scripts with full SAPELTC prompts per the video-prompting skill.

**Step 3:** Close bead.
```bash
bd close AiModel-4g9
```

---

### Task 6: AiModel-fh2 — Develop AI Agent Skills

**Files:**
- Extend: `skills/` directory

**Step 1:** Identify which workflow steps from launch plan Section 4 can be automated.

**Step 2:** Create additional skills or extend existing ones for the production pipeline.

**Step 3:** Close bead.
```bash
bd close AiModel-fh2
```
