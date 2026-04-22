# Telegram → Agent Routing Redesign

**Date:** 2026-04-22
**Status:** Design — revised after first review
**Author:** Ann (drafted with Claude assistance)

## Problem

The Telegram webhook never reaches the `WorkflowPlanner`. A user message like *"hãy tạo một workflow làm video short tiktok 30s…"* sits in an intake buffer, gets summarized back as *"Reply `ok` để xác nhận…"*, and on confirmation tries to execute a **pre-existing** workflow matched by bot token. If no workflow exists (current state: zero rows), the user gets *"❌ Không tìm thấy workflow phù hợp."* The full `TelegramAgent` and its `ComposeWorkflowTool` / `RefinePlanTool` / `PersistWorkflowTool` / `CatalogLookupTool` / Voyage wiring is built but unreachable from the webhook.

Secondary problem: `handleConfirmation()` uses a hardcoded Vietnamese + English keyword list (`ok`, `chốt`, `xác nhận`, `hủy`, …). Fragile, locale-bound, can't handle natural phrasing (*"ờ làm đi chị ơi"*, *"đổi cái tên rồi chốt"*).

## Guiding principle

**LLM is the brain, code is tools.** Anything requiring judgment or natural-language interpretation is an LLM call. Anything deterministic — buffering, session storage, job dispatch, DB writes, HTTP plumbing — is code. No keyword matching for intent or confirmation. The `TelegramAgent` already embodies this pattern; we extend it to the webhook entry point.

## Scope

**In scope:**
- Wire webhook → `TelegramAgent::handle()` for the free-text fallback.
- Delete `handleConfirmation()`, keyword lists, and the hardcoded workflow-match-by-bot-token in the controller.
- Add a shorter (5s) debounce window for pending-draft turns; keep 30s for fresh turns.
- Fix `TelegramAgent.php:151` to accept image-only messages.
- Minimum test work to verify the routing flip end-to-end.

**Explicitly out of scope (moved to follow-up plans):**
- Semantic workflow matching in `RunWorkflowTool` → a later plan. `RunWorkflowTool` keeps its existing slug-based contract; the LLM picks slugs from the catalog already passed to `SystemPrompt`.
- Writing `catalog_embedding` in `PersistWorkflowTool` → a later plan. The embedding column exists and is populated by nothing right now; adding writes is independent of the routing fix.
- Full backfill of the two aspirational test files (`TelegramWebhookAgentRoutingTest`, `TelegramAgentEndToEndTest`). We make the minimum set pass to verify the flip; remaining tests stay as future work.
- Queue worker infrastructure (supervisord, docker-compose worker service).

## Target architecture

```
Telegram webhook POST
  ↓
TelegramWebhookController::handle()
  ├─ Priority 0  callback_query                      → handleCallbackQuery
  ├─ Priority 1  reply-to pending interaction        → tryResumePendingByMessage
  ├─ Priority 1b bare text + single pending in chat  → tryResumePendingByChat
  ├─ Priority 1c legacy HumanGate                    → tryResumeGate
  └─ Priority 2  free text (catch-all)               → bufferMessage
                                                         ↓ debounce: 5s if AgentSessionStore has pending draft, else 30s
                                                         ↓
                                                 ProcessTelegramBatchJob
                                                         ↓
                                                 TelegramAgent::handle(combinedUpdate)
                                                         ↓
                                    LLM picks a tool (intent == tool choice):
                                      ComposeWorkflowTool  → WorkflowPlanner → CatalogLookupTool (Voyage)
                                      RefinePlanTool       → re-plan from feedback
                                      PersistWorkflowTool  → save draft (no embedding in this plan)
                                      RunWorkflowTool      → bySlug(slug) → RunWorkflowJob
                                      GetRunStatusTool     → read ExecutionRun
                                      CancelRunTool        → cancel
                                      ListWorkflowsTool    → enumerate
                                      ReplyTool            → chat / clarify / acknowledge
```

Priorities 0/1/1b/1c are unchanged — they're tied to explicit message IDs or DB-tracked pending state; no language judgment needed. The only behavioral flip is **Priority 2**.

## Components

### Reused (buffering primitives)
`ProcessTelegramBatchJob`'s Redis buffering, debounce timer, and stale-marker coordination are reused. Its *finish path* changes completely (intake-summary → agent call).

### Unchanged
- Priority 0/1/1b/1c controller handlers.
- All `App\Services\TelegramAgent\Tools\*` classes and their contracts — including `RunWorkflowTool`'s existing `slug`-based contract.
- All `App\Services\TelegramAgent\BehaviorSkills\*` — notably `ComposeWorkflowBehaviorSkill` already covers the full propose→explain→approve→persist loop with approval/refinement/rejection language and slug generation. No new prompt writing needed.
- `WorkflowPlanner`, `WorkflowPlannerAgent`, `CatalogLookupTool`, `EmbeddingService`.
- Redis session keys (`telegram_intake:*`), conversation store, `AgentSessionStore`, `PendingInteraction`.

### Deleted
- `TelegramWebhookController::handleConfirmation()` (~50 lines).
- `TelegramWebhookController::triggerWorkflowFromSession()` (~80 lines).
- `TelegramWebhookController::findWorkflowByBotToken()` (~15 lines) — not replaced; the LLM picks workflow slugs from the catalog passed to its system prompt.
- `$confirmWords` / `$rejectWords` arrays and the entire keyword-matching block.
- `ProcessTelegramBatchJob`'s intake-summary formatter (*"📋 Đã nhận thông tin…"*) and the `status = 'awaiting_confirmation'` transition.

### Added
- **`App\Services\TelegramAgent\TelegramAgentFactory`** — thin per-request factory. Not a singleton (isolation per webhook hit; `chatId` / `botToken` are instance state).
- **`ProcessTelegramBatchJob::assembleCombinedUpdate(array $session): array`** — flattens buffered texts + photos into one synthetic Telegram update. Joins texts with `\n\n`, merges `photo` arrays preserving order, preserves burst metadata in `message._intake`.
- **Debounce-window selector** in `TelegramWebhookController::bufferMessage()` — check `AgentSessionStore::readPendingDraft($chatId)` before dispatching. If present → 5s delay; otherwise → 30s. Deterministic code making a timing decision from existing state; no judgment.
- **Image-only relaxation** in `TelegramAgent.php:151` — replace `if ($text === '' || $this->chatId === '') return;` with `if ($this->chatId === '' || ($text === '' && empty($update['message']['photo'] ?? []))) return;`. One-line fix; image-only bursts now reach the LLM.
- **Controller constructor seam** `$agentFactory` (already referenced by `TelegramWebhookAgentRoutingTest.php`; we make it concrete).
- **`TelegramAgentServiceProvider`** binding for `TelegramAgentFactory`.

### Verification items (not additions)
- Confirm `AgentSessionStore` already has `storePendingDraft` / `readPendingDraft` / `clearPendingDraft` (or equivalent). If missing, add as trivial Redis ops.
- Confirm `BehaviorSkillComposer` activates `ComposeWorkflowBehaviorSkill` in the agent's prompt pipeline. If not, flip the activation.

## State ownership

**Code owns intake buffering and job dispatch state. Agent-owned tools own the pending-draft lifecycle. Conversation memory is advisory context, not the source of truth for workflow state.**

| Store | Key | Owner | Purpose |
|---|---|---|---|
| Redis | `telegram_intake:{chat}:{bot}` | code | Debounce buffer. 120s TTL. |
| Redis | `telegram_batch_job:{chat}:{bot}` | code | Current batch ID for stale coordination. |
| Redis | `telegram_batch_stale:{batchId}` | code | Marker for superseded jobs. |
| Redis | `AgentSessionStore:*` | agent (via tools) | Pending drafts. LLM controls lifecycle; code reads only for debounce-window selection. |
| Redis | `RemembersConversations` backing | agent | LLM turn memory keyed `{chat}:{bot}`. |
| DB | `pending_interactions` | code + user | In-flight HumanGate resume points. |
| DB | `workflows.catalog_embedding` | **not written in this plan** | Future semantic matcher input. |

## Walkthroughs

### Compose path

**Steps:**
1. User sends a compose brief; Priority 2 → `bufferMessage()`. No pending draft → 30s debounce.
2. Additional messages within 30s reset the debounce; `telegram_batch_stale` supersedes old jobs.
3. Job fires; `assembleCombinedUpdate()` flattens the burst; `TelegramAgent::handle()`.
4. Agent (guided by `ComposeWorkflowBehaviorSkill`) picks `ComposeWorkflowTool`.
5. Tool → `WorkflowPlanner::plan()` → `CatalogLookupTool` → Voyage (or ILIKE fallback).
6. Tool stashes draft in `AgentSessionStore`; agent picks `ReplyTool` with the propose-explain summary.
7. User sees draft + *"OK để mình lưu không? (ok / chỉnh / hủy)"*.

**Failure modes:**
- Planner returns malformed JSON → `ComposeWorkflowTool` returns `available: false` with error → agent picks `ReplyTool` to tell the user + offer retry.
- Voyage down → `CatalogLookupTool` falls back to ILIKE; compose still succeeds, logs warning.
- Refine loop exhausted (5-try cap enforced in `ComposeWorkflowBehaviorSkill`) → agent forces user to choose `ok` or `hủy`.

### Execute path

**Steps:**
1. User sends *(image)* + *"tạo video cho sản phẩm này, theme back to school"*; Priority 2 → `bufferMessage()`. No pending draft → 30s debounce.
2. Job fires; combined update has text + photo; `TelegramAgent::handle()`.
3. Agent sees the catalog in its system prompt, picks `RunWorkflowTool({slug: "<best-fit slug>", params: {...}})`.
4. Tool → `Workflow::triggerable()->bySlug($slug)` → validate params against `param_schema` → create `ExecutionRun` → dispatch `RunWorkflowJob`.
5. Agent picks `ReplyTool`: *"🚀 Đang chạy workflow X, runId abc-123"*.

**Failure modes:**
- Slug hallucinated → `RunWorkflowTool` returns `{error: 'workflow_not_found'}` → agent picks `ReplyTool` with correction + catalog list.
- `param_schema` validation fails → tool returns validation errors → agent picks `ReplyTool` asking for missing params.
- `RunWorkflowJob` dispatch throws → agent catches via laravel/ai error handling → `ReplyTool` apology; `ExecutionRun` row remains in `pending` for manual inspection.

### Confirm-after-draft path

**Steps:**
1. User replies *"ok"* / *"chốt"* / *"👍"*. Priority 2 → `bufferMessage()`. **Pending draft exists** → 5s debounce.
2. Job fires after 5s. `TelegramAgent::handle()`.
3. Agent sees conversation memory shows a proposed plan + `ComposeWorkflowBehaviorSkill` teaches approve/refine/reject patterns. LLM classifies as approval.
4. Agent picks `PersistWorkflowTool({slug, name})`.
5. Tool inserts `Workflow` row. **No `catalog_embedding` write in this plan.**
6. Agent picks `ReplyTool` with saved-slug confirmation.

### Multi-message refinement path

**Steps:**
1. User sends *"actually"* at T+0s, *"change tone to genz"* at T+2s, *"and add 2 scenes"* at T+4s. Pending draft present → 5s debounce window after each message; stale-marker coordination means only the last-scheduled job fires.
2. At T+9s (5s after the last message), one job fires with combined text `"actually\n\n change tone to genz\n\n and add 2 scenes"`.
3. Agent picks `RefinePlanTool({feedback: "<combined>"})`.
4. Tool re-plans; stashes new draft; agent `ReplyTool`s the diff.

**Why 5s:** short enough to feel responsive for single-message approvals, long enough to coalesce a 2-3 message refinement burst. 30s would feel sluggish for a simple "ok". Bypass-entirely would force users into single-message discipline.

## Error handling

| Failure | Design response |
|---|---|
| Voyage API down / invalid key | `tryEmbed()` returns null → `CatalogLookupTool` falls back to `LOWER()+LIKE`. Log warning, not user-visible. |
| Fireworks 502 / 429 | `RetryPrimary` middleware + laravel/ai vendor failover. Final failure → fallback reply. |
| Telegram API unreachable | Log error. **Do not retry agent turn** (side effects may have committed). |
| Redis down | Controller returns 200 + logs. Telegram retries delivery. |
| Postgres down | Agent catches → `ReplyTool` apology. Conversation memory in Redis preserves state for retry. |
| Queue worker down | Jobs sit in Redis. **Not fixed by this design.** Separate infra task. |
| Agent picks no tool | LLM returns plain text. Forward as Telegram reply. |
| Agent turn throws | `ProcessTelegramBatchJob::handle()` `finally` block sends generic apology if no reply went out. Uses last known tool result text if available. |
| User approves with no pending draft | Agent's system-prompt (via `ComposeWorkflowBehaviorSkill`) → clarifying question via `ReplyTool`. |

### Side-effect caution (expanded)

If a tool with side effects has committed — `PersistWorkflowTool` inserting a `Workflow` row, `RunWorkflowTool` creating an `ExecutionRun` + dispatching `RunWorkflowJob`, `CancelRunTool` marking a run cancelled — and a subsequent `ReplyTool` call or raw Telegram send fails, **the job MUST NOT retry the agent turn**. Retrying would double-insert, double-dispatch, or double-cancel. Log the orphan side effect with its row ID / run ID. Accept the transient inconsistency; the user either sees the next attempt's reply or none at all. The fallback-reply `finally` block uses the last successful tool result text so the user learns a side effect occurred even if the primary reply path died.

## Latency budget

| Turn type | Debounce | Agent + tool work | P95 target |
|---|---|---|---|
| Fresh compose | 30s | 15–30s (planner round-trips) | ~60s |
| Pending-draft approval/refinement | 5s | 5–20s | ~20s |
| No-tool chat | 5s (if pending) / 30s (if not) | 2–5s | 10–35s |

The 5s pending-draft window is a product decision: we'd rather have snappy approvals than perfect multi-message coalescing for quick replies. Users with longer thoughts can keep typing — stale-marker coordination means the 5s timer resets.

## Migration sequence

Revised to reflect first-class test work. Each commit is independently mergeable.

1. **Add `TelegramAgentFactory` + provider binding.** Pure addition; no behavior change. Existing tests still pass.
2. **Add `assembleCombinedUpdate()` helper to `ProcessTelegramBatchJob`.** Unused code. No behavior change.
3. **Fix image-only early-exit in `TelegramAgent.php:151`.** Add unit test for image-only `handle()`. One-line fix, one new test.
4. **Wire `AgentSessionStore::readPendingDraft()` check into `TelegramWebhookController::bufferMessage()`** for the 5s-vs-30s decision. Add unit test covering both windows. Verify `AgentSessionStore` has the read method (add if missing, trivial).
5. **Swap `ProcessTelegramBatchJob` finish path** from intake-summary → agent call via factory. Update the minimum test set:
   - `ProcessTelegramBatchJobTest` (new): assembly + agent invocation + stale handling + fallback reply.
   - Remove assertions on intake-summary text in existing tests (obsolete).
   - `TelegramWebhookAgentRoutingTest`: make the **two** core assertions pass — `free text → TelegramAgent::handle()` and priorities 0/1/1b/1c still work. Other assertions in this file remain as future work.
6. **Delete dead code in controller:** `handleConfirmation`, `triggerWorkflowFromSession`, `findWorkflowByBotToken`, keyword lists, Priority 2 (old). Add `$agentFactory` constructor seam.
7. **Verify `BehaviorSkillComposer` activates `ComposeWorkflowBehaviorSkill`.** If not, flip the activation flag. Add one test covering "agent prompt includes compose-workflow skill fragment."

**Baseline step 0:** run the full test suite, document which tests are currently red/skipped, commit nothing. This is the honest starting line, not "green."

Lines added ≈ 120. Lines deleted ≈ 300. Net: codebase shrinks.

## Testing

### Activated (minimum set)
- `tests/Feature/TelegramWebhookAgentRoutingTest.php`: **two core assertions only** — free text → `TelegramAgent::handle()`, priorities 0/1/1b/1c unchanged. Other assertions flagged for follow-up.
- `tests/Feature/TelegramAgentEndToEndTest.php`: **left as-is** for this plan. Flagged for follow-up.

### New
1. **`ProcessTelegramBatchJobTest`**
   - Assembles 3 texts + 1 image into one combined update (text joined with `\n\n`, merged photo array, `_intake` metadata preserved).
   - Calls `TelegramAgent::handle()` exactly once per burst.
   - On agent exception, sends fallback reply and clears session.
   - Respects `telegram_batch_stale` marker (no-op if stale).
2. **`TelegramAgentImageOnlyTest`** — feeds an image-only update, asserts `handle()` does not early-exit, agent sees empty `text` + non-empty `photo`.
3. **`TelegramWebhookDebounceWindowTest`** — asserts 5s delay when pending draft present, 30s otherwise. Uses `Queue::assertPushed(..., fn ($job) => $job->delay === ...)`.

### Updated / removed
- Delete any existing assertions on the `"📋 Đã nhận thông tin…"` intake-summary text.
- Delete any existing tests of `handleConfirmation` keyword behavior.
- Update `TelegramWebhookControllerTest` priority-matrix expectations to reflect the new Priority 2.

### What we do NOT test in this plan
- Specific Vietnamese/English phrases routing to specific tools. That tests the LLM, which is non-deterministic. We test scaffolding.
- Specific node IDs returned by `CatalogLookupTool` for specific queries. Same reason.
- End-to-end Voyage integration (Voyage key loaded, real HTTP). Gated for follow-up infra work.

## Manual QA (post-merge)

1. *"tạo workflow video tiktok 30s bán áo học sinh"* → draft plan reply (~60s).
2. *"đổi tone sang genz"* → revised plan reply (~20s, 5s debounce).
3. *"chốt"* → saved-slug reply (~15s, 5s debounce).
4. *(image)* + *"tạo video cho sản phẩm này"* → run-started reply (~40s).
5. *"đến đâu rồi?"* → status reply.
6. *"dừng đi"* → cancel reply.
7. *"hi"* → capability-summary reply.
8. Image-only message with no text → not silently dropped; agent acknowledges or asks for context.

Each should show Voyage calls in `storage/logs/providers-YYYY-MM-DD.log` where the planner fires.

## Open questions

All previously blocking items resolved per review:
- **Pending-draft debounce:** 5s shortened window. ✓
- **Image-only handling:** relax `TelegramAgent:151` early-exit. ✓
- **Test backfill scope:** minimum for the flip; aspirational tests remain future work. ✓

None blocking for implementation start.

## Blast radius summary

- Files modified: 5 (`TelegramWebhookController`, `ProcessTelegramBatchJob`, `TelegramAgentServiceProvider`, `TelegramAgent` line 151, possibly `AgentSessionStore`).
- Files added: 1 (`TelegramAgentFactory`).
- Files deleted: 0.
- Net lines: ≈ −180.
- User-facing behavior: bot now understands any-language compose / refine / approve / execute / status / cancel / chat intent via the LLM; no fragile keyword matching remains.

## Follow-up plans (not this PR)

1. **`2026-04-23-workflow-catalog-embedding.md`** — add `catalog_embedding` writes in `PersistWorkflowTool`; backfill command; semantic `RunWorkflowTool` matching.
2. **`2026-04-23-queue-worker-infra.md`** — supervisord or docker-compose worker service so `ProcessTelegramBatchJob` doesn't silently stall.
3. **`2026-04-24-telegram-agent-test-backfill.md`** — fully activate `TelegramWebhookAgentRoutingTest` + `TelegramAgentEndToEndTest`.
