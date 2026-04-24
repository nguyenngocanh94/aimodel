# Product Analyzer

**type:** `productAnalyzer`
**category:** `Input`
**vibe impact:** `Neutral`
**human gate:** no

## Purpose

Accepts one or more product images and an optional text description, calls a vision-capable LLM, and returns a structured product analysis JSON used by downstream creative nodes (story writer, script writer, trend researcher).

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `images` | `imageAsset` | true | yes | Product images to analyze. At least one image required. |
| `description` | `text` | false | no | Optional freetext product description to supplement the images. |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `analysis` | `json` | false | Structured product analysis (see Behavior). |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `provider` | string | `"stub"` | required | LLM provider (`openai`, `anthropic`, `stub`). Must be vision-capable. |
| `apiKey` | string | `""` | — | API key for the selected provider. |
| `model` | string | `"gpt-4o"` | — | Vision-capable model to use. |
| `analysisDepth` | enum | `"detailed"` | `basic` \| `detailed` | Controls how many fields the LLM is asked to fill in and the depth of its reasoning. |
| `analysis_angle` | enum | `"neutral"` | `neutral` \| `entertainment_ready` \| `education_ready` \| `aesthetic_ready` | Tilts the LLM system prompt and output wording toward a downstream creative direction. |

## Behavior

`execute()` collects the image URLs from all `imageAsset` items in `ctx.inputs['images']`, optionally appends `ctx.inputs['description']`, and issues a structured LLM call with a product-analysis system prompt.

The prompt instructs the LLM to return a JSON object with these fields:

```
{
  productType:        string,
  productName:        string,
  colors:             string[],
  materials:          string[],
  style:              string,
  sellingPoints:      string[],
  targetAudience:     string,
  pricePositioning:   string,       // budget / mid / premium / luxury
  suggestedMood:      string
}
```

`analysisDepth=basic` asks for only the top fields (`productType`, `productName`, `colors`, `sellingPoints`). `analysisDepth=detailed` asks for all fields.

`analysis_angle` modifies the system prompt preamble — e.g., `entertainment_ready` primes the LLM as a social-video content strategist; `aesthetic_ready` primes it as a visual art director. The output JSON schema is the same regardless.

**Stub mode** (provider=`stub` or no API key): returns canned data with sensible placeholder values so downstream nodes can be tested without a real LLM call.

## Planner hints

- **When to include:** any workflow that starts from product images and needs downstream content (story, script, trend brief) informed by the product's characteristics.
- **When to skip:** product-agnostic workflows (general trend research, user-prompt-only flows).
- **Knobs the planner should tune:** `analysis_angle` — align with the workflow's creative vibe (`entertainment_ready` for story/funny flows, `education_ready` for how-to flows, `aesthetic_ready` for mood/visual flows, `neutral` when no clear tilt).

## Edge cases

- Empty `images` list with no `description` — fail fast with a validation error before calling the LLM.
- LLM returns malformed JSON — retry once; on second failure surface an error with the raw LLM response in the error payload.
- Images with very low resolution may produce poor analysis; the node does not resize — caller is responsible for supplying reasonable-quality images.

## Implementation notes

- Use `ctx.llm` with the configured provider/model; do not instantiate an LLM client directly.
- All image URLs should already be accessible to the LLM (stored via `ctx.storage`). Do not pass raw binary; pass URLs.
- Cache key includes `(config_hash, input_hash)` per the generic caching rules in `../workflow.md` §7.2. For the same product images and config, the analysis result can be reused across runs.
- The stub response should be deterministic (not random) so tests are reproducible.
