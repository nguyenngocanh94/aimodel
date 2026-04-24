# Subtitle Formatter

**type:** `subtitleFormatter`
**category:** `Audio`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

Converts an audio plan into a structured subtitle asset with timed text segments. Uses an LLM for phrasing quality and line-break decisions rather than pure text splitting.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `audioPlan` | `audioPlan` | false | yes | Structured audio plan from `ttsVoiceoverPlanner` (or equivalent). |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `subtitles` | `subtitleAsset` | false | Timed subtitle segments. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `llm.provider` | string | `"stub"` | — | LLM provider. |
| `llm.model` | string | `""` | — | Model to use. Empty string = provider default. |

## Behavior

`execute()` receives the `audioPlan` from `ctx.inputs['audioPlan']` and issues a structured LLM call. The LLM is given each segment's `text`, `sceneId`, and `durationSec`, and asked to produce timed subtitle entries.

The LLM handles:
- Breaking long narration lines into shorter subtitle-friendly phrases (max ~42 chars per line is a common guideline).
- Assigning `start` and `end` times in seconds based on the cumulative `durationSec` values from the audio plan.
- Phrasing adjustments for readability (e.g., avoiding line breaks mid-phrase).

Output conforms to the `subtitleAsset` DataType:

```
{
  segments: [{
    id:    string,      // e.g. "sub-0", "sub-1"
    text:  string,
    start: float,       // seconds
    end:   float        // seconds
  }]
}
```

A deterministic fallback implementation is valid for the stub: split each segment's text at word boundaries to fit within 42 characters, assign start/end times cumulatively from `durationSec`.

**Stub mode** uses the deterministic fallback above.

## Planner hints

- **When to include:** when the output video needs embedded or overlaid subtitles. Place after `ttsVoiceoverPlanner` and before `videoComposer` (if the compositor supports subtitle overlay) or as a terminal node producing a subtitle file.
- **When to skip:** when subtitles are not needed, or when they are provided by an external captioning service.
- **Knobs the planner should tune:** none. LLM choice does not have significant creative impact for subtitle formatting.

## Edge cases

- `audioPlan.segments` is empty — return a `subtitleAsset` with an empty `segments` array.
- Segment with very long `text` — the LLM may produce many sub-segments from one input segment; that is valid and expected.
- Cumulative timing drift: if the sum of `durationSec` values doesn't match the actual audio file duration, timing will be approximate. The video compositor is responsible for final sync; this node only plans.

## Implementation notes

- A deterministic fallback (no LLM call) is acceptable for stub and as a production fallback if the LLM call fails: split on word boundaries at ~42 chars, assign durations proportionally.
- The LLM call should use structured output mode for schema compliance.
- Cache per `(node_type, version, config_hash, input_hash)`. The input hash covers the full `audioPlan` payload.
- No external API calls beyond the LLM; all data is derived from the `audioPlan` input.
