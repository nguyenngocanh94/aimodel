# Final Export

**type:** `finalExport`
**category:** `Output`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

Terminal node for video-output workflows. Reads the completed video asset and synthesizes an export metadata record, signalling that the run has produced a deliverable output.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `video` | `videoAsset` | false | yes | The completed video asset to export. |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `exported` | `json` | false | Export metadata: `{exportedAt, format, duration, resolution, status}`. |

## Config

_None._

## Behavior

`execute()` reads `ctx.inputs['video']` (a `videoAsset` payload) and constructs an export metadata object:

```
{
  exportedAt:  string,    // ISO 8601 timestamp at execution time
  format:      string,    // derived from video URL extension, e.g. "mp4"
  duration:    float,     // videoAsset.duration
  resolution:  string,    // videoAsset.resolution
  status:      "exported"
}
```

No external API calls. No LLM calls. No storage writes — the video URL is already persisted upstream. This node is purely a metadata synthesizer and pipeline terminator.

In stub mode the behavior is identical; input is a stub `videoAsset` and output is a canned metadata record.

## Planner hints

- **When to include:** as the terminal node of any workflow that produces a video and needs to record the export event (e.g., for run reporting, audit trail, or downstream webhook triggers).
- **When to skip:** never in a video-output workflow context. If the pipeline terminates with `telegramDeliver` instead, `finalExport` can be omitted — `telegramDeliver` provides its own delivery confirmation.

## Edge cases

- `video.url` does not have a recognisable file extension — default `format` to `"mp4"` rather than failing.
- `video.duration` is null or zero — include the value as-is; do not substitute a default.

## Implementation notes

- `exportedAt` should be set at execution time using the system clock, not derived from the video asset's metadata.
- Format detection from URL: use a simple split on `.` and take the last segment, stripped of query params. If ambiguous, default to `"mp4"`.
- No caching benefit here — this node is a terminal and its output is not consumed by other nodes in the same run.
- This node has no config and no external dependencies; it should be trivial to implement and a good candidate for the very first node written after the runner infrastructure is ready.
