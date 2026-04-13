# Specs for Opencode — 3 Beads

## Project Context

This is an AI Video Workflow Builder. Backend = Laravel 13 (PHP 8.3) at `backend/`. Frontend = React + TypeScript + Vite at `frontend/`.

Each node has:
- **Backend template**: `backend/app/Domain/Nodes/Templates/{Name}Template.php` (extends `NodeTemplate`)
- **Backend test**: `backend/tests/Unit/Domain/Nodes/Templates/{Name}TemplateTest.php`
- **Frontend template**: `frontend/src/features/node-registry/templates/{name}.ts`
- **Frontend test**: `frontend/src/features/node-registry/templates/{name}.test.ts`
- **Registered** in `backend/app/Providers/NodeTemplateServiceProvider.php` and `frontend/src/features/node-registry/node-registry.ts`

**Reference files to understand patterns:**
- `backend/app/Domain/Nodes/Templates/TrendResearcherTemplate.php` — latest node, good example
- `backend/app/Domain/Nodes/Templates/ScriptWriterTemplate.php` — uses TextGeneration + system prompt
- `backend/app/Domain/Nodes/Templates/HumanGateTemplate.php` — pause/resume pattern
- `frontend/src/features/node-registry/templates/trend-researcher.ts` — latest frontend node
- `frontend/src/features/node-registry/node-registry.ts` — registration

**Run tests:**
- Backend: `docker exec backend-app-1 php artisan test --filter={TestName}`
- Frontend: `cd frontend && npx vitest run src/features/node-registry/templates/{name}.test.ts`

---

## Bead 1: AiModel-624 — StoryWriter Node

### What
The core creative node. Takes product analysis + trend brief + optional user seed idea → writes a human story arc for Vietnamese TikTok TVC. NOT a product pitch — a story about a human that hooks to the product.

### Backend Template

**File:** `backend/app/Domain/Nodes/Templates/StoryWriterTemplate.php`

- type: `storyWriter` (replaces existing scriptWriter — keep scriptWriter as-is for backward compat, this is a NEW node)
- version: `1.0.0`
- title: `Story Writer`
- category: `NodeCategory::Script`
- description: `Writes human-centered story arcs for TVC videos. Localized for Vietnamese GenZ. Takes product analysis and trend context to create stories that hook emotions first, product second.`

**Inputs:**
- `productAnalysis` (DataType::Json, required: false) — from ProductAnalyzer node
- `trendBrief` (DataType::Json, required: false) — from TrendResearcher node
- `seed` (DataType::Text, required: false) — optional raw idea from user (e.g., "POV đi phỏng vấn xin việc")

**Outputs:**
- `story` (DataType::Json) — structured story arc

**Config:**
- `provider` (string, required) — default `'stub'`
- `apiKey` (string, optional)
- `model` (string, optional) — default `'gpt-4o'`
- `market` (string, in: `'vietnam'`, `'global'`, `'sea'`) — default `'vietnam'`
- `storyFormula` (string, in: `'suyt_thi'`, `'pov'`, `'truoc_sau'`, `'ban_than_recommend'`, `'unboxing'`, `'mot_ngay'`, `'review_that'`, `'auto'`) — default `'auto'`
- `targetDurationSeconds` (integer, min: 15, max: 60) — default `30`
- `language` (string) — default `'vi'`

**Execute:**
- Uses `Capability::TextGeneration`
- System prompt should:
  - Identify as a Vietnamese TikTok TVC story writer
  - Explain story formulas:
    - `suyt_thi`: "Almost missed out → product saved the day" (FOMO + relief)
    - `pov`: "POV: bạn là..." first-person relatable situation
    - `truoc_sau`: Before/after transformation
    - `ban_than_recommend`: Friend recommends product
    - `unboxing`: Unexpected package → reveal → reaction
    - `mot_ngay`: Day-in-my-life featuring product naturally
    - `review_that`: Honest review tone
    - `auto`: AI picks best formula based on product
  - Instruct to return JSON:
    ```json
    {
      "title": "string — story title",
      "formula": "string — which formula was used",
      "hook": "string — opening hook line (Vietnamese)",
      "story": {
        "shots": [
          {
            "shotNumber": 1,
            "timestamp": "0~3s",
            "description": "string — what happens in this shot",
            "dialogue": "string|null — character's spoken words",
            "emotion": "string — character's emotion",
            "setting": "string — where this takes place",
            "cameraDirection": "string — shot type and movement"
          }
        ]
      },
      "cast": {
        "lead": "string — description of the lead character",
        "supporting": "string|null — supporting character if needed"
      },
      "tone": "string — funny/emotional/aspirational/chill",
      "soundDirection": "string — suggested music/audio mood",
      "productMoment": "string — which shot number reveals/features the product"
    }
    ```
  - Include product analysis context and trend brief in user prompt
  - If seed is provided, build story around the seed idea
  - Target duration determines number of shots (~3s per shot)
  - All dialogue/hook in Vietnamese when market is `'vietnam'`

**Stub behavior:**
The existing StubAdapter returns canned text for TextGeneration. The StoryWriter should parse the result or, if parsing fails, wrap it in the expected JSON structure with sensible defaults.

### Backend Test

**File:** `backend/tests/Unit/Domain/Nodes/Templates/StoryWriterTemplateTest.php`

Tests:
- `has_correct_metadata` — type, version, category
- `ports_define_all_inputs_and_story_output`
- `default_config_targets_vietnam_with_auto_formula`
- `execute_with_stub_returns_story_json` — verify output has shots, hook, cast, tone
- `system_prompt_includes_formula_instructions`
- `system_prompt_includes_seed_when_provided`

### Frontend Template

**File:** `frontend/src/features/node-registry/templates/story-writer.ts`

- Match backend type/config
- Zod config schema
- mockExecute returns sample Vietnamese story with 5 shots
- buildPreview returns idle
- 1 fixture: `'Vietnamese TVC Story'`

### Frontend Test

**File:** `frontend/src/features/node-registry/templates/story-writer.test.ts`

Standard tests: metadata, ports, config validation, default config, preview, mock execution.

### Register

- Backend: `NodeTemplateServiceProvider.php`
- Frontend: `templates/index.ts` + `node-registry.ts` + update `node-registry.test.ts` template count

---

## Bead 2: AiModel-620 — Story Writer Compete Pattern (multi-LLM Diverge)

### What
NOT a new node — this is a **workflow pattern** using existing nodes. Document and test that the Diverge node + multiple StoryWriter instances + HumanGate work together.

### What to Build

1. **Built-in workflow template** at `backend/app/Domain/Templates/StoryCompeteWorkflow.php` (or similar):
   A factory that creates a pre-wired workflow with:
   ```
   ProductAnalyzer → TrendResearcher → HumanGate (seed?)
     → Diverge (fan out to 4 paths)
       → StoryWriter (Claude)
       → StoryWriter (GPT)
       → StoryWriter (Gemini)
       → StoryWriter (Grok)
     → Merge (collect 4 stories)
     → HumanGate (pick winner)
     → PromptRefiner (Wan mode)
     → WanR2V
   ```

2. **Built-in template on frontend** at `frontend/src/features/templates/built-in-templates.ts`:
   Check existing file — there may already be a template system. Add a "TVC Story Compete" template that pre-populates a workflow with the above node graph.

3. **Test:** Create a test that verifies the template creates valid nodes with correct connections.

### Important
- This depends on Diverge node (AiModel-625, already done) and StoryWriter (AiModel-624, being built)
- The Diverge node fans out; the Merge is just collecting outputs — check if a Merge/Aggregator node exists, if not create a simple one
- HumanGate handles the pause points

---

## Bead 3: AiModel-618 — TelegramTrigger + TelegramDeliver Nodes

### What
Two nodes that integrate with Telegram Bot API for workflow trigger and delivery.

### TelegramTrigger

**Backend:** `backend/app/Domain/Nodes/Templates/TelegramTriggerTemplate.php`

- type: `telegramTrigger`
- category: `NodeCategory::Input`
- description: `Starts a workflow from a Telegram message. Extracts text, images, and videos from the message.`

**Inputs:** None (it's a trigger — data comes from Telegram webhook)

**Outputs:**
- `text` (DataType::Text) — message text
- `images` (DataType::ImageAssetList) — attached photos
- `rawMessage` (DataType::Json) — full Telegram message object

**Config:**
- `botToken` (string, required) — Telegram bot token
- `allowedChatIds` (array, optional) — restrict to specific chats
- `extractMode` (string, in: `'text'`, `'images'`, `'all'`) — default `'all'`

**Execute:**
- This node is special — it doesn't call an AI provider
- It reads from the workflow's trigger data (stored when webhook fires)
- For now: read trigger payload from `$ctx->config['_triggerPayload']` (injected by the webhook controller)
- Parse Telegram message format: extract text from `message.text`, images from `message.photo[]`
- Return extracted data as port outputs

**Backend webhook route** (add to `routes/api.php`):
```php
Route::post('/telegram/webhook/{botToken}', [TelegramWebhookController::class, 'handle']);
```

**Backend controller:** `backend/app/Http/Controllers/TelegramWebhookController.php`
- Receives Telegram update
- Finds workflow configured with this bot token
- Creates a new run with trigger payload
- Dispatches RunWorkflowJob

### TelegramDeliver

**Backend:** `backend/app/Domain/Nodes/Templates/TelegramDeliverTemplate.php`

- type: `telegramDeliver`
- category: `NodeCategory::Output`
- description: `Sends workflow results to a Telegram chat. Supports text, images, and video.`

**Inputs:**
- `message` (DataType::Text, required: false) — text message to send
- `video` (DataType::VideoAsset, required: false) — video to send
- `image` (DataType::ImageAsset, required: false) — image to send

**Outputs:**
- `deliveryStatus` (DataType::Json) — confirmation of delivery

**Config:**
- `botToken` (string, required)
- `chatId` (string, required) — target chat ID
- `messageTemplate` (string, optional) — template with `{{variable}}` placeholders

**Execute:**
- Calls Telegram Bot API: `https://api.telegram.org/bot{token}/sendMessage` or `sendVideo` or `sendPhoto`
- Formats message using template + input data
- Returns delivery confirmation

### Tests
Standard pattern for both nodes — metadata, ports, config, execute with stub/mock.

### Frontend
Standard frontend templates for both nodes — registered, tested, with fixtures.

### Note
The webhook controller + route is the trickiest part. Look at the existing `RunController` and `RunStreamController` for patterns on how controllers interact with the workflow engine.

---

## Commit Convention

Each bead should be a separate commit:
```
git commit -m "feat: add StoryWriter node — Vietnamese GenZ story-driven TVC scripts

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>"
```

After all done, close beads:
```
bd close AiModel-624 AiModel-620 AiModel-618
bd sync
```
