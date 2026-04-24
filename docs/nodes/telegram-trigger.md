# Telegram Trigger

**type:** `telegramTrigger`
**category:** `Input`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

Entry point for Telegram-triggered workflows. Extracts the incoming message, text, images, and metadata from a Telegram update injected by the runtime, and exposes them as typed output ports.

## Inputs

_None._ The trigger payload is injected by the runtime into `config._triggerPayload`; it is not a port-level input.

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `message` | `json` | false | Full Telegram message object as received. |
| `text` | `text` | false | Extracted text content: `message.text` if present, else `message.caption`. |
| `images` | `imageAsset` | true | Photos and image-mime documents extracted from the message, up to `maxImages`, using the largest available variant for each. |
| `triggerInfo` | `json` | false | `{chatId, messageId, fromId, date}` extracted from the message. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `botToken` | string | `""` | required | Telegram Bot API token. Used for any reply calls made by downstream nodes. |
| `allowedChatIds` | list[string] | `[]` | — | Whitelist of chat IDs that may trigger this workflow. Empty list = all chats allowed. |
| `extractImages` | bool | `true` | — | When false, the `images` output port is always empty. |
| `maxImages` | int | `5` | 1–10 | Maximum number of images to extract per message. |
| `filterKeywords` | list[string] | `[]` | — | If non-empty, only process messages whose text contains at least one of these keywords (case-insensitive). |

## Behavior

At **edit/preview time** (no `_triggerPayload` in config), `execute()` returns empty/idle outputs — nulls or empty lists on each port — so downstream validation can still run without a real message.

At **run time**, the runtime injects the raw Telegram update as `config._triggerPayload` before calling `execute()`. The node then:

1. Checks `allowedChatIds`: if non-empty and the message's `chat.id` is not in the list, returns idle outputs (workflow effectively becomes a no-op for this trigger).
2. Checks `filterKeywords`: if non-empty and none match in the message text/caption, returns idle outputs.
3. Extracts `text` from `message.text` or `message.caption` (whichever is non-null).
4. If `extractImages=true`, collects photos (`message.photo` array, largest variant = last element) and documents with `mime_type` starting with `image/`, up to `maxImages` total. Each is stored via `ctx.storage` and returned as an `imageAsset`.
5. Builds `triggerInfo` from `{chatId: message.chat.id, messageId: message.message_id, fromId: message.from.id, date: message.date}`.
6. Returns all four output payloads.

## Planner hints

- **When to include:** workflows that start from a Telegram message — product analysis pipelines, story pipelines, on-demand video generation.
- **When to skip:** batch/scheduled workflows or those driven by `userPrompt` or MCP.
- **Knobs the planner should tune:** `allowedChatIds` (restrict to known chat IDs in production), `maxImages` (lower for text-only flows), `filterKeywords` (command-prefix filtering, e.g., `["/generate"]`).

## Edge cases

- If `message.photo` is absent and `message.document` is absent, `images` output is an empty list (`multiple=True` port; empty list is valid).
- If `botToken` is empty and a downstream node needs to reply, that downstream node will independently fail — this node does not validate the token.
- Messages from bots (`from.is_bot=true`) should be silently ignored to prevent loops; implement this guard in the runner's trigger dispatch layer, not inside this node.

## Implementation notes

- The `_triggerPayload` key is a runtime-injected config field, not part of `config_rules()` schema — validation should allow extra keys or explicitly whitelist it.
- Image extraction: for `message.photo`, the Telegram API returns an array sorted ascending by size; the largest variant is always the last element. Prefer the largest for quality.
- Images should be fetched via `ctx.http` (with `botToken` in the URL: `https://api.telegram.org/file/bot{token}/{file_path}`) and persisted via `ctx.storage` before creating the `imageAsset` payload.
- `botToken` passed in config is available to downstream nodes (e.g., `telegramDeliver`, `humanGate` with channel=telegram) via their own config; this node does not need to forward it.
