# Trend Researcher

**type:** `trendResearcher`
**category:** `Script`
**vibe impact:** `Critical`
**human gate:** no

## Purpose

Queries a social-connected LLM to produce a structured trend brief for a specific market, platform, and language. The brief informs downstream creative nodes about what formats, sounds, hashtags, and cultural moments are currently resonating.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `context` | `json` | false | no | Optional upstream context (e.g., product analysis output) to focus the trend query. |
| `topic` | `text` | false | no | Optional freetext topic to narrow the trend search (e.g., "school uniforms", "skincare"). |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `trendBrief` | `json` | false | Structured trend brief (see Behavior). |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `provider` | string | `"stub"` | required | LLM provider. Social-connected models (Grok, Gemini) are preferred for real-time trend awareness. |
| `apiKey` | string | `""` | — | API key for the selected provider. |
| `model` | string | `"grok-3"` | — | Model to use. Grok 3 or Gemini Flash recommended for trend tasks. |
| `market` | enum | `"vietnam"` | `vietnam` \| `global` \| `sea` | required | Target market. Primes the LLM system prompt with regional context. |
| `platform` | enum | `"tiktok"` | `tiktok` \| `youtube` \| `instagram` \| `all` | required | Target platform. Tunes format suggestions. |
| `language` | string | `"vi"` | required | BCP-47 language code. Controls response language for hashtags and copy suggestions. |
| `trend_usage` | enum | `"informed"` | `ignore` \| `informed` \| `leaned_in` \| `fully_on_trend` | vibe-linked | Controls how aggressively the brief emphasizes trend adoption vs. evergreen content. |
| `content_angle_focus` | enum | `"vibe_matched"` | `broad` \| `vibe_matched` \| `entertainment_first` \| `info_first` | — | Shifts the LLM's prioritization of content angle suggestions. |

## Behavior

`execute()` builds a system prompt primed as a market specialist for the configured `market` and `platform`. It optionally incorporates `context` (e.g., product type/name from `productAnalyzer`) and `topic` to focus the query.

The LLM returns a structured JSON trend brief:

```
{
  trendingFormats:    string[],       // e.g. ["POV reveal", "before/after", "duet reaction"]
  trendingHashtags:   string[],
  trendingSounds:     string[],       // sound names or descriptions
  culturalMoments:    string[],       // seasonal events, memes, news hooks
  contentAngles:      string[],       // creative angles to explore
  audienceInsights: {
    tone:       string,
    age:        string,
    interests:  string[],
    behaviors:  string[]
  },
  avoidList:          string[]        // formats or topics to avoid
}
```

`trend_usage` modifies the system prompt emphasis: `ignore` = treat this as a generic creative brief with no trend focus; `informed` = light trend awareness; `leaned_in` = actively adopt trending formats; `fully_on_trend` = maximize trend alignment even at cost of brand safety.

**Stub mode** returns a canned trend brief with Vietnamese TikTok defaults.

## Planner hints

- **When to include:** short-video pipelines where keeping up with platform trends materially improves performance. Especially valuable in `storyWriter` and `scriptWriter` flows.
- **When to skip:** fixed-format content briefs with no social distribution angle, or internal/B2B video where trend signals don't apply.
- **Knobs the planner should tune:**
  - `trend_usage` — vibe-linked: `raw_authentic` vibe → `leaned_in` or `fully_on_trend`; `clean_education` vibe → `informed`; `aesthetic_mood` → `informed`.
  - `market` and `platform` — set from the user's brief.
  - `content_angle_focus` — `entertainment_first` for funny/story vibes, `info_first` for educational vibes.

## Edge cases

- Both `context` and `topic` absent: the LLM operates as a general trend scanner for the market/platform — valid, but less focused. The node should not fail.
- Social-connected models may be unavailable or return stale data; stub mode should always be usable as a fallback for development.
- LLM may hallucinate trend names; downstream nodes treat the brief as creative inspiration, not factual data.

## Implementation notes

- Use `ctx.llm` with the configured `provider`/`model`. Do not hardcode Grok/Gemini — `model` is configurable for a reason.
- The system prompt should establish the persona before the user-turn content. Keep the persona string versioned in code so prompt changes are trackable.
- Apply standard run caching (per `../workflow.md` §7.2). For the same market/platform/topic/config, reuse cached results within a run. The TTL for trend data should be short if the caching layer supports per-entry TTLs (e.g., 24 hours).
- Structured output: use the LLM provider's JSON mode or function-calling to enforce the schema, rather than post-parsing free text.
