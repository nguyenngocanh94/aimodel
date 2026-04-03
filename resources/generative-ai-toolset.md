# Generative AI Toolset — Linh Nova

Selected tools for each layer of the AI influencer content pipeline.

## Image Generation

| Tool | Role | Why |
|------|------|-----|
| **Google Gemini (Imagen 3)** | Primary image generator | Best photorealism, free tier available, strong prompt adherence. Handles the SAPELT framework well. |
| **Midjourney v6** | Secondary / style exploration | Superior artistic quality and aesthetic control. Use for mood boards, editorial-style shots, and style R&D. |

**Workflow:** Gemini for volume production (daily content), Midjourney for hero shots (thumbnails, profile pics, brand assets).

**Consistency method:** Textual DNA anchor (defined in persona doc) — no LoRA training needed for v1.

## Video Generation

| Tool | Role | Why |
|------|------|-----|
| **Runway Gen-3 Alpha** | Primary video generator | Best image-to-video pipeline, strong camera movement control, 4s/10s clips. Direct integration with our still images. |
| **Kling 1.5 (Kuaishou)** | Secondary / motion-heavy clips | Superior motion realism for action sequences. Good for street scenes and dynamic content. |

**Workflow:** Generate consistent start frame via Gemini → Feed to Runway for image-to-video → Stitch clips in editor.

**Not selected (v1):**
- Sora: Not yet publicly available with consistent API access
- HeyGen: Talking-head focus doesn't fit our aesthetic-first approach
- Vidu: Good for stylized content but less photorealistic

## Voice & Audio

| Tool | Role | Why |
|------|------|-----|
| **ElevenLabs** | Primary TTS / voiceover | Best voice quality, Vietnamese language support, voice cloning for consistency. Custom voice profile for Linh. |
| **CapCut** (built-in TTS) | Quick drafts / captions | Free, fast, integrated with TikTok. Good for testing scripts before committing to ElevenLabs credits. |

**Voice profile:** Warm female voice, slight Vietnamese accent when speaking English, natural cadence (not robotic). Create a custom voice in ElevenLabs and use it consistently.

## Scripting & Text

| Tool | Role | Why |
|------|------|-----|
| **Claude / GPT-4** | Script generation | Use our `scriptwriting` skill with HOOK framework. Claude for nuanced cultural tone, GPT-4 as fallback. |
| **Our skills pipeline** | Structured prompts | `scriptwriting` → `image-prompting` → `video-prompting` chain handles the full content pipeline. |

## Editing & Post-Production

| Tool | Role | Why |
|------|------|-----|
| **CapCut** | Primary video editor | Free, powerful, native TikTok integration, auto-captions, trending templates. Industry standard for short-form. |
| **Canva** | Graphics & thumbnails | Quick social assets, story templates, brand kit management. |

## Music & Sound

| Tool | Role | Why |
|------|------|-----|
| **CapCut library** | Trending sounds | Access to TikTok trending audio directly. Critical for algorithm performance. |
| **Epidemic Sound** | Licensed background music | For YouTube content where TikTok sounds don't apply. Copyright-safe. |
| **Suno AI** | Custom music (experimental) | Generate unique background tracks matching Linh's aesthetic. Use sparingly. |

## Pipeline Summary

```
Script (Claude + scriptwriting skill)
  ↓
Image prompts (image-prompting skill + SAPELT)
  ↓
Still frames (Gemini Imagen 3 / Midjourney)
  ↓
Video clips (Runway Gen-3, image-to-video)
  ↓
Voiceover (ElevenLabs custom voice)
  ↓
Edit & publish (CapCut → TikTok/YouTube/IG)
```

## Cost Estimate (Monthly, v1 Scale)

| Tool | Plan | Est. Cost |
|------|------|-----------|
| Gemini | Free tier / Pro ($20) | $0–20 |
| Midjourney | Standard ($30) | $30 |
| Runway | Standard ($12) | $12 |
| Kling | Free tier | $0 |
| ElevenLabs | Starter ($5) | $5 |
| CapCut | Free | $0 |
| Canva | Free | $0 |
| **Total** | | **~$47–67/mo** |
