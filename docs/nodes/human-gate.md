# Human Gate

**type:** `humanGate`
**category:** `Utility`
**vibe impact:** `Neutral`
**human gate:** yes

## Purpose

Suspends a workflow run to collect a human decision or approval, then resumes with the human's response. Supports multiple delivery channels (UI, Telegram, MCP) with configurable timeout and auto-fallback.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `data` | `json` | false | yes | The payload to present to the human for review or decision. |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `response` | `json` | false | The human's response: `{response: HumanResponse}`. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `messageTemplate` | string | `""` | — | Template string for the message sent to the human. Supports `{{var}}` substitution from fields in the `data` payload. Empty = use a default summary of `data`. |
| `channel` | enum | `"ui"` | `ui` \| `telegram` \| `mcp` \| `any` | Delivery channel for the human-gate proposal. `any` = runtime chooses first available. |
| `timeoutSeconds` | int | `0` | 0–86400 | Seconds to wait for a response before applying fallback. `0` = wait indefinitely. |
| `autoFallbackResponse` | json | `null` | — | If non-null and `timeoutSeconds` is reached without a response, resume the run with this value as the response. If null and timeout is reached, the run fails. |
| `options` | list | `null` | — | Optional list of selectable response options to present to the human (e.g., `["Approve", "Reject", "Edit"]`). `null` = free-form response. |
| `botToken` | string | `""` | — | Required when `channel=telegram`. Telegram Bot API token. |
| `chatId` | string | `""` | — | Required when `channel=telegram`. Target chat ID. |

## Behavior

`execute()` builds a `HumanProposal` from `ctx.inputs['data']` and `config.messageTemplate` (with `{{var}}` substitution applied). If `options` is non-null, the proposal includes a selectable options list.

The node calls `ctx.human.propose(proposal)`, which:
1. Persists a `PendingInteraction` record.
2. Delivers the proposal to the configured `channel`.
3. Raises `ReviewPendingException`.

The run transitions to `suspended` state. No downstream nodes execute until the gate is resolved.

On **resume**, the runner calls `execute()` again with `ctx.inputs['_humanResponse']` populated. The node returns `{"response": ctx.inputs['_humanResponse']}` on the output port.

**Timeout handling:** if `timeoutSeconds > 0` and no response arrives within the window, the runner checks `autoFallbackResponse`. If non-null, the run is resumed automatically with the fallback value. If null, the run transitions to `failed` with a timeout error.

**Stub mode:** the node raises `ReviewPendingException` normally. In local development the test harness should inject a canned `_humanResponse` to complete the run.

## Planner hints

- **When to include:** mid-pipeline decision points where a human must choose a path, approve a draft, or provide a correction before costly generation steps proceed.
- **When to skip:** fully automated pipelines with no human-in-the-loop requirement. Also skip when `storyWriter`'s built-in human gate is sufficient — avoid stacking two gates for the same approval.
- **Knobs the planner should tune:**
  - `channel` — `telegram` for Telegram-based workflows; `ui` for web-UI flows; `mcp` for AI-assistant-driven flows.
  - `messageTemplate` — the planner should craft a clear approval message with `{{var}}` references to key fields from the `data` payload.
  - `timeoutSeconds` — set a realistic timeout for asynchronous flows (e.g., 3600 for a 1-hour window); leave at 0 for interactive sessions.
  - `autoFallbackResponse` — set a safe default (e.g., `{"approved": false}`) for unattended pipelines where timeout = reject.
  - `botToken` + `chatId` — required for `channel=telegram`; populated from the upstream `telegramTrigger` or workflow-level config.

## Edge cases

- `channel=telegram` with empty `botToken` or `chatId` — fail at config validation before `execute()` is called.
- `messageTemplate` references a `{{var}}` that doesn't exist in `data` — render the variable as an empty string or the literal `{{var}}`; do not fail the run.
- `options` list with zero items — treat as `null` (free-form response).
- Re-entrant `execute()` call without `_humanResponse` populated (runner bug) — the node should detect this and raise `ReviewPendingException` again rather than returning empty output.

## Implementation notes

- The suspension/resume lifecycle is entirely managed by the runner per `../workflow.md` §7.2 rule 5. The node only raises `ReviewPendingException` and returns the response on resume — it does not implement any waiting or polling.
- `{{var}}` substitution in `messageTemplate`: use a simple regex replace over the `data` JSON object's top-level keys. Nested key access (e.g., `{{a.b}}`) is to be specified.
- `HumanProposal` payload should carry enough context for the review channel to render a useful message: the rendered template text, the raw `data` payload, and the `options` list.
- This is a general-purpose gate. For the `storyWriter` use case, the node implements the gate directly (see `story-writer.md`). `humanGate` is for generic mid-pipeline gates.
