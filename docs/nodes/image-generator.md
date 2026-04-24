# Image Generator

**type:** `imageGenerator`
**category:** `Visuals`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

Generates one or more images by calling an image-generation provider. Accepts either a text prompt or a scene as input, and supports single or multiple output modes for different pipeline shapes.

## Inputs

Active ports depend on `config.inputMode`:

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `prompt` | `prompt` | false | no | Used when `inputMode=prompt`. A text generation prompt. |
| `scene` | `scene` | false | no | Used when `inputMode=scene`. Runtime auto-iterates when a list arrives from upstream. |

Exactly one of `prompt` or `scene` should be connected, matching `inputMode`. The other port is inactive.

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `image` | `imageAsset` | configurable | Single asset when `outputMode=single` (`multiple=False`); list of assets when `outputMode=multiple` (`multiple=True`). Same port key in both cases — `active_ports()` returns the correct `multiple` flag for the configured mode. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `inputMode` | enum | `"prompt"` | `prompt` \| `scene` | Selects which input port is active. |
| `outputMode` | enum | `"single"` | `single` \| `multiple` | `single` → one image output. `multiple` → N variation images from the same prompt. |
| `image.provider` | string | `"stub"` | `fal` \| `replicate` \| `openai-dalle` \| `stub` | Image generation provider. |
| `image.model` | string | provider default | — | Model/pipeline identifier, provider-specific (e.g., `fal-ai/flux/dev`, `stability-ai/sdxl`). |

## Behavior

`execute()` routes based on `inputMode` and `outputMode`:

**`inputMode=prompt`, `outputMode=single`:** takes `ctx.inputs['prompt']`, sends one generation request, writes the result to `ctx.storage`, returns one `imageAsset` on the `image` port.

**`inputMode=prompt`, `outputMode=multiple`:** sends one prompt with N variation requests (provider-specific batch call or N sequential calls), returns a list of `imageAsset` values on `image` (`multiple=True`).

**`inputMode=scene`, `outputMode=single`:** extracts `scene.visualDescription or scene.description` as the prompt text, generates one image, returns one asset. When wired to a `multiple=True` scenes port upstream, the runtime auto-iterates this node per scene — each sub-invocation sees one scene and produces one image.

**`inputMode=scene`, `outputMode=multiple`:** generates N variations from the first scene's description.

Each generated image is persisted via `ctx.storage`. The `imageAsset` payload is `{url, width, height, seed, metadata}` where `url` is the stored URL returned by `ctx.storage`.

**Stub mode** returns a deterministic placeholder `imageAsset` with a synthetic URL.

## Planner hints

- **When to include:** any pipeline that needs visual assets from text prompts or scenes.
- **When to skip:** when images are provided externally (already available as `imageAsset` ports from an upstream trigger), or when video is generated directly via `wanR2V` without an intermediate image-gen step.
- **Knobs the planner should tune:**
  - `inputMode` — `scene` for scene-splitting pipelines (paired with `promptRefiner`); `prompt` for direct prompt-to-image pipelines.
  - `outputMode` — `multiple` only when variation selection is needed (e.g., A/B review). Most pipelines use `single`.
  - `image.provider` — `fal` for production (fast, high quality); `stub` for local development.

## Edge cases

- Both `prompt` and `scene` ports connected simultaneously — `inputMode` determines which is used; the other is ignored.
- Provider rate limits or failures: use `ctx.http`'s retry/failover. Per-item retry semantics apply when auto-iterating.
- `outputMode=multiple` with a provider that only supports single generation — the node must issue multiple sequential calls and collect results; do not error.
- Auto-iteration note (see `../workflow.md` §5): when `inputMode=scene` and `multiple=False` but a scene list arrives from upstream (e.g., from `sceneSplitter`), the runtime auto-iterates. The node itself always receives exactly one scene per invocation. The `outputMode=multiple` case is for one-prompt-to-N-variations, not for list processing.

## Implementation notes

- `active_ports()` must return the correct `multiple` flag on the `image` output port based on `outputMode`. This is one of the few nodes where `active_ports()` diverges from the static `ports()` definition.
- Store images via `ctx.storage` immediately after generation. Do not return raw binary in the payload — return the stored URL.
- Seed handling: if the provider returns a seed, include it in `imageAsset.seed` for reproducibility. For `outputMode=multiple`, each variation will have a distinct seed.
- Provider abstraction: implement a thin adapter per provider (`fal`, `replicate`, `openai-dalle`, `stub`) behind a common interface so adding new providers doesn't require touching `execute()`.
