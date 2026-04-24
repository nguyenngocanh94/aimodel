# Scene Splitter

**type:** `sceneSplitter`
**category:** `Script`
**vibe impact:** `Critical`
**human gate:** no

## Purpose

Splits a structured script into an ordered list of discrete visual scenes, each with a title, description, visual direction, duration, and narration. Feeds the per-scene prompt refinement or Wan pipeline downstream.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `script` | `script` | false | yes | Structured script from `scriptWriter` (or any node emitting `script` type). |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `scenes` | `scene` | true | Ordered list of scene objects. Each item is one `scene` DataType value. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `maxScenes` | int | `10` | 1–50 | Maximum number of scenes to generate. The LLM will not exceed this count. |
| `includeVisualDescriptions` | bool | `true` | — | When true, each scene includes a `visualDescription` field with camera/visual direction. |
| `provider` | string | `"stub"` | required | LLM provider. |
| `apiKey` | string | `""` | — | API key. |
| `model` | string | `"gpt-4o"` | — | Model to use. |
| `edit_pace` | enum | `"steady"` | `slow_meditative` \| `steady` \| `fast_cut` \| `rapid_fire` | vibe-linked | Tunes target scene durations. `slow_meditative` → longer scenes; `rapid_fire` → shorter, more scenes. |
| `scene_granularity` | enum | `"normal"` | `broad` \| `normal` \| `fine` | vibe-linked | Controls scene density at fixed script length. `fine` produces more scenes from the same script; `broad` produces fewer. |

## Behavior

`execute()` issues a cached structured LLM call. The system prompt instructs the LLM to split the incoming `script` into scenes that sum to approximately the script's intended duration. The LLM is given `maxScenes`, `edit_pace`, and `scene_granularity` as directives.

Each scene in the output list conforms to the `scene` DataType:

```
{
  index:              int,
  title:              string,
  description:        string,
  visualDescription:  string | null,   // null when includeVisualDescriptions=false
  durationSeconds:    int,
  narration:          string
}
```

`edit_pace` tuning:
- `slow_meditative` — scenes 8–15 s, few cuts, contemplative pacing
- `steady` — scenes 4–8 s, standard TikTok rhythm
- `fast_cut` — scenes 2–4 s, energetic
- `rapid_fire` — scenes 1–3 s, maximum energy / trending meme pacing

`scene_granularity` operates independently: `fine` allows the LLM to split a single narrative beat into multiple short scenes; `broad` consolidates beats into fewer larger scenes.

**Stub mode** returns a deterministic set of placeholder scenes (count = min(`maxScenes`, 3)), with canned titles and narration derived from the script's `title` field.

The output port `scenes` has `multiple=True`. Downstream nodes (e.g., `promptRefiner`) with a `scene` input of `multiple=False` will auto-iterate — see `../workflow.md` §5.

## Planner hints

- **When to include:** between `scriptWriter` and `promptRefiner` (or `wanPromptFormatter`) in any visual-output pipeline.
- **When to skip:** Wan pipelines where `storyWriter` feeds `wanPromptFormatter` directly (story writer provides shot-level detail; scene splitting is redundant).
- **Knobs the planner should tune:**
  - `edit_pace` — vibe-linked: `raw_authentic` → `fast_cut`; `aesthetic_mood` → `slow_meditative`; `funny_storytelling` → `rapid_fire`; `clean_education` → `steady`.
  - `scene_granularity` — `fine` for high-energy/short-form; `broad` for long-form or explainer content.
  - `maxScenes` — calibrate to video length and image generation budget (each scene = one image gen call).

## Edge cases

- LLM may return fewer scenes than `maxScenes` if the script is short — this is valid.
- Scenes with `durationSeconds=0` should be rejected and the node should re-invoke the LLM or surface an error.
- `includeVisualDescriptions=false` reduces prompt quality downstream — only omit if a custom `imageStyle` in `promptRefiner` is sufficient.

## Implementation notes

- Use structured output / JSON mode to guarantee an array of scene objects.
- Cache per `(node_type, version, config_hash, input_hash)`. The input hash is derived from the full `script` payload — changes to any script field invalidate the cache.
- The `scenes` output is `multiple=True`, meaning the returned dict key `"scenes"` must be a `list[scene]`. The runner's fan-out logic handles auto-iteration downstream.
- If `maxScenes` is exceeded in the LLM response, truncate to `maxScenes` ordered by `index`.
