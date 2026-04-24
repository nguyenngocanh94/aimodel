# User Prompt

**type:** `userPrompt`
**category:** `Input`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

Emits a static prompt string configured at workflow design time. Serves as the entry point for prompt-driven workflows that are not triggered by an external event.

## Inputs

_None._

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `prompt` | `prompt` | false | The configured prompt string, emitted as a `prompt` payload. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `prompt` | string | `""` | required, min length 1 | The prompt text to emit. |

## Behavior

`execute()` reads `config.prompt` and emits it directly on the `prompt` output port. No LLM calls, no external API calls, no side effects. The returned payload is a `prompt` DataType value (string with optional metadata — in this case no metadata).

In stub mode the behavior is identical; there is no meaningful distinction between stub and live for this node.

## Planner hints

- **When to include:** any workflow that starts from a user-authored text prompt rather than a Telegram message, MCP call, or other dynamic trigger.
- **When to skip:** when the entry point is `telegramTrigger` or another event-driven input node.
- **Knobs the planner should tune:** `prompt` — the planner writes the prompt text based on the user's brief before emitting the workflow JSON.

## Edge cases

- An empty `prompt` string fails config validation before execution. The runner should surface this as a validation error, not a runtime error.
- This node has no input ports; it can never be blocked waiting for upstream data.

## Implementation notes

- The `prompt` output port carries DataType `prompt`. Downstream nodes that accept `prompt` (e.g., `scriptWriter`, `imageGenerator`) connect to this port directly.
- No caching benefit here — execution is instant and stateless.
- The config validator should reject `null` and empty-string values before `execute()` is ever called.
