# Wan R2V

**type:** `wanR2V`
**category:** `Video`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

Generates a character-consistent video from a Wan-formatted prompt and optional reference media using the Wan 2.7 reference-to-video model. The primary video generation node for Wan-based pipelines.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `prompt` | `wanPrompt` | false | yes | Wan-formatted multi-shot prompt from `wanPromptFormatter`. Typed `wanPrompt` — not a plain text string. |
| `referenceVideos` | `videoUrl` | true | no | Optional reference video URLs for character/style grounding. |
| `referenceImages` | `imageAsset` | true | no | Optional reference images for character/style grounding. |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `video` | `videoAsset` | false | Generated video: `{url, duration, resolution, aspectRatio, seed}`. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `provider` | string | `"stub"` | — | Provider adapter to use. Currently `fal` (fal.ai) or `stub`. |
| `model` | string | `"fal-ai/wan/v2.7/reference-to-video"` | — | Model endpoint. Provider-specific. |
| `aspectRatio` | enum | `"9:16"` | `16:9` \| `9:16` \| `1:1` \| `4:3` \| `3:4` | Output video aspect ratio. |
| `resolution` | enum | `"1080p"` | `720p` \| `1080p` | Output resolution. |
| `duration` | enum | `"5"` | `2` \| `3` \| `4` \| `5` \| `6` \| `7` \| `8` \| `9` \| `10` | Video duration in seconds. String enum as some APIs represent this as a string param. |
| `multiShots` | bool | `false` | — | When true, passes the prompt as a multi-shot sequence to the model (model must support multi-shot mode). |
| `seed` | int | `null` | 0–2147483647 or null | Generation seed for reproducibility. `null` = random. |

## Behavior

`execute()` collects reference URLs:
1. `referenceVideos` (`list[videoUrl]`) — raw URL strings.
2. `referenceImages` (`list[imageAsset]`) — extracts `.url` from each asset.

Both lists are combined into a single reference URL array submitted to the provider.

The node then calls the provider's R2V endpoint via `ctx.http`. For the `fal` provider:
- Endpoint: the configured `model` string.
- Request payload includes: `prompt` (the `wanPrompt.prompt` string), `aspect_ratio`, `resolution`, `duration`, `seed` (if not null), `reference_urls` (combined list), `multi_shot` flag.

The provider returns a video URL and metadata. The node extracts:
- `url` — the hosted video URL (stored via `ctx.storage` if the provider does not host permanently).
- `duration` — from provider response or from config `duration`.
- `resolution` — from provider response or config.
- `aspectRatio` — from config.
- `seed` — from provider response (actual seed used, which differs from input seed when seed=null).

Output `videoAsset`:

```
{
  url:         string,
  duration:    float,
  resolution:  string,
  aspectRatio: string,
  seed:        int | null
}
```

**Stub mode** returns a deterministic synthetic `videoAsset` with the configured `aspectRatio`, `resolution`, and `duration`, without calling fal.ai.

## Planner hints

- **When to include:** character-consistent video generation pipelines using Wan 2.7. Typically wired as: `wanPromptFormatter.wanPrompt → wanR2V.prompt`.
- **When to skip:** pipelines using image generation + video composition (`imageGenerator` → `imageAssetMapper` → `videoComposer`), or when using a different video provider.
- **Knobs the planner should tune:**
  - `aspectRatio` — match to target platform (TikTok → `9:16`, YouTube → `16:9`).
  - `duration` — calibrate to story length (10 s for short scenes, 5 s default for typical shots).
  - `resolution` — `1080p` for production; `720p` for faster dev iterations.
  - `seed` — set a fixed seed when reproducibility is required; leave null for creative variation.
  - `multiShots` — enable when `wanPromptFormatter` used `multiShot` or `sound` formula.

## Edge cases

- `referenceVideos` and `referenceImages` both empty — valid; the model generates from the prompt alone (no character grounding). Output may have inconsistent character appearance across shots.
- Provider timeout or async render: fal.ai may return a job ID rather than an immediate result. Implement polling via `ctx.http` with exponential backoff.
- `seed=null` — the provider assigns a random seed; capture the actual seed from the response and include it in the output for reproducibility debugging.
- `wanPrompt.prompt` string is empty — fail fast before calling the provider.

## Implementation notes

- The `prompt` input port is typed `wanPrompt` (not `text` or `prompt`). Connecting a plain `text` or `prompt` port to this node is a type validation error per `../workflow.md` §3.4 rule 4. The upstream must be `wanPromptFormatter`.
- The `wanPrompt` payload carries `formula`, `characterTags`, and `includeSound` alongside the `prompt` string. Pass these to the provider if it supports them; otherwise use only `prompt`.
- Reference URL collection should handle `null` gracefully — if either list is absent/empty, skip that source.
- Use `ctx.http` for the provider call, not a raw HTTP client, to get uniform retry, timeout, and logging.
- For fal.ai specifically: the model may require a queue/poll pattern (`fal.queue.submit` + `fal.queue.result`). Implement this in the fal adapter, not in `execute()`.
