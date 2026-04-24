# Prompt Refiner

**type:** `promptRefiner`
**category:** `Script`
**vibe impact:** `Critical`
**human gate:** no

## Purpose

Converts a single visual scene into a polished image-generation prompt. Designed as a 1:1 transform; runtime auto-iteration handles per-scene concurrency when a list of scenes arrives from upstream.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `scene` | `scene` | false | yes | One visual scene to convert into an image-gen prompt. |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `prompt` | `prompt` | false | One image-generation prompt derived from the input scene. |

## Behavior

`execute()` issues an LLM call that takes the incoming `scene` (`visualDescription`, `description`, `narration`, `durationSeconds`) plus the style config and returns a single image-generation prompt string.

The prompt is constructed to be self-contained: it incorporates the scene's visual cues, the configured `imageStyle`, `visual_polish`, and `mood_palette` directives, and formats the result as a concise, high-specificity text prompt suitable for image generation backends (Stable Diffusion, DALL-E, Flux, etc.).

Cross-scene style consistency is achieved not by passing prior prompts into each invocation, but by keeping `imageStyle`, `visual_polish`, and `mood_palette` constant in config — since all auto-iterated sub-invocations share the same config hash, every scene gets the same stylistic framing.

**Stub mode** returns a deterministic templated concatenation: `"{imageStyle}, {scene.visualDescription or scene.description}, {aspectRatio}, {mood_palette}"`.

> **Note:** Wan-specific prompt formatting (formulas, character tags, shot markers, sound cues) has moved to the `wanPromptFormatter` node. This node is for generic image-gen pipelines only.

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `imageStyle` | string | `"cinematic, high quality, photorealistic"` | max 200 chars | Base style directive prepended to every generated prompt. |
| `aspectRatio` | enum | `"16:9"` | `1:1` \| `16:9` \| `9:16` \| `4:3` | Target aspect ratio. Included as a directive in the prompt. |
| `detailLevel` | enum | `"standard"` | `minimal` \| `standard` \| `detailed` | Controls how much compositional detail the LLM adds beyond the scene description. `minimal` = nearly verbatim; `detailed` = full art-direction expansion. |
| `provider` | string | `"stub"` | required | LLM provider. |
| `apiKey` | string | `""` | — | API key. |
| `model` | string | `"gpt-4o"` | — | Model to use. |
| `visual_polish` | enum | `"natural_clean"` | `raw_authentic` \| `natural_clean` \| `polished_minimal` \| `hyper_polished` | vibe-linked | Controls how heavily the LLM elevates the visual aesthetic in the prompt. |
| `mood_palette` | enum | `"neutral"` | `warm` \| `cool` \| `neutral` \| `high_contrast` \| `pastel` \| `moody` | vibe-linked | Colour/mood palette directive included in the prompt. |

## Planner hints

- **When to include:** before an image generator (`imageGenerator`) in any pipeline that uses scene splitting. Wired as: `sceneSplitter.scenes → promptRefiner.scene` (runtime auto-iterates).
- **When to skip:** when prompts are authored by hand (wire a `userPrompt` node directly to `imageGenerator`), or when the downstream generator is Wan 2.7 — use `wanPromptFormatter` instead.
- **Knobs the planner should tune:**
  - `visual_polish` — vibe-linked: `raw_authentic` → `raw_authentic`; `aesthetic_mood` → `hyper_polished`; `clean_education` → `polished_minimal`; `funny_storytelling` → `natural_clean`.
  - `mood_palette` — vibe-linked: warm product shots → `warm`; dark/dramatic → `moody`; fashion/beauty → `pastel` or `high_contrast`.
  - `imageStyle` — planner may override with a specific style directive from the user's brief.
  - `aspectRatio` — match to target platform (TikTok → `9:16`, YouTube → `16:9`).

## Edge cases

- A scene with no `visualDescription` and minimal `description` may produce a low-quality prompt. The node does not fail — it uses whatever is available.
- Auto-iteration: when `sceneSplitter` emits 5 scenes, the runtime runs 5 sub-invocations of this node concurrently (up to `max_concurrency`). Each sub-invocation is independently cached. See `../workflow.md` §5.
- This node does not validate that the output prompt will produce a good image — that's the image generator's concern.

## Implementation notes

- Each sub-invocation in auto-iteration is cached independently per `(node_type, version, config_hash, input_hash)` where `input_hash` is the hash of the individual scene. Retrying one scene does not invalidate others.
- The LLM call is relatively cheap (short input, short output) — keep the system prompt focused and avoid unnecessary chain-of-thought reasoning.
- Do not pass previous scenes' prompts into the current call. Style consistency comes from config, not from cross-scene context — this keeps the node truly stateless and cache-safe.
- Output is a `prompt` DataType, which is a string with optional metadata. At minimum, set `prompt.text`. Optionally include `{style, aspectRatio}` as metadata for downstream debugging.
