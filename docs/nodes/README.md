# Node Catalog

Per-node designs for the Python port. Generic contract at [`../workflow.md`](../workflow.md).

## Build order

**Minimal smoke path:** `userPrompt` → `scriptWriter` → `reviewCheckpoint`. These three nodes exercise the full runner loop (input → LLM generation → suspension checkpoint) with no external media calls.

**Expand toward video-gen flow:** add `productAnalyzer`, `storyWriter`, `sceneSplitter`, `promptRefiner`, `imageGenerator`, `imageAssetMapper`, `ttsVoiceoverPlanner`, `subtitleFormatter`, `videoComposer`, `telegramDeliver`.

**Wan flow variant:** replace `promptRefiner` + `imageGenerator` with `wanPromptFormatter` + `wanR2V` after `sceneSplitter` (or direct from `storyWriter`).

**Trigger variants:** swap `userPrompt` for `telegramTrigger` once the runner supports trigger-mode entry points.

---

| Node | Category | Vibe | Human gate | File |
|---|---|---|---|---|
| User Prompt | Input | Neutral | no | [user-prompt.md](./user-prompt.md) |
| Telegram Trigger | Input | Neutral | no | [telegram-trigger.md](./telegram-trigger.md) |
| Product Analyzer | Input | Neutral | no | [product-analyzer.md](./product-analyzer.md) |
| Trend Researcher | Script | Critical | no | [trend-researcher.md](./trend-researcher.md) |
| Script Writer | Script | Critical | no | [script-writer.md](./script-writer.md) |
| Story Writer | Script | Critical | yes | [story-writer.md](./story-writer.md) |
| Scene Splitter | Script | Critical | no | [scene-splitter.md](./scene-splitter.md) |
| Prompt Refiner | Script | Critical | no | [prompt-refiner.md](./prompt-refiner.md) |
| Wan Prompt Formatter | Script | Critical | no | [wan-prompt-formatter.md](./wan-prompt-formatter.md) |
| Image Generator | Visuals | Neutral | no | [image-generator.md](./image-generator.md) |
| Image Asset Mapper | Visuals | Neutral | no | [image-asset-mapper.md](./image-asset-mapper.md) |
| TTS Voiceover Planner | Audio | Neutral | no | [tts-voiceover-planner.md](./tts-voiceover-planner.md) |
| Subtitle Formatter | Audio | Neutral | no | [subtitle-formatter.md](./subtitle-formatter.md) |
| Video Composer | Video | Neutral | no | [video-composer.md](./video-composer.md) |
| Wan R2V | Video | Neutral | no | [wan-r2v.md](./wan-r2v.md) |
| Human Gate | Utility | Neutral | yes | [human-gate.md](./human-gate.md) |
| Review Checkpoint | Utility | Neutral | no | [review-checkpoint.md](./review-checkpoint.md) |
| Final Export | Output | Neutral | no | [final-export.md](./final-export.md) |
| Telegram Deliver | Output | Neutral | no | [telegram-deliver.md](./telegram-deliver.md) |
