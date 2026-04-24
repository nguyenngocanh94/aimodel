# Script Writer

**type:** `scriptWriter`
**category:** `Script`
**vibe impact:** `Critical`
**human gate:** no

## Purpose

Takes a generation prompt and produces a fully structured video script with hook, narrative beats, narration copy, and call-to-action. Suited for clean, linear script flows (educational, product explainer, listicle).

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `prompt` | `prompt` | false | yes | The generation prompt describing the video's topic, product, or angle. |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `script` | `script` | false | Structured script: `{title, hook, beats[], narration, cta}`. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `style` | string | `"Clear, conversational narration with concrete examples"` | required, 1–200 chars | Writing style directive appended to the LLM system prompt. |
| `structure` | enum | `"three_act"` | `three_act` \| `problem_solution` \| `story_arc` \| `listicle` | required | Narrative skeleton the LLM follows. |
| `includeHook` | bool | `true` | — | Whether to generate a distinct opening hook beat. |
| `includeCTA` | bool | `true` | — | Whether to generate a call-to-action at the end. |
| `targetDurationSeconds` | int | `90` | 5–600 | Target video duration. The LLM calibrates beat count and narration length accordingly. |
| `provider` | string | `"stub"` | required | LLM provider. |
| `apiKey` | string | `""` | — | API key for the selected provider. |
| `model` | string | `"gpt-4o"` | — | Model to use. |
| `hook_intensity` | enum | `"high"` | `low` \| `medium` \| `high` \| `extreme` | vibe-linked | Controls how punchy/disruptive the opening hook is. |
| `narrative_tension` | enum | `"medium"` | `low` \| `medium` \| `high` | vibe-linked | Controls story arc tension; higher = more conflict/stakes in the beats. |
| `product_emphasis` | enum | `"balanced"` | `subtle` \| `balanced` \| `hero` | vibe-linked | Controls how prominently the product is featured relative to the narrative. |
| `cta_softness` | enum | `"medium"` | `none` \| `soft` \| `medium` \| `hard` | vibe-linked | Controls how overt the CTA copy is. `none` omits CTA even if `includeCTA=true`. |
| `native_tone` | enum | `"conversational"` | `polished` \| `conversational` \| `genz_native` \| `ultra_slang` | vibe-linked | Controls vocabulary and register of the narration copy. |

## Behavior

`execute()` makes a cached structured LLM call with a system prompt that encodes the `style`, `structure` skeleton, and vibe knobs. The user-turn contains the incoming `prompt` value.

The LLM returns a `script` payload:

```
{
  title:     string,
  hook:      string,          // opening line / caption
  beats:     [{ index, title, description, durationSeconds }],
  narration: string,          // full narration copy
  cta:       string | null
}
```

`structure` selects the narrative skeleton:
- `three_act` — setup / confrontation / resolution
- `problem_solution` — pain point → agitation → relief → product
- `story_arc` — character journey with transformation
- `listicle` — N numbered tips/points

`includeHook=false` omits the hook beat and `hook` field (or sets to null). `includeCTA=false` or `cta_softness=none` sets `cta` to null.

Vibe knobs are passed as natural-language directives in the system prompt (e.g., "Hook intensity: extreme — the first line must arrest the viewer in under 2 seconds with maximum pattern interrupt.").

**Stub mode** returns a templated script with placeholder beats based on `structure` and `targetDurationSeconds`.

## Planner hints

- **When to include:** linear, prompt-driven script flows — especially `clean_education` vibe or any flow where a clear narrative structure is needed before scene splitting.
- **When to skip:** when `storyWriter` is used instead (character-driven, shot-by-shot stories with human approval), or when an upstream node already produces a `script`-typed output.
- **Knobs the planner should tune:**
  - `hook_intensity` — vibe-linked: `raw_authentic` / `funny_storytelling` → `extreme`; `clean_education` → `medium`; `aesthetic_mood` → `low`.
  - `narrative_tension` — `funny_storytelling` → `high`; `clean_education` → `low`.
  - `product_emphasis` — `hero` for direct-response flows; `subtle` for brand/mood flows.
  - `cta_softness` — `hard` for performance campaigns; `soft` or `none` for awareness.
  - `native_tone` — `genz_native` or `ultra_slang` for TikTok youth audiences; `polished` for professional contexts.
  - `structure` — match to the brief: `problem_solution` for product demos; `listicle` for educational; `story_arc` for brand storytelling.

## Edge cases

- Empty or very short `prompt` may produce a low-quality script. The node does not validate prompt quality — downstream review nodes handle this.
- `targetDurationSeconds` is a hint to the LLM, not a hard constraint. Actual narration length may vary; TTS/voiceover planning handles the reconciliation.
- Caching: same prompt + same config_hash → same script. Use `ctx.remember` if cross-run story recall is needed (not typically needed here — that's `storyWriter`'s concern).

## Implementation notes

- Use structured output / JSON mode for the LLM call to guarantee schema compliance.
- The `beats` array should have a reasonable count: roughly `targetDurationSeconds / 10` as a soft guide (e.g., 90s → ~9 beats).
- Cache result per `(node_type, version, config_hash, input_hash)` — standard rules from `../workflow.md` §7.2.
- Stream token deltas via `ctx.emit("node.token.delta", {"text": delta})` if the LLM client supports streaming; the runner surfaces this on the SSE channel.
