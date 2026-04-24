# Review Checkpoint

**type:** `reviewCheckpoint`
**category:** `Utility`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

A static development/testing checkpoint that suspends a workflow run when `approved=false` and passes data through unchanged when `approved=true`. Simpler than `humanGate` — no channel routing, no human proposal, no timeout.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `data` | `json` | false | yes | Any JSON payload to pass through (or block). |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `data` | `json` | false | The input `data` passed through unchanged. Only emitted when `approved=true`. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `approved` | bool | `false` | — | When `false`, raises `ReviewPendingException` and suspends the run. When `true`, passes data through. |

## Behavior

`execute()` checks `config.approved`:

- **`approved=false`:** raises `ReviewPendingException`. The run suspends. The runner persists a `PendingInteraction` record. A developer or operator must set `approved=true` on the workflow config and resume the run (or the runner may provide a direct `approve(run_id, node_id)` shortcut).

- **`approved=true`:** returns `{"data": ctx.inputs['data']}`. Data passes through unchanged. No LLM calls, no external API calls, no side effects.

Unlike `humanGate`, this node does not deliver a message to any channel, does not accept a typed human response, and does not support timeout or fallback. It is purely a configuration-controlled gate for workflow development — insert it after any generation node to pause and inspect the output before proceeding.

**Stub mode** behavior is identical to live mode — the flag is the only control.

## Planner hints

- **When to include:** as a temporary checkpoint during workflow development to inspect intermediate outputs (e.g., after `scriptWriter` or `sceneSplitter`) before expensive downstream calls.
- **When to skip:** production workflows where `humanGate` with channel routing is richer, or any flow that must run fully unattended.
- **Knobs the planner should tune:** none at plan time. The planner should leave `approved=false` when inserting the node (its purpose is to create a pause point). The developer flips it to `true` manually when satisfied.

## Edge cases

- `approved=true` at plan time — the node is a pass-through with no runtime effect. This is valid (the checkpoint has been cleared).
- `approved=false` and the run is resumed: the runner re-enters `execute()`. If `approved` is still `false` in the snapshot config, the run suspends again immediately. The operator must update the config before resuming.
- Chained checkpoints (`reviewCheckpoint → reviewCheckpoint`) — each will block independently when `approved=false`.

## Implementation notes

- `ReviewPendingException` here carries no `HumanProposal` payload (or a minimal one). The runner's `PendingInteraction` record will have an empty proposal — that is intentional; this node is not a user-facing gate.
- Do not implement a "resume with response" path for this node — it does not consume `ctx.inputs['_humanResponse']`. The gate is resolved by changing the workflow config's `approved` field, not by injecting a response.
- This is one of the first three nodes to implement (smoke path: `userPrompt` → `scriptWriter` → `reviewCheckpoint`). Keep it minimal; it exercises the suspension/resume mechanism without channel complexity.
