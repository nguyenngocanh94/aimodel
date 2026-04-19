# Conversational workflow composition

> **For Claude:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Goal:** Turn the existing `WorkflowPlanner` into a first-class Assistant capability: the user says *"tạo workflow sinh video TVC chuyên nghiệp 9:16 cho sản phẩm chăm sóc sức khỏe"* on Telegram, the bot drafts a plan, explains it in Vietnamese, accepts user refinements through chat ("thêm humor nhẹ", "đổi vibe aesthetic"), and only persists + enables the workflow on explicit approval. No tinker, no artisan command — a conversation.

**Why this matters:** the planner (epic 645.4) and the evaluator (645.5) are already built and tested. Today they're unreachable from the bot — `ComposeWorkflowTool` is a stub that returns *"not available, aimodel-645 pending"*. This epic flips that stub into a real seam.

**UX target (the flow we're building toward):**

```
User:  tạo workflow sinh video TVC 9:16 chăm sóc sức khỏe
Bot:   Mình đề xuất workflow này:
       • vibe: clean_education
       • nodes: productAnalyzer → storyWriter → sceneSplitter →
                promptRefiner → imageGenerator → videoComposer → finalExport
       • knobs: humor_density=none, product_emphasis=hero_moment,
                cta_softness=medium, edit_pace=steady
       • lý do: sức khỏe cần tin cậy, hero_moment tăng tín nhiệm ...
       OK để mình lưu không? (trả lời ok / chỉnh / hủy)

User:  chỉnh: thêm humor nhẹ, đừng khô khan
Bot:   Cập nhật: humor_density=punchline_only, emotional_tone=relatable_humor.
       Phần còn lại giữ nguyên. OK chưa?

User:  ok
Bot:   ✅ Đã lưu workflow health-tvc-9x16. Gõ /list để xem hoặc "chạy
       health-tvc-9x16 cho <sản phẩm>" để dùng.
```

**Architecture:**
- Three new tools: `ComposeWorkflowTool` (real), `RefinePlanTool`, `PersistWorkflowTool`.
- One new skill: `ComposeWorkflowSkill` — tells the model how to sequence the four tools.
- `AgentSession` gains `pendingPlan` and `pendingPlanAttempts` fields, persisted through `RemembersConversations`.
- The planner + evaluator (already built) are DI-resolved inside the tools. No new LLM provider work.

**Tech Stack:** PHP 8.4, Laravel 11, `laravel/ai v0.6.0`, existing `WorkflowPlanner` / `WorkflowPlanToDocumentConverter` / `WorkflowPlanValidator`. PHPUnit 12. No frontend work.

**Non-goals for this epic:**
- Actually running the generated workflow to produce video output (blocked by unimplemented nodes — `casting`, `shotCompiler`, `moodSequencer`, real `imageGenerator`, real `videoComposer` provider wiring).
- Plan versioning / history (each conversation turn overwrites `pendingPlan`).
- Multi-user plan collaboration (scoped per-chat).
- Cost / rate limiting (~5K tokens per plan attempt, ~$0.003; monitor only).

---

## CW1 — Session state + real `ComposeWorkflowTool` + `ComposeWorkflowSkill`

**Files:**
- Edit: `backend/app/Services/TelegramAgent/AgentSession.php` — add `pendingPlan` and `pendingPlanAttempts`.
- Edit: `backend/app/Services/TelegramAgent/RedisConversationStore.php` — persist the new fields (they flow through the `RemembersConversations` trait's default serializer; verify with a round-trip test).
- **Replace body** (not signature): `backend/app/Services/TelegramAgent/Tools/ComposeWorkflowTool.php`.
- Create: `backend/app/Services/TelegramAgent/Skills/ComposeWorkflowSkill.php`.
- Edit: `backend/config/telegram_agent.php` — prepend the new skill (place after `RouteOrRefuseSkill` so it gets the "must use tools" scaffold already).
- Tests:
  - `backend/tests/Unit/Services/TelegramAgent/AgentSessionPendingPlanTest.php`
  - `backend/tests/Unit/Services/TelegramAgent/Tools/ComposeWorkflowToolTest.php` (rewrite)
  - `backend/tests/Unit/Services/TelegramAgent/Skills/ComposeWorkflowSkillTest.php`

**`AgentSession` changes:**
```php
public ?array $pendingPlan = null;           // serialized WorkflowPlan (via ->toArray-like shape)
public int   $pendingPlanAttempts = 0;       // how many refine rounds so far
```
Update the serializer/hydrator on the session (or `RedisConversationStore` if it owns serialization) so these survive round-trip.

**`ComposeWorkflowTool` (real implementation):**
- Constructor gains `WorkflowPlanner $planner` and `AgentSessionStore $sessionStore` via DI.
- Description: *"Draft a new workflow from a user brief. Returns a plan (nodes, edges, knobs, rationale) WITHOUT persisting. Follow with `reply` to explain the plan to the user and ask for approval."*
- Input schema: `brief` (string, required, min 10 chars).
- `handle(Request $request)`:
  1. Call `$this->planner->plan(new PlannerInput(brief: $request->string('brief')))`.
  2. If `!$result->successful()` — return `{"available": false, "reason": "<top validation error>"}`.
  3. Serialize `$result->plan` to an array (use `toArray()` if it exists; otherwise manually — see `WorkflowPlan` source).
  4. Load session → set `$session->pendingPlan = <serialized>` + `$session->pendingPlanAttempts = 1` → save.
  5. Return a compact summary JSON: `{"available": true, "vibeMode", "nodes": [{type, reason (truncated)}], "knobs": [{node, name, value}], "rationale": "..."}`.

**`ComposeWorkflowSkill`:**
Vietnamese-first prompt fragment that explains to the model:
- When user says "tạo workflow" / "xây pipeline" / "tạo flow mới" / "compose workflow" → call `ComposeWorkflowTool({brief: <user's full request>})`.
- **Immediately after `ComposeWorkflowTool` returns**, call `reply` with a Vietnamese explanation of the plan: bullet-list of nodes, key knobs, vibe, short rationale, then "OK để mình lưu không? (ok / chỉnh / hủy)".
- NEVER call `persist_workflow` without explicit user approval.
- If the tool returns `available: false`, reply with the reason + suggest alternatives (listing existing catalog slugs).

Include a one-shot example in the skill text: a 40-line transcript showing the compose → explain → refine → persist chain.

**Tests for CW1:**
- `AgentSessionPendingPlanTest` — round-trip a session with `pendingPlan` populated through the Redis store; assert fields survive.
- `ComposeWorkflowToolTest` (rewrite) —
  - Happy path: `Http::fake` Fireworks with a canned plan response → tool returns `available: true` + session has `pendingPlan` set. Use `Laravel\Ai\AnonymousAgent` mocking via the same pattern LA5 used.
  - Unsuccessful planner: fake Fireworks with junk JSON → tool returns `available: false`.
  - Session write: after call, load the session from Redis and assert `pendingPlan` is a non-empty array.
- `ComposeWorkflowSkillTest` (3 cases) —
  - Prompt fragment contains "tạo workflow" trigger phrases.
  - Prompt fragment contains explicit "NEVER persist without approval".
  - One-shot example is present and parses as expected (contains 4 role transitions).

**Acceptance:**
- `docker exec backend-app-1 php artisan test --filter="AgentSessionPendingPlanTest|ComposeWorkflowToolTest|ComposeWorkflowSkillTest"` green.
- No regressions in existing agent tests (`TelegramAgentTest`, `RunWorkflowToolTest`, etc.).

---

## CW2 — `RefinePlanTool`

**Files:**
- Create: `backend/app/Services/TelegramAgent/Tools/RefinePlanTool.php`.
- Create: `backend/app/Services/TelegramAgent/Tools/RefinePlanPrompt.php` — composes the re-plan prompt from the prior plan + feedback + node catalog.
- Edit: `backend/app/Services/TelegramAgent/TelegramAgent.php` — register in `tools()`.
- Tests: `backend/tests/Unit/Services/TelegramAgent/Tools/RefinePlanToolTest.php`.

**`RefinePlanTool`:**
- Description: *"Refine the pending workflow plan based on user feedback. Reads the prior plan from session, re-invokes the planner with the feedback, stores the updated plan back. Call ONLY when the user asks for changes AND there's a pending plan."*
- Input schema: `feedback` (string, required, min 3 chars).
- `handle(Request $request)`:
  1. Load session. If `$session->pendingPlan === null` → return `{"error": "no_pending_plan", "message": "Chưa có plan nào để chỉnh — hãy tạo workflow mới trước."}`.
  2. Build re-plan prompt: system prompt = catalog + the rules used in `ComposeWorkflowTool` + "Đây là plan hiện tại: `<json>`. User feedback: `<feedback>`. Emit an updated plan in the same JSON schema. Only change what the user asked for; preserve the rest."
  3. Call `Laravel\Ai\AnonymousAgent->prompt(...)`.
  4. Parse JSON (reuse the lenient parser from `WorkflowPlanner`).
  5. Validate via `WorkflowPlanValidator`. On failure, return `{"error": "validation_failed", "errors": [...]}` — skill instructs the model to explain this and offer another refinement.
  6. On success, update `$session->pendingPlan` + `$session->pendingPlanAttempts++`, save, return summary JSON (same shape as CW1).

**Refinement strategy decision:** full re-plan, not diff. Cheaper to reason about, more robust. ~5K tokens per refinement; the skill limits refinements to 5 per session.

**Tests (5 cases):**
- Happy path: prior plan present, fake Fireworks with an updated plan, session updated correctly.
- No pending plan: tool returns `no_pending_plan` error.
- Validator rejects the refined plan: tool returns `validation_failed`.
- Session-level cap: on the 6th refinement call, tool returns `refinement_cap_reached` and instructs the user to approve / cancel / start over.
- Prior-plan payload is correctly inlined into the re-plan prompt (use `Http::recorded()` to inspect the request body — assert it contains the prior `vibeMode`).

**Acceptance:**
- `--filter=RefinePlanToolTest` green.
- Integration: running CW1's test + CW2's test in sequence exercises the full propose→refine chain.

---

## CW3 — `PersistWorkflowTool`

**Files:**
- Create: `backend/app/Services/TelegramAgent/Tools/PersistWorkflowTool.php`.
- Edit: `TelegramAgent::tools()` — register it.
- Tests: `backend/tests/Unit/Services/TelegramAgent/Tools/PersistWorkflowToolTest.php`.

**`PersistWorkflowTool`:**
- Description: *"Persist the pending plan as a triggerable workflow. Call ONLY when the user has explicitly approved (e.g. said 'ok', 'được', 'chốt', 'đồng ý'). Do NOT call without approval."*
- Input schema: `slug` (string required, kebab-case; model proposes), `name` (string required), `description` (string optional — human-readable summary for catalog).
- `handle(Request $request)`:
  1. Load session. If `$session->pendingPlan === null` → `{"error": "no_pending_plan"}`.
  2. Slug collision handling:
     - Query `Workflow::where('slug', $slug)->first()`.
     - If exists and the existing row is also planner-generated (tag `"planner"`), append `-v2`, `-v3`, etc. until unique.
     - If exists and it's a non-planner workflow, return `{"error": "slug_reserved", "suggestion": "<slug>-v2"}` so the skill asks the user to pick a different slug.
  3. Hydrate `WorkflowPlan` from the serialized session blob (write `WorkflowPlan::fromArray` if it doesn't exist yet — single static factory, trivial).
  4. `$document = app(WorkflowPlanToDocumentConverter::class)->convert($plan);`
  5. Create `Workflow`:
     ```php
     Workflow::create([
         'name'           => $name,
         'slug'           => $slug,
         'triggerable'    => true,
         'schema_version' => 1,
         'nl_description' => $description ?: "Auto-generated from brief: {$plan->intent}",
         'param_schema'   => ['productBrief' => ['required','string','min:5']],
         'document'       => $document,
         'tags'           => ['planner', 'v1', $plan->vibeMode],
     ]);
     ```
  6. Clear `$session->pendingPlan` + reset `pendingPlanAttempts`, save.
  7. Return `{"workflowId": $wf->id, "slug": $wf->slug, "name": $wf->name, "triggerable": true}`.

**Tests (6 cases):**
- Happy path: session has a valid plan → tool creates the row with right fields, session cleared.
- No pending plan: returns `no_pending_plan`.
- Slug collision with existing non-planner workflow: returns `slug_reserved` with suggestion.
- Slug collision with prior planner row: appends `-v2` automatically.
- `param_schema` defaults to `productBrief` required string — not every plan has explicit params today.
- After successful persist, `ListWorkflowsTool` (the existing one) returns the new workflow.

**Acceptance:**
- `--filter=PersistWorkflowToolTest` green.
- `Workflow::triggerable()->where('slug', 'test-slug')` returns the created row after the happy-path test.

---

## CW4 — Approval heuristic + skill polish + `SystemPrompt` wiring

**Files:**
- Edit: `backend/app/Services/TelegramAgent/Skills/ComposeWorkflowSkill.php` — expand with approval vocabulary + refinement guidance.
- Edit: `backend/app/Services/TelegramAgent/SystemPrompt.php` or `SkillComposer` — no functional change expected, but verify the new skill slots in cleanly after `RouteOrRefuseSkill`.
- Edit: `backend/config/telegram_agent.php` — confirm ordering.
- Optional: `backend/app/Services/TelegramAgent/Skills/ComposeVocabularySkill.php` if the approval words deserve their own skill (probably not — keep in `ComposeWorkflowSkill`).
- Tests: extend `ComposeWorkflowSkillTest` with 4 more cases covering the full approval/refinement/rejection vocabulary.

**Prompt content additions:**

*Approval words* (positive): `ok`, `oki`, `oke`, `đồng ý`, `được`, `chốt`, `tiếp`, `làm đi`, `go`, `yes`, `✅`.
*Refinement words*: `chỉnh`, `đổi`, `thay`, `khác`, `sửa`, `thêm`, `bớt`, `lại`, `retry`, `update`.
*Rejection words*: `hủy`, `thôi`, `không`, `dừng`, `bỏ`, `cancel`, `no`.

Skill instructs:
- On approval → call `PersistWorkflowTool` with the slug + name the skill proposes (or the user specifies).
- On refinement → call `RefinePlanTool` with the user's exact feedback string.
- On rejection → call `reply` acknowledging, then clear pendingPlan via a new no-op call (or just let it expire with the session).
- On anything ambiguous → call `reply` asking for clarification before picking a path.

**Tests:**
- Skill prompt contains all three vocabulary lists.
- Skill prompt contains the "NEVER persist without approval" rule.
- Skill prompt shows a concrete slug-generation example (`slug: kebab-case based on brief's topic`).
- Skill prompt contains the refinement cap warning (max 5 refinements per session).

**Acceptance:**
- `--filter=ComposeWorkflowSkillTest` green.
- Manual smoke sketch (document, don't run): scripted Vietnamese turns → expected tool calls → expected replies.

---

## CW5 — End-to-end feature test

**Files:**
- Create: `backend/tests/Feature/TelegramAgent/ConversationalCompositionTest.php`.

**Scenarios (4 cases):**

1. **Happy path — 3-turn composition.**
   - Turn 1: user says *"tạo workflow sinh video TVC 9:16 cho sản phẩm chăm sóc sức khỏe"*. Canned LLM responses: Fireworks returns a plan (matching the cocoon-direct-intro fixture shape adapted for health), then a reply tool call explaining the plan.
   - Assert: `ComposeWorkflowTool` called once; session's `pendingPlan` populated; Telegram `sendMessage` called once with a Vietnamese plan summary.
   - Turn 2: user says *"ok"*. Canned LLM response: `PersistWorkflowTool(slug=health-tvc-9x16, name="Health TVC 9:16")`.
   - Assert: `Workflow::where('slug', 'health-tvc-9x16')->count() === 1`, `triggerable === true`, `document` non-empty, session's `pendingPlan` cleared.
   - Turn 3 (optional): user sends `/list`. Assert the new slug appears in the reply.

2. **Refinement path — 4-turn composition.**
   - Turn 1: same brief → plan drafted.
   - Turn 2: user says *"chỉnh: thêm humor nhẹ"*. Canned LLM: `RefinePlanTool(feedback="thêm humor nhẹ")` with fake Fireworks returning an updated plan.
   - Assert: `pendingPlanAttempts === 2`, `pendingPlan.knobs` now contains `humor_density=punchline_only`.
   - Turn 3: user says *"ok"*. Assert persistence as before.

3. **Refinement cap — after 5 refinements, tool refuses.**
   - Loop 5 refinements + 6th → `RefinePlanTool` returns `refinement_cap_reached`.

4. **Rejection — user says "hủy"** after plan drafted. Assert `pendingPlan` cleared, no `Workflow` row created, Telegram reply acknowledges cancellation.

**Acceptance:**
- 4/4 green.
- `Queue::fake()` verifies no stray `RunWorkflowJob` dispatched (persist only creates the row, doesn't auto-run).
- Runs in < 3s (all Fireworks/Telegram faked).

---

## CW6 — Live smoke + epic close

**Files:**
- Edit: `docs/plans/2026-04-19-conversational-workflow-composition.md` — append "Done — results" footer with commit hashes.
- Edit: `backend/AGENTS.md` — short note under Tech Stack describing the compose → refine → persist flow.
- Optional: disposable smoke script reproducing the user's exact Vietnamese message against live Fireworks.

**Steps:**
1. `docker exec backend-app-1 composer dump-autoload -o` + `artisan config:clear` + `docker restart backend-worker-1 backend-app-1`.
2. Full regression: `docker exec backend-app-1 vendor/bin/phpunit --testdox --filter="Conversational|ComposeWorkflow|RefinePlan|PersistWorkflow|AgentSessionPendingPlan|TelegramAgentTest"`.
3. Live smoke: disposable `storage/app/cw_smoke.php` that runs the 2-turn happy path (tạo → ok) against real Fireworks with Telegram faked. Assert `Workflow::where('slug', ...)->exists()`. Delete the script after.
4. Update `aimodel.lovableai.space` webhook stays the same — the bind-mount makes the merge instantly live; no redeploy needed.
5. Close `aimodel-q1hp` epic? No — that's LG, unrelated. Close this epic: `bd close <cw-epic-id> --reason="Planner is now a conversational Assistant skill: tạo → draft → refine → approve → persist."`

**Acceptance:**
- All regression tests green.
- Live smoke against real Fireworks completes in < 15s with a persisted `Workflow` row.
- Telegram webhook still working for non-compose paths.
- Epic closed.

---

## Dependency order

```
CW1 (session + tool + skill) ─┬─► CW2 (RefinePlanTool)   ─┐
                              │                            │
                              └─► CW3 (PersistWorkflow)   ─┤
                                                           │
                                          CW4 (skill polish) ─► CW5 (feature test) ─► CW6 (smoke + close)
```

CW1 is strict prerequisite — defines the session shape every later task depends on.
CW2 + CW3 can run in parallel after CW1 lands.
CW4 polishes the skill prompt using all three tools.
CW5 tests the full conversation.
CW6 closes.

## Relationship to other epics

- **Builds on `aimodel-645` (closed)** — uses `WorkflowPlanner`, `WorkflowPlanToDocumentConverter`, `WorkflowPlanValidator` as-is.
- **Builds on `aimodel-gnkh` (closed)** — extends the Skills framework with one new skill and three new tools.
- **Does NOT block** `aimodel-q1hp` (LLM generalization) — different surface.
- **Does NOT solve** "generated workflow actually produces real video" — that's blocked on unimplemented nodes (`casting`, `shotCompiler`, real provider wiring). Out of scope here.
