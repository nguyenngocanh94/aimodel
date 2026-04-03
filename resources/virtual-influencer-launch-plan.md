# AI Virtual Influencer Launch Plan

> **Status:** Active
> **Created:** 2026-04-03
> **Bead:** AiModel-up0
> **Sub-beads:** AiModel-2ut (Persona), AiModel-cyn (Toolset), AiModel-onm (Channels), AiModel-4g9 (Content), AiModel-fh2 (Agent Skills)

---

## 1. Vision

Build and grow a **single AI-generated virtual influencer** that publishes short-form video content across TikTok, YouTube Shorts, and Facebook Reels. The influencer is fully AI-generated (images, video, voice) and managed by an AI agent workflow.

**Core thesis:** A virtual influencer with a consistent, well-crafted persona can grow a real audience by publishing high-quality, culturally-localized content at a pace no human creator can match.

**V1 success = 1,000 followers on one platform within 60 days of first post.**

---

## 2. Target Market

### Primary: Vietnam / Southeast Asia

**Why Vietnam first:**
- High social media penetration (78M users, 73% of population)
- TikTok and Facebook dominate; YouTube Shorts growing fast
- Virtual influencer/KOL concept is emerging but not saturated
- Content production cost advantage (localized content, less competition from Western AI influencers)
- The marketing-strategy skill already has deep Vietnam cultural localization (Gen Z slang, VinaHouse trends, "phen" vs "sang" contrast)

**Target audience persona:**
- Age: 18-28, Vietnamese Gen Z
- Platforms: TikTok (primary), Facebook Reels (secondary), YouTube Shorts (tertiary)
- Interests: Fashion, lifestyle, tech, music, cafe culture
- Consumption: Short-form vertical video (15-60 seconds)
- Language: Vietnamese with some English mixed in (bilingual flex)

### Secondary (Phase 2): Global/English

Expand to English-language content once the workflow is proven and the character has a visual identity bank.

---

## 3. Persona Direction (guides AiModel-2ut)

### Character Archetype: "The Aesthetic Explorer"

A young woman who explores cities, cafes, fashion, and culture through a cinematic lens. She doesn't sell products directly — she sells a *vibe*. Think: if a lo-fi playlist became a person.

**Key persona decisions for AiModel-2ut to finalize:**

| Attribute | Direction | Notes |
|-----------|-----------|-------|
| Name | Vietnamese name, easy to say globally | e.g., "Linh", "Mai", "Ha" — avoid English-only names |
| Age | Early 20s | Relatable to target demographic |
| Ethnicity/Look | Vietnamese | Textual DNA via image-prompting skill |
| Niche | Lifestyle + Fashion + City exploration | 3-pillar niche per marketing-strategy skill |
| Personality | Curious, warm, slightly introverted, witty captions | Not "perfect influencer" — relatable |
| Backstory | Light lore — art student, moved to Saigon, documents her life | Enables storytelling, lore-building content pillar |
| Visual style | Modern street fashion, earth tones, natural makeup | Consistent across all generated images |
| Voice | Soft, warm Vietnamese female voice | ElevenLabs or equivalent TTS |
| Language | Vietnamese primary, occasional English | Bilingual captions |

**Textual DNA (draft — AiModel-2ut refines):**
> Early-20s Vietnamese female, oval face with soft features, almond-shaped dark brown eyes, small nose, naturally full lips, long straight black hair with subtle highlights, slim build. Natural makeup, earth-tone wardrobe.

---

## 4. Content Strategy Framework (guides AiModel-4g9)

### Content Pillars (from marketing-strategy skill)

1. **Viral/Hook (40%)** — Trend-jacking, aesthetic transitions, satisfying visuals
2. **Community/Nurture (35%)** — "Day in my life", Q&A, lore-building, cafe reviews
3. **Conversion/Brand (25%)** — Subtle lifestyle showcasing, outfit breakdowns, "what I use"

### Content Formats

| Format | Duration | Frequency | Platform |
|--------|----------|-----------|----------|
| Aesthetic transition reel | 15-30s | 3x/week | TikTok, Reels |
| "Day in my life" vlog | 30-60s | 2x/week | TikTok, YouTube Shorts |
| Outfit/look showcase | 15-20s | 2x/week | TikTok, Reels |
| Cafe/location review | 30-45s | 1x/week | YouTube Shorts, TikTok |
| Trend response/duet | 15-30s | 1-2x/week | TikTok |

### Production Pipeline (per video)

```
1. Script (text description of scene)
   └─ marketing-strategy skill → trending topic + cultural angle
2. Image generation (key frames)
   └─ image-prompting skill → SAPELT prompts → Gemini/Midjourney
3. Video generation (animate key frames)
   └─ video-prompting skill → SAPELTC prompts → Runway/Kling
4. Voice synthesis (if narration needed)
   └─ ElevenLabs or equivalent
5. Composition (stitch clips, add music, captions)
   └─ CapCut or FFmpeg pipeline
6. Publish
   └─ Manual initially, automated later via agent skills
```

### Publishing Cadence

- **Week 1-2:** 3 posts total (testing pipeline, refining character consistency)
- **Week 3-4:** 5 posts/week (ramp up, find what resonates)
- **Week 5+:** 7-10 posts/week (full cadence, data-driven optimization)

---

## 5. AI Toolset Recommendations (guides AiModel-cyn)

### Image Generation

| Tool | Use Case | Priority |
|------|----------|----------|
| **Google Gemini (Imagen 3)** | Primary image gen — best photorealism, free tier available | P0 |
| **Midjourney v6** | Stylized/artistic shots, fashion lookbooks | P1 |
| **Flux (local)** | Backup, no API limits, character consistency via LoRA | P2 |

**Character consistency strategy:**
1. Start with Textual DNA (text-based consistency via SAPELT)
2. Generate 20-30 reference images, curate best 10
3. If needed: train a LoRA on the curated set for Flux/SD

### Video Generation

| Tool | Use Case | Priority |
|------|----------|----------|
| **Kling 1.6** | Primary video gen — best motion realism, image-to-video | P0 |
| **Runway Gen-3** | Secondary — strong camera control, 4s clips | P1 |
| **Vidu** | Stylized/artistic video, reference image support | P2 |

### Voice

| Tool | Use Case | Priority |
|------|----------|----------|
| **ElevenLabs** | Vietnamese female voice clone/generation | P0 |
| **Fish Audio** | Alternative, supports Vietnamese | P1 |

### Composition

| Tool | Use Case | Priority |
|------|----------|----------|
| **CapCut** | Quick editing, auto-captions, trending effects | P0 |
| **FFmpeg pipeline** | Automated stitching for batch production | P1 |

---

## 6. Platform Strategy (guides AiModel-onm)

### TikTok (Primary)

- Account name: matches character name
- Bio: Vietnamese, personality-driven, include "AI" disclosure
- Profile pic: best generated portrait
- Link: eventually to YouTube or a Linktree
- Hashtag strategy: mix of trending VN hashtags + niche tags
- **Disclosure:** All posts tagged with #AIGenerated or #VirtualInfluencer per platform policy

### YouTube Shorts (Secondary)

- Same branding as TikTok
- Longer descriptions for SEO
- Playlists by content pillar
- Community tab for engagement

### Facebook Reels (Tertiary)

- Same content cross-posted
- Facebook page (not personal profile)
- Leverage Facebook Groups in Vietnam for distribution

### Branding Consistency

- Same profile picture across all platforms (generated via image-prompting)
- Same color palette in thumbnails/covers
- Same bio tone and structure
- Consistent posting handle format

---

## 7. Launch Phases

### Phase 0: Foundation (current — AiModel-up0)
- [x] Create skills: image-prompting, video-prompting, marketing-strategy
- [ ] Write this launch plan (this document)
- [ ] Define persona (AiModel-2ut)
- [ ] Select toolset (AiModel-cyn)

### Phase 1: Character Bootstrap (Week 1-2)
- Generate Textual DNA from a reference concept
- Produce 20-30 character images across settings
- Curate top 10 as the "identity bank"
- Generate 3 test video clips
- Set up social media accounts (AiModel-onm)
- **Gate:** Character looks consistent across 10+ images

### Phase 2: Content Pipeline Validation (Week 3-4)
- Write scripts for first 5 videos (AiModel-4g9)
- Produce and publish 3 videos
- Measure: views, watch time, completion rate
- Iterate on what works
- **Gate:** Full pipeline runs end-to-end in under 2 hours per video

### Phase 3: Growth Sprint (Week 5-12)
- 7-10 posts/week
- A/B test content pillars
- Engage with comments (AI-assisted)
- Collaborate/duet with other creators
- Track metrics weekly
- **Gate:** 1,000 followers on one platform

### Phase 4: Automation & Scale (Week 13+)
- Build agent skills for automated pipeline (AiModel-fh2)
- Batch content production
- Multi-platform scheduling
- Revenue exploration (brand deals, affiliate)

---

## 8. Success Metrics

### North Star
**1,000 followers on TikTok within 60 days of first post**

### Leading Indicators (weekly tracking)

| Metric | Target (Week 4) | Target (Week 8) |
|--------|-----------------|-----------------|
| Posts published | 15 total | 50 total |
| Average views per post | 500 | 2,000 |
| Follower count (TikTok) | 100 | 500 |
| Watch completion rate | >40% | >50% |
| Comments per post | >5 | >15 |
| Pipeline time per video | <3 hours | <1.5 hours |

### Lagging Indicators
- Follower growth rate (week over week)
- Engagement rate (likes + comments / views)
- Cross-platform audience overlap
- Content pillar performance (which type drives growth)

---

## 9. Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Character inconsistency across images | Audience doesn't recognize character | Textual DNA + curated identity bank + potential LoRA |
| Platform bans AI-generated content | Lose the account | Disclose upfront, follow platform policies, diversify platforms |
| Video quality too low / uncanny valley | Low engagement, negative comments | Start with image-forward content (slideshows with music), graduate to video |
| Vietnamese cultural missteps | Backlash, unfollows | Use marketing-strategy skill for cultural localization, get human review |
| API costs escalate | Unsustainable production | Use free tiers first (Gemini), local models (Flux) as backup |
| Content feels generic/soulless | No audience connection | Strong persona with backstory, community content pillar, respond to trends |

---

## 10. Bead Execution Order

Based on the dependency graph:

```
1. AiModel-up0  ← THIS DOCUMENT (strategy anchor)
2. AiModel-2ut  ← Define persona using Section 3 above
3. AiModel-cyn  ← Select toolset using Section 5 above (depends on 2ut)
4. AiModel-onm  ← Setup channels using Section 6 above (depends on 2ut)
5. AiModel-4g9  ← Write pilot scripts using Section 4 above (depends on 2ut + cyn)
6. AiModel-fh2  ← Build agent skills for automation (Phase 4)
```

Each sub-bead should reference this document as its strategic context.

---

## 11. What This Plan Does NOT Cover

- Monetization strategy (Phase 4+)
- Legal entity / business setup
- Paid advertising / promotion budget
- Multi-character expansion
- Real-time livestreaming (future capability)

These are deferred intentionally. Ship the first post before optimizing the business model.
