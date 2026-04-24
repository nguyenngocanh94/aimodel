# Telegram Deliver

**type:** `telegramDeliver`
**category:** `Output`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

Delivers a JSON content payload as a formatted Telegram message to a configured chat. Terminal output node for Telegram-based workflows.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `content` | `json` | false | yes | The content payload to format and send. |
| `chatId` | `text` | false | no | Override chat ID. If provided, takes precedence over `config.defaultChatId`. |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `deliveryResult` | `json` | false | Delivery confirmation: `{ok, statusCode, chatId, messageLength, format, sentAt, telegramResponse}`. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `botToken` | string | `""` | required | Telegram Bot API token. |
| `defaultChatId` | string | `""` | required | Default target chat ID. Used when the `chatId` input port is not connected. |
| `messageFormat` | enum | `"structured"` | `text` \| `markdown` \| `html` \| `structured` | Controls how `content` is rendered into a Telegram message. |
| `includeTimestamp` | bool | `true` | — | Appends a human-readable timestamp to the message. |
| `notifyOnSuccess` | bool | `true` | — | When false, sends the message silently (no notification sound on the recipient's device). |
| `maxMessageLength` | int | `4096` | 100–4096 | Maximum message length in characters. Content is truncated if it exceeds this limit. |

## Behavior

`execute()` resolves the target chat ID: `ctx.inputs['chatId']` if connected and non-empty, else `config.defaultChatId`.

The `content` JSON payload is formatted according to `messageFormat`:

- **`text`** — JSON serialised to a plain text string.
- **`markdown`** — key-value pairs rendered as `*Key:* value` lines.
- **`html`** — key-value pairs rendered as `<b>Key:</b> value` lines.
- **`structured`** — multi-section message with section headers and emoji decorators, optimised for human readability in Telegram. (Exact emoji and section layout to be specified in implementation.)

If `includeTimestamp=true`, a timestamp line is appended: e.g., `Sent: 2026-04-22 14:30:00 UTC`.

The formatted message is truncated to `maxMessageLength` characters with a `...` suffix if needed.

The node calls `ctx.http.post("https://api.telegram.org/bot{botToken}/sendMessage", payload)` with:
- `chat_id`: resolved chat ID.
- `text`: formatted message.
- `parse_mode`: `"Markdown"` or `"HTML"` for the respective formats; omitted for `text` and `structured`.
- `disable_notification`: `true` when `notifyOnSuccess=false`.

The Telegram API response is captured. Output `deliveryResult`:

```
{
  ok:               bool,
  statusCode:       int,
  chatId:           string,
  messageLength:    int,
  format:           string,
  sentAt:           string,    // ISO 8601
  telegramResponse: object     // raw Telegram API response body
}
```

**Stub mode** (empty `botToken`): skips the API call and returns a synthetic `deliveryResult` with `ok=true` and a canned `telegramResponse`.

## Planner hints

- **When to include:** any workflow that delivers results back to a Telegram user or group, especially Telegram-triggered workflows that should reply to the originating chat.
- **When to skip:** UI-only or MCP-only delivery flows.
- **Knobs the planner should tune:**
  - `messageFormat` — `structured` for rich summaries; `markdown` for formatted reports; `text` for raw JSON debug output.
  - `defaultChatId` — populated from the `telegramTrigger`'s `triggerInfo.chatId` via the `chatId` input port (preferred over hardcoding in config).
  - `maxMessageLength` — reduce if content is known to be very long and truncation is acceptable.

## Edge cases

- Both `chatId` input and `config.defaultChatId` are empty — fail with a validation error before making the API call.
- Telegram API returns `ok=false` (e.g., invalid `botToken`, chat not found) — surface the API error in the run log. Do not silently swallow; raise a runtime error.
- Content payload is very large — the structured formatter should summarise or truncate individual fields before applying `maxMessageLength` to the final string.
- Telegram's `parse_mode=Markdown` is sensitive to unescaped characters — the markdown formatter should escape `_`, `*`, `[`, `]`, `(`, `)`, `~`, `` ` ``, `>`, `#`, `+`, `-`, `=`, `|`, `{`, `}`, `.`, `!` in user-generated content.

## Implementation notes

- Use `ctx.http` for the Telegram API call, not a raw HTTP client, to get retry and logging.
- Retry on Telegram 429 (Too Many Requests) with the `retry_after` header value respected.
- The `chatId` input port (`multiple=False`, `required=False`) allows dynamic chat ID routing — e.g., the `telegramTrigger` outputs `triggerInfo.chatId` which can be wired here via a small adapter or directly if the types are compatible.
- `botToken` in config is the deliver-node's own token. It may be the same token as in `telegramTrigger` config, but it is configured independently.
