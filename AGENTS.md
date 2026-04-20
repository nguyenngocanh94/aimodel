# AGENTS.md — AI Video Workflow Builder

> Read this ENTIRE file before doing anything else.

## What This Repository Does

This repo builds an **AI Video Workflow Builder** — a browser-based visual pipeline editor where developers drag-drop nodes onto a canvas, connect them, and compose AI video generation workflows. Each node is a discrete processing step (script generation, image creation, audio synthesis, video composition) with typed inputs/outputs visible in a Data Inspector.

**Key product principles:**
- Builder-first, not platform-first
- Local-first, single-user, no backend in v1
- Mock execution (no real AI provider calls in v1)
- Data Inspector is the core differentiator
- DAG-only, max ~15 nodes per workflow

### Telegram Assistant (Laravel `backend/`)

The Telegram-facing **Assistant** routes user messages to catalog workflows via `laravel/ai`. Its instructions are built from composable **BehaviorSkills** in `backend/app/Services/TelegramAgent/BehaviorSkills/` (prompt guardrails, not tools). To change tone or guardrails, edit an existing `*BehaviorSkill` class or add a new one implementing `BehaviorSkill` and list it in `backend/config/telegram_agent.php` under `behavior_skills` (order matters). Tool capsules (progressive-disclosure `Skill`s for the sdk-skills package) live in `resources/skills/` — do not confuse the two.

## Tech Stack

- **React** + **Vite** + **TypeScript** (strict mode)
- **@xyflow/react** (React Flow) for the canvas
- **Tailwind CSS** + **shadcn/ui** for styling
- **Zustand** for state (2 stores: workflow + run)
- **Dexie** on IndexedDB for persistence
- **Zod** for runtime schemas and config validation
- **React Hook Form** for inspector forms
- **Vitest** + **React Testing Library** + **Playwright** for testing

## Project Structure

```
plans/                          # Planning documents (do NOT modify)
plans/06-final-plan.md          # THE authoritative spec (3,613 lines)
resources/                      # Implementation goes here
src/                            # Source code (created during implementation)
  app/                          # App shell, providers, routes
  features/
    canvas/                     # React Flow canvas, node cards, edges
    node-library/               # Sidebar with draggable nodes
    inspector/                  # Node config inspector
    data-inspector/             # Payload viewer, schema diff, lineage
    workflow/                   # Zustand workflow store, commands
    execution/                  # Run store, planner, mock executor, cache
    workflows/                  # Persistence, migrations, recovery
    node-registry/              # Node templates + fixtures
    templates/                  # Built-in workflow templates
  shared/                       # Shared UI components, utilities
```

## Non-Negotiable Rules

1. **Read the plan first.** Before implementing ANY bead, read the relevant section of `plans/06-final-plan.md`. The plan is authoritative.
2. **No real AI providers in v1.** Do not add OpenAI, Runway, ElevenLabs, or any external API calls. Mock execution only.
3. **No backend.** V1 is a static SPA. No server, no auth, no credentials.
4. **All types are readonly/immutable.** Use `readonly` on all interfaces per the plan.
5. **Every node template must have:** configSchema (Zod), buildPreview, fixtures, and mockExecute (if executable).
6. **Two Zustand stores.** Never mix workflow state and run state. Undo/redo applies only to workflow store.
7. **Test what matters.** Unit test: validation, execution, compatibility, migrations. E2E test: core user journeys. Don't chase vanity coverage.
8. **Do not modify planning documents.** Files in `plans/` are final. If you find a plan issue, note it in a comment on the bead.
9. **Never ask — just do.** Do not ask the user what to work on, whether to commit, or how to proceed. Run `bd ready`, pick the highest-priority ready bead, claim it, and start coding immediately. The only time to ask is when the plan is genuinely ambiguous and no reasonable default exists. Presenting a numbered menu of options is never acceptable.
10. **Targeted tests only.** NEVER run the full test suite. Only run tests for the files you changed:
    ```bash
    # CORRECT — run only your feature's tests
    npx vitest run src/features/node-registry/templates/script-writer.test.ts
    npx vitest run src/features/workflows/domain/

    # WRONG — runs everything, hangs, eats RAM
    npx vitest
    npm test
    npx vitest run  # full suite — still too broad
    ```
    Always use `npx vitest run <specific-path>`. The test must exit in under 10 seconds. If it hangs, you have a bug.
11. **Test lock protocol.** Use a file lock to prevent concurrent test runs:
    ```bash
    # Before testing: acquire lock
    if [ -f .test.lock ]; then
      echo "Another agent is testing, waiting..."
      while [ -f .test.lock ]; do sleep 2; done
    fi
    echo "$$" > .test.lock

    # Run your scoped test
    npx vitest run src/features/your-feature/

    # After testing: release lock
    rm -f .test.lock
    ```
    If `.test.lock` exists, wait. If it's stale (older than 60s), delete it and proceed.
    ALWAYS remove the lock after testing, even if the test fails.

## At Session Start

Do this immediately — no preamble, no asking for direction:

1. Read this entire file
2. Run `git status` — if there are uncommitted changes from a previous session, commit them first
3. Run `bd ready` — pick the highest-priority ready bead
4. Run `bd show <id>` to read the bead, then start coding

**Do not present options. Do not ask what to work on. The graph decides.**

## How to Find Work

Use `bd` (beads) to find and claim tasks:

```bash
# See what's ready to work on (no blockers)
bd ready

# Get details on a specific bead
bd show <bead-id>

# Claim a bead
bd update <bead-id> --status=in_progress

# When done
bd close <bead-id>

# See the full dependency graph
bv
```

**Always use `bd ready` or `bv` to pick your next task.** Do not pick randomly or by convenience. The dependency graph exists for a reason.

## How to Use Agent Mail

Agent Mail coordinates multi-agent work via MCP tools. The server runs at `http://127.0.0.1:8765/mcp/`.

### At Session Start

Register yourself (if not already registered). Use the MCP tool `register_agent`:
```json
{"project_key": "AiModel", "program": "claude-code", "model": "your-model", "name": "YourName", "task_description": "what you're working on"}
```

Check your inbox with `check_inbox` or search messages with `search_messages`.

### When Starting a Bead

Announce what you're working on by sending a message to all other agents:
```json
{"project_key": "AiModel", "sender_name": "YourName", "to": ["all-agents"], "subject": "Claiming AiModel-xxx: task name", "body_md": "Starting work on this bead. Files I'll touch: src/features/..."}
```

### Reserve Files Before Editing

Before editing shared files, reserve them with `file_reservation_paths`:
```json
{"project_key": "AiModel", "agent_name": "YourName", "paths": ["src/features/workflows/domain/workflow-types.ts"], "mode": "exclusive"}
```

Release when done with `release_file_reservations`.

### When Completing a Bead

Send a completion message so other agents know the dependency is satisfied:
```json
{"project_key": "AiModel", "sender_name": "YourName", "to": ["all-agents"], "subject": "Completed AiModel-xxx", "body_md": "Done. Key files created: ... Other agents can now use these exports."}
```

### Registered Agents

| Name | Program | Role |
|------|---------|------|
| Claude1 | claude-code | Implementation |
| OpenCode1 | opencode | Implementation |

## How to Work on a Bead

1. **Claim it:** `bd update <id> --status=in_progress`
2. **Read the plan:** Open `plans/06-final-plan.md` and find the section referenced in the bead description
3. **Implement:** Write code in the correct `src/` path as specified in the bead
4. **Test:** Write tests alongside implementation. Run `npx vitest run` (single run, NOT watch mode)
5. **Quality gate:** Run `npm run quality-gate` (typecheck + lint + tests). Fix all errors before proceeding.
6. **Self-review (Fresh Eyes):** Re-read all new and modified code adversarially. Ask:
   - **Correctness:** Does implementation match the bead description and acceptance criteria?
   - **Edge cases:** Empty inputs, concurrent access, error paths, boundary conditions?
   - **Pattern matching:** If you find a bug, search for identical patterns elsewhere in the codebase.
   - **Alternatives:** Is there a simpler or more robust approach?
   Run review rounds until no additional issues surface (typically 1–2 rounds for simple beads, 2–3 for complex).
7. **Verify acceptance criteria:** Check every item in the bead's acceptance list
8. **Close:** `bd close <id>`
9. **Pick next and start immediately:** Re-read this AGENTS.md, run `bd ready`, claim the highest-priority bead, and begin coding. Do not ask the user what to do next — the dependency graph decides.

## Quality Gates

Before every commit, agents must pass:

```bash
npm run quality-gate    # runs: typecheck → lint → tests
```

Individual checks:
- `npm run typecheck` — `tsc --noEmit` (no `any`, no type errors)
- `npm run lint` — ESLint with `--max-warnings 0` (zero warnings policy)
- `npm test -- --run` — Vitest in single-run mode

If any check fails, fix the issue before committing. Do not skip or disable checks.

## Cross-Agent Review Protocol

After completing a milestone or every 30–60 minutes of active implementation, one agent should perform a cross-codebase review:

1. **Random exploration:** Open code files you did not write. Trace functionality through imports. Look for integration bugs (incorrect argument ordering, missing error handling, type mismatches at boundaries).
2. **Fresh eyes prompt:** Re-read modified files as if seeing them for the first time. Fix obvious bugs, errors, and problems while complying with all AGENTS.md rules.
3. **Convergence signal:** When two consecutive review rounds return clean with zero changes, the codebase passes quality gates for that milestone.

Do not halt all agents for review. Designate 1–2 agents finishing beads for review while others continue implementing.

## Coding Standards

- **TypeScript strict mode.** No `any`, no `@ts-ignore`.
- **Feature-oriented file structure.** Code goes in `src/features/<domain>/`.
- **Named exports only.** No default exports.
- **Zod for all runtime validation.** Config schemas, import validation, etc.
- **shadcn/ui for UI primitives.** Button, Dialog, Tabs, Badge — use shadcn, don't reinvent.
- **Tailwind for styling.** No CSS modules, no styled-components.
- **Small, focused commits.** One bead = one commit (or a few).

## Node Schema Authority

**Node schemas are backend-authoritative.** `GET /api/nodes/manifest` is the source of truth for ports, config rules, and defaults. Frontend templates own only `mockExecute`, `buildPreview`, `fixtures`, and visual hints — never re-author `configSchema` or `defaultConfig` in TS. To add a new config field to a node: edit `configRules()` on the PHP template, done. The inspector picks it up automatically via the manifest. See `docs/plans/2026-04-18-node-manifest-alignment.md`.

## Milestone Order

Work proceeds through 6 milestones in sequence:

1. **Document Model & Registry** — Types, node templates, registry (Epic: AiModel-9wx)
2. **Canvas & Authoring Shell** — React Flow, Zustand, commands (Epic: AiModel-k6g)
3. **Validation & Preview** — Graph validator, preview engine, data inspector (Epic: AiModel-1n1)
4. **Mock Execution** — Run planner, executor, cache, toolbar (Epic: AiModel-ecs)
5. **Persistence & Recovery** — Dexie, boot, import/export, crash recovery (Epic: AiModel-e0x)
6. **Templates & Polish** — Built-in templates, shortcuts, a11y (Epic: AiModel-537)

Each milestone's tasks have explicit dependencies. Trust the graph.

## When You're Stuck

- Re-read the relevant plan section
- Check if a dependency bead has context you need
- Ask via Agent Mail if another agent has worked on related code
- If the plan is ambiguous, make a reasonable decision and note it in a comment on the bead

<!-- bv-agent-instructions-v1 -->

---

## Beads Workflow Integration

This project uses [beads_viewer](https://github.com/Dicklesworthstone/beads_viewer) for issue tracking. Issues are stored in `.beads/` and tracked in git.

### Essential Commands

```bash
# View issues (launches TUI - avoid in automated sessions)
bv

# CLI commands for agents (use these instead)
bd ready              # Show issues ready to work (no blockers)
bd list --status=open # All open issues
bd show <id>          # Full issue details with dependencies
bd create --title="..." --type=task --priority=2
bd update <id> --status=in_progress
bd close <id> --reason="Completed"
bd close <id1> <id2>  # Close multiple issues at once
bd sync               # Commit and push changes
```

### Workflow Pattern

1. **Start**: Run `bd ready` to find actionable work
2. **Claim**: Use `bd update <id> --status=in_progress`
3. **Work**: Implement the task
4. **Complete**: Use `bd close <id>`
5. **Sync**: Always run `bd sync` at session end

### Key Concepts

- **Dependencies**: Issues can block other issues. `bd ready` shows only unblocked work.
- **Priority**: P0=critical, P1=high, P2=medium, P3=low, P4=backlog (use numbers, not words)
- **Types**: task, bug, feature, epic, question, docs
- **Blocking**: `bd dep add <issue> <depends-on>` to add dependencies

### Session Protocol

**Before ending any session, run this checklist:**

```bash
git status              # Check what changed
git add <files>         # Stage code changes
bd sync                 # Commit beads changes
git commit -m "..."     # Commit code
bd sync                 # Commit any new beads changes
git push                # Push to remote
```

### Best Practices

- Check `bd ready` at session start to find available work
- Update status as you work (in_progress → closed)
- Create new issues with `bd create` when you discover tasks
- Use descriptive titles and set appropriate priority/type
- Always `bd sync` before ending session

<!-- end-bv-agent-instructions -->

## Landing the Plane (Session Completion)

**When ending a work session**, you MUST complete ALL steps below. Work is NOT complete until `git push` succeeds.

**MANDATORY WORKFLOW:**

1. **File issues for remaining work** - Create issues for anything that needs follow-up
2. **Run quality gates** (if code changed) - Tests, linters, builds
3. **Update issue status** - Close finished work, update in-progress items
4. **PUSH TO REMOTE** - This is MANDATORY:
   ```bash
   git pull --rebase
   bd sync
   git push
   git status  # MUST show "up to date with origin"
   ```
5. **Clean up** - Clear stashes, prune remote branches
6. **Verify** - All changes committed AND pushed
7. **Hand off** - Provide context for next session

**CRITICAL RULES:**
- Work is NOT complete until `git push` succeeds
- NEVER stop before pushing - that leaves work stranded locally
- NEVER say "ready to push when you are" - YOU must push
- If push fails, resolve and retry until it succeeds
