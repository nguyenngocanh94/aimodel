# Image Asset Mapper

**type:** `imageAssetMapper`
**category:** `Visuals`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

Transforms a list of generated image assets into ordered image frames suitable for video composition, using an LLM to assign intelligent durations and ordering metadata.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `images` | `imageAsset` | true | yes | List of image assets to map into frames. At least one image required. |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `frames` | `imageFrame` | true | Ordered list of image frame objects, one per input image. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `llm.provider` | string | `"stub"` | — | LLM provider for the frame-mapping call. |
| `llm.model` | string | `""` | — | Model to use. Empty string = provider default. |

## Behavior

`execute()` receives the list of `imageAsset` items from `ctx.inputs['images']` and issues a structured LLM call that maps each image to a frame object.

The LLM is provided with the image URLs and any available metadata (dimensions, seed, order of arrival) and asked to assign:
- `duration` — display duration in seconds, chosen intelligently based on image count and typical video pacing.
- `order` — sequential order for video composition (usually matches arrival order, but the LLM may reorder if metadata suggests it).

Each output frame conforms to the `imageFrame` DataType:

```
{
  id:        string,      // stable identifier, e.g. "frame-{index}"
  imageUrl:  string,      // from the source imageAsset.url
  duration:  float,       // seconds
  order:     int          // 0-based sort index
}
```

The output `frames` list has `multiple=True`. The `videoComposer` node consumes this list directly.

**Stub mode** returns deterministic frames with a fixed duration of 3.0 seconds per image and order matching the input sequence.

## Planner hints

- **When to include:** between `imageGenerator` (or any image-producing node) and `videoComposer`. This is the standard bridge node in image-to-video pipelines.
- **When to skip:** when frames come from a source that already provides `imageFrame`-typed data, or when video is generated directly without intermediate image assets (e.g., `wanR2V`).
- **Knobs the planner should tune:** none — this node is fully mechanical. The LLM provider/model choice affects duration quality but not creative output.

## Edge cases

- Empty `images` list — fail with a validation error before calling the LLM; `videoComposer` with no frames would produce an invalid video.
- A single image — the LLM returns one frame with a reasonable full-video duration.
- LLM assigns `duration=0` to any frame — replace with a minimum fallback (e.g., 1.0 s) rather than passing zero to the compositor.
- Images arriving out of expected order (due to concurrent auto-iteration upstream) — the LLM uses the `order` field to establish final sequence; the node should sort the output list by `order` before returning.

## Implementation notes

- This node receives a `list[imageAsset]` on its `images` port (`multiple=True`) — the input is already a fully coalesced list when `execute()` is called. No per-item iteration happens inside this node.
- The LLM call is lightweight: pass image URLs + count, ask for a JSON array of `{id, duration, order}`. The URL is already in each `imageAsset`.
- In stub mode, produce `n` frames where `n = len(images)`, each with `duration=3.0` and `order=index`.
- Sort output list by `order` before returning to guarantee deterministic input to `videoComposer`.
