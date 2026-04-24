# Video Composer

**type:** `videoComposer`
**category:** `Video`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

Stitches an ordered list of image frames into a video, optionally overlaying an audio track. Delegates to a media composition provider (ffmpeg wrapper, Shotstack, Remotion, etc.) and returns a hosted video asset.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `frames` | `imageFrame` | true | yes | Ordered list of image frames (from `imageAssetMapper`). Must contain at least one frame. |
| `audio` | `audioAsset` | false | no | Optional audio track to overlay on the composed video. |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `video` | `videoAsset` | false | Composed video: `{url, duration, resolution, aspectRatio, seed}`. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `video.provider` | string | `"stub"` | — | Media composition provider: `ffmpeg`, `shotstack`, `remotion`, or `stub`. |

## Behavior

`execute()` receives the list of `imageFrame` items from `ctx.inputs['frames']` (sorted by `order`) and the optional `audioAsset` from `ctx.inputs['audio']`.

The node delegates to the configured `video.provider`:
- **`ffmpeg`** — builds an ffmpeg command that sequences the images with per-frame durations (`-loop 1 -t {duration}`), concatenates them, and overlays the audio if present. Returns a local file URL written to `ctx.storage`.
- **`shotstack`** — constructs a Shotstack timeline JSON from frames + audio and submits a render job via `ctx.http`. Polls for completion and returns the hosted URL.
- **`remotion`** — to be specified.
- **`stub`** — returns a deterministic synthetic `videoAsset` URL without calling any media service.

The output `videoAsset` payload:

```
{
  url:         string,
  duration:    float,      // sum of all frame durations
  resolution:  string,     // e.g. "1080x1920"
  aspectRatio: string,     // e.g. "9:16"
  seed:        null        // videoComposer does not use seeds
}
```

`duration` is the sum of all `imageFrame.duration` values. `resolution` and `aspectRatio` are derived from the first frame's image dimensions.

## Planner hints

- **When to include:** any pipeline that assembles image frames into a final video. Standard terminal node before `telegramDeliver` or `finalExport` in image-based pipelines.
- **When to skip:** when video is generated directly by `wanR2V` — that node produces a `videoAsset` directly without needing composition.
- **Knobs the planner should tune:** `video.provider` — `ffmpeg` for local/docker deployments; `shotstack` for cloud renders; `stub` for development.

## Edge cases

- Empty `frames` list — fail with a validation error; a video with no frames is not composable.
- `frames` list unsorted (mixed `order` values) — sort by `order` before composing to guarantee correct sequence.
- `audio` is absent — produce a silent video (or video with no audio track). Do not fail.
- Frame images are not yet available at the URL (e.g., storage latency) — use `ctx.http` with retry.
- Long render times (Shotstack async): use the polling pattern via `ctx.http`; do not block the thread with `sleep`. If the provider supports webhooks, that is preferable but to be specified.

## Implementation notes

- Sort `frames` by `imageFrame.order` at the start of `execute()` regardless of input order.
- Implement a thin provider adapter per `video.provider` value. The adapter interface should be: `compose(frames: list[imageFrame], audio: audioAsset | None) → videoAsset`.
- For `ffmpeg`: run via subprocess through `ctx.http` (or a dedicated subprocess abstraction if available). The ffmpeg binary must be available in the container.
- Store the composed video via `ctx.storage` and return the storage URL, not the raw binary path.
- The `seed` field in `videoAsset` is `null` for composer output (seeds are only relevant for generative video nodes like `wanR2V`).
