# TTS Voiceover Planner

**type:** `ttsVoiceoverPlanner`
**category:** `Audio`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

Takes a list of scenes and produces a structured audio plan that maps each scene to a voiceover segment with voice assignment and estimated duration. Planning only — audio synthesis is a separate downstream node.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `scenes` | `scene` | true | yes | Ordered list of scenes (typically from `sceneSplitter`). |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `audioPlan` | `audioPlan` | false | Structured audio plan with one segment per scene. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `llm.provider` | string | `"stub"` | — | LLM provider for the planning call. |
| `llm.model` | string | `""` | — | Model to use. Empty string = provider default. |

## Behavior

`execute()` receives the full list of scene objects from `ctx.inputs['scenes']` (a `list[scene]` because `multiple=True`) and issues a structured LLM call that maps each scene to an audio segment.

The LLM is provided with each scene's `narration`, `durationSeconds`, and `index`, and asked to assign:
- `voice` — a voice identifier or style description (e.g., `"female-warm-vi"`, `"male-energetic-en"`).
- `text` — the narration text for this segment (may be the scene's `narration` verbatim, or lightly rephrased for TTS delivery).
- `durationSec` — estimated spoken duration in seconds, calibrated to the scene's target duration.

The output conforms to the `audioPlan` DataType:

```
{
  segments: [{
    sceneId:     string,     // matches scene.index (as string)
    text:        string,
    voice:       string,
    durationSec: float
  }]
}
```

**Stub mode** returns a deterministic plan with one segment per input scene, using a fixed default voice and the scene's `narration` text verbatim.

## Planner hints

- **When to include:** any pipeline that needs narration/voiceover synced to scenes, before a TTS synthesis node or `subtitleFormatter`.
- **When to skip:** when audio is provided externally, or when the video has no spoken narration (music-only or silent).
- **Knobs the planner should tune:** none — this node is mechanical. Voice style and provider choices are for the downstream TTS synthesis node (to be implemented).

## Edge cases

- Empty `scenes` list — return an `audioPlan` with an empty `segments` array (do not fail; some pipelines may conditionally feed zero scenes).
- Scenes with empty `narration` — include the segment but with empty `text`; the TTS synthesis node will handle the no-speech case.
- `durationSec` assigned by LLM may exceed scene's `durationSeconds` — the synthesis node and video composer handle timing reconciliation; this node only plans.

## Implementation notes

- This node receives `list[scene]` on its `scenes` port. It processes the entire list in a single LLM call, not per-item. Do not connect this node's `scenes` port to a single-scene auto-iteration pattern.
- The LLM call is structured: pass all scenes as a JSON array, request a matching JSON array of segment objects.
- Cache per `(node_type, version, config_hash, input_hash)` where `input_hash` covers the full scenes list.
- TTS synthesis (converting the plan's text + voice to an `audioAsset`) is a separate node not yet specified. This node's output is the plan only.
