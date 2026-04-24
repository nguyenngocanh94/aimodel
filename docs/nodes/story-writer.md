# Story Writer

**type:** `storyWriter`
**category:** `Script`
**vibe impact:** `Critical`
**human gate:** yes

## Purpose

Generates a shot-by-shot story arc from product analysis, trend data, and/or a seed idea, then suspends the run for human approval before downstream nodes execute. Used for character-driven, emotionally resonant short-video formats.

## Inputs

| key | data_type | multiple | required | description |
|---|---|---|---|---|
| `productAnalysis` | `json` | false | no | Output from `productAnalyzer`. Informs product references, mood, selling points. |
| `trendBrief` | `json` | false | no | Output from `trendResearcher`. Informs format, hashtags, cultural moments. |
| `modelRoster` | `json` | false | no | Cast/talent roster (to be specified). Informs character selection for shots. |
| `seedIdea` | `text` | false | no | Freetext seed concept from the user or upstream node. |

## Outputs

| key | data_type | multiple | description |
|---|---|---|---|
| `storyArc` | `json` | false | Full story arc (see Behavior). Only emitted after human approval. |

## Config

| key | type | default | validation | description |
|---|---|---|---|---|
| `provider` | string | `"stub"` | required | LLM provider. |
| `apiKey` | string | `""` | — | API key. |
| `model` | string | `"gpt-4o"` | — | Model to use. |
| `targetDurationSeconds` | int | `30` | 15–120 | Target story duration in seconds. Controls shot count. |
| `storyFormula` | enum | `"problem_agitation_solution"` | `hero_journey` \| `problem_agitation_solution` \| `before_after_transformation` \| `day_in_life` \| `social_proof_story` \| `emotional_hook` | Narrative formula used as the structural skeleton. |
| `emotionalTone` | enum | `"relatable_humor"` | `aspirational` \| `relatable_humor` \| `nostalgic` \| `empowering` \| `fomo_urgency` \| `warm_family` | vibe-linked | Sets the emotional register for the story. |
| `productIntegrationStyle` | enum | `"natural_use"` | `subtle_background` \| `natural_use` \| `hero_moment` \| `transformation_reveal` \| `comparison_story` | Controls how the product appears in the story. |
| `genZAuthenticity` | enum | `"high"` | `low` \| `medium` \| `high` \| `ultra` | vibe-linked | Controls how Gen-Z-native the dialogue and scenarios feel. |
| `vietnameseDialect` | enum | `"neutral"` | `northern` \| `central` \| `southern` \| `neutral` | Tunes dialogue phrasing for regional Vietnamese dialect (relevant when `language` is `vi`). |
| `recallPreviousStory` | bool | `true` | — | When true, calls `ctx.recall()` to retrieve the previous story arc for this workflow (7-day TTL) and passes it to the LLM for continuity. |
| `messageTemplate` | string | `""` | — | Human-gate message template. Supports `{{var}}` substitution from the story arc. |
| `channel` | enum | `"ui"` | `ui` \| `telegram` \| `mcp` \| `any` | Delivery channel for the human-gate proposal. |
| `timeoutSeconds` | int | `0` | 0–86400 | Seconds before auto-fallback. 0 = wait indefinitely. |
| `botToken` | string | `""` | — | Required when `channel=telegram`. |
| `chatId` | string | `""` | — | Required when `channel=telegram`. |

## Behavior

`execute()` optionally calls `ctx.recall("story_arc", ttl_days=7)` to retrieve the previous story arc for this workflow instance when `recallPreviousStory=true`. This gives the LLM continuity context to avoid repeating the same story.

The node issues a cached structured LLM call with a system prompt that encodes `storyFormula`, `emotionalTone`, `genZAuthenticity`, `vietnameseDialect`, and product/trend context from the inputs. The LLM returns a story arc:

```
{
  title:        string,
  theme:        string,
  hook:         string,
  shots: [{
    shotNumber:      int,
    timestamp:       string,    // e.g. "0:00-0:05"
    description:     string,
    dialogue:        string,
    emotion:         string,
    setting:         string,
    cameraDirection: string
  }],
  cast: {
    lead:       string,
    supporting: string[]
  },
  toneDirection:    string,
  soundDirection:   string,
  productMoment:    string
}
```

The story arc is formatted as a human-readable markdown summary and attached to a `HumanProposal`. The node then calls `ctx.human.propose(proposal)`, which raises `ReviewPendingException`. The run suspends.

On resume, `ctx.inputs['_humanResponse']` contains the human's approval or rejection. If approved, `execute()` calls `ctx.remember("story_arc", storyArc, ttl_days=7)` and emits `storyArc` on the output port. If rejected, the behavior is to be specified (likely re-run with feedback or fail).

**Stub mode** returns a canned story arc and still raises `ReviewPendingException` (human gate is unconditional).

## Planner hints

- **When to include:** `raw_authentic` or `funny_storytelling` vibe; any workflow where a character-driven shot list is the creative foundation.
- **When to skip:** `clean_education` or `aesthetic_mood` vibes, which are better served by `scriptWriter`; any fully-automated flow with no human approval step.
- **Knobs the planner should tune:**
  - `emotionalTone` — vibe-linked: `funny_storytelling` → `relatable_humor`; `raw_authentic` → `empowering` or `nostalgic`; FOMO campaigns → `fomo_urgency`.
  - `genZAuthenticity` — vibe-linked: youth TikTok → `ultra`; mature/premium → `low`.
  - `storyFormula` — match to campaign type: `before_after_transformation` for product demos; `day_in_life` for lifestyle; `social_proof_story` for testimonial-style.
  - `productIntegrationStyle` — `hero_moment` for product-first briefs; `subtle_background` for vibe-first.
  - `channel` — set to `telegram` for Telegram-based approval flows; requires `botToken` and `chatId`.

## Edge cases

- All four inputs are optional — if none are provided, the LLM works from `seedIdea` alone (or generates freely, which may produce generic output).
- `timeoutSeconds > 0` with no human response: use `autoFallbackResponse` if provided, otherwise fail the run. Auto-fallback for story approval is to be specified.
- `recallPreviousStory=true` on first run (no prior story in memory) — `ctx.recall()` returns null; node proceeds without prior context, no error.
- Human rejects the story — to be specified; likely re-generates with the rejection reason as additional input.

## Implementation notes

- The human-gate suspension/resume flow follows the generic pattern in `../workflow.md` §7.2 rule 5. Do not implement a custom wait loop — raise `ReviewPendingException` and let the runner handle persistence.
- `ctx.remember` must be called **after** approval, not before — so the recalled value on the next run reflects an approved story, not a rejected draft.
- The markdown formatting step (for the proposal) is separate from the structured LLM call. The structured call produces the JSON; a second lightweight formatting step produces the readable text for the proposal payload.
- Shot count should be calibrated to `targetDurationSeconds`: roughly one shot per 3–5 seconds is typical for TikTok-style content.
