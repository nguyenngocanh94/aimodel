# Wan Prompt Formatter

**type:** `wanPromptFormatter`
**category:** `Script`
**vibe impact:** `Critical`
**human gate:** no

## Purpose

Converts a story narrative into a single Wan 2.7-formatted multi-shot prompt string, complete with character tags, shot markers, and optional sound cues. This is the dedicated pre-processing step for Wan R2V pipelines; it replaces the old Wan mode that was previously embedded in `promptRefiner`.

> **New node:** no prior implementation exists. This is the first documented spec.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `story` | `text` | false | yes | Narrative prose or story arc description to convert into a Wan-formatted prompt. |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `wanPrompt` | `wanPrompt` | false | Wan-formatted prompt payload: `{prompt, formula, characterTags[], includeSound}`. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `provider` | string | `"stub"` | required | LLM provider for the formatting call. |
| `apiKey` | string | `""` | — | API key. |
| `model` | string | `"gpt-4o"` | — | Model to use. |
| `wanFormula` | enum | `"advanced"` | `basic` \| `advanced` \| `r2v` \| `multiShot` \| `sound` | Selects the Wan prompt formula/template schema. Each formula uses a different system prompt specialised for that Wan mode. |
| `wanAspectRatio` | enum | `"9:16"` | `16:9` \| `9:16` \| `1:1` | Target aspect ratio. Included as a directive in the Wan prompt. |
| `characterTags` | list[string] | `[]` | — | Character reference tags to embed in the prompt (e.g., `["char_lead_01", "char_friend_02"]`). These map to Wan's reference-image character IDs. |
| `includeSound` | bool | `false` | — | When true, the LLM adds `[SOUND: ...]` cue markers in the prompt string per the Wan sound formula. |
| `visual_polish` | enum | `"natural_clean"` | `raw_authentic` \| `natural_clean` \| `polished_minimal` \| `hyper_polished` | vibe-linked | Tunes the visual quality directives embedded in the Wan prompt. |
| `mood_palette` | enum | `"neutral"` | `warm` \| `cool` \| `neutral` \| `high_contrast` \| `pastel` \| `moody` | vibe-linked | Colour/mood directive embedded in the Wan prompt. |

## Behavior

`execute()` selects a formula-specific system prompt based on `wanFormula`:

- `basic` — minimal Wan format: single-shot style prompt with quality tags.
- `advanced` — multi-marker format with shot transitions, quality tags, and visual polish directives.
- `r2v` — reference-to-video optimised: includes character tags and reference anchoring cues.
- `multiShot` — explicit `[SHOT N]` marker blocks, one per story beat; calibrated to `wanAspectRatio`.
- `sound` — extends `multiShot` with `[SOUND: ...]` markers for each shot.

The LLM call takes `story` (the input narrative) and returns a structured response. The node extracts the `prompt` string, then assembles the `wanPrompt` payload:

```
{
  prompt:         string,      // the Wan-formatted prompt string
  formula:        string,      // the wanFormula value used
  characterTags:  string[],    // from config.characterTags
  includeSound:   bool         // from config.includeSound
}
```

`characterTags` from config are injected into the Wan prompt string at positions determined by the formula (e.g., prefixed as `[CHAR:char_lead_01]` before their first scene appearance). The exact injection syntax is formula-specific and should follow Wan 2.7's documented prompt conventions.

`visual_polish` and `mood_palette` are translated to Wan-compatible quality and style tags (e.g., `hyper_polished` → `masterpiece, ultra-detailed, professional cinematography`).

**Stub mode** returns a deterministic placeholder Wan prompt string with the configured formula and character tags embedded.

## Planner hints

- **When to include:** when the downstream video generator is `wanR2V`. Wire as: `storyWriter.storyArc → [text conversion] → wanPromptFormatter.story → wanR2V.prompt`. (If `storyWriter` emits `json`, the workflow may need a light adapter; to be specified.)
- **When to skip:** generic image-gen pipelines — use `promptRefiner` instead.
- **Knobs the planner should tune:**
  - `wanFormula` — `r2v` for character-consistent video; `multiShot` for complex scene sequences; `sound` if the Wan generation should include audio cues; `basic` for quick prototypes.
  - `visual_polish` — vibe-linked: same mapping as `promptRefiner`.
  - `mood_palette` — vibe-linked: same mapping as `promptRefiner`.
  - `characterTags` — populated from the model roster or casting framework upstream; empty list is valid for no character references.
  - `includeSound` — only set true if the Wan provider/model supports sound generation.
  - `wanAspectRatio` — match to target platform (TikTok → `9:16`).

## Edge cases

- `story` is empty or very short — the LLM will produce a minimal prompt. The node does not fail but output quality will be low.
- `characterTags` references tags that are not registered in the Wan reference-image system — the prompt will contain the tags but the video generator may ignore or error on them. Validation of tag existence is out of scope for this node.
- `includeSound=true` with `wanFormula` other than `sound` — the node should either switch to `sound` formula automatically or append sound cues as a best-effort overlay. Behavior to be specified.

## Implementation notes

- The `wanPrompt` DataType carries `{prompt, formula, characterTags[], includeSound}` — the output port is typed `wanPrompt`, not `text`. Downstream `wanR2V` expects this type.
- Character tag injection into the prompt string should follow Wan 2.7's documented syntax. Keep the injection logic in a small helper function so it can be updated when Wan's format evolves.
- Use a separate system prompt string per `wanFormula` value. Store these in a constants module (not inline in execute()) so they can be updated independently of the node logic.
- This node has no prior implementation — refer to Wan 2.7 documentation for prompt format conventions when writing the formula system prompts.
