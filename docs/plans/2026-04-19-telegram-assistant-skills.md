# Telegram Assistant — skills and behavior

> **For Claude:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task.

**Goal:** Fix the behavior we observed on 2026-04-18 — the Telegram agent, when asked `"tạo cho tôi kịch bản giới thiệu sản phẩm bánh chocopie, ... tập trung vào story telling, truyền cảm hứng cho giới trẻ, kể 1 câu chuyện mang tính nhân văn"`, generated a script itself instead of calling `run_workflow(slug='story-writer-gated', params={productBrief:'chocopie ...'})`. Root cause: the system prompt is advisory ("prefer tools"), not mandatory ("never generate content yourself"). This plan introduces a **skills framework** so guardrails become first-class, testable, and composable.

**Scope boundary:** This epic is about the **Assistant** (the Telegram-facing dispatcher). It is **not** about composing new workflows from scratch — that remains `aimodel-645` (AI Planner). The seam between the two is a single stub tool (`compose_workflow`) added in TA5, to be filled in when the planner lands.

**Architecture:**
- `App\Services\TelegramAgent\Skills\Skill` — simple contract: `name(): string` + `promptFragment(): string` + optional `appliesTo(array $update): bool`.
- `App\Services\TelegramAgent\Skills\SkillComposer` — takes a list of enabled skills + catalog preview + chatId, emits the final Vietnamese-first system prompt.
- Four initial skills: `RouteOrRefuseSkill`, `ExtractProductBriefSkill`, `VietnameseToneSkill`, `NoRamblingSkill`.
- `SystemPrompt::build()` delegates to the composer. Skill list lives in `config/telegram_agent.php`.
- A regression harness (`AssistantBehaviorTest`) pins ~12 canned Vietnamese messages to expected tool-use or reply outcomes, including the chocopie case.
- A stub `ComposeWorkflowTool` the model can call when no catalog match is found; returns `{available: false, reason: "Workflow composition not yet implemented — track aimodel-645"}` so the model can explain gracefully instead of hallucinating.

**Tech Stack:** PHP 8.4, Laravel 11, `laravel/ai` v0.6.0 (already installed), PHPUnit 11. Frontend untouched.

**Non-goals:** Workflow composition (aimodel-645). Voice/audio intents. Multi-bot session routing. Rate limiting / cost caps.

---

## TA1 — Skill contract + SkillComposer

**Files:**
- Create: `backend/app/Services/TelegramAgent/Skills/Skill.php` (interface)
- Create: `backend/app/Services/TelegramAgent/Skills/SkillComposer.php`
- Test: `backend/tests/Unit/Services/TelegramAgent/Skills/SkillComposerTest.php`

**`Skill` contract:**
```php
interface Skill {
    public function name(): string;          // e.g. "route-or-refuse"; used for logging, config, ordering
    public function promptFragment(): string; // the Vietnamese-first instruction text the model sees
    public function appliesTo(array $update): bool; // default true; lets future skills opt out per message
}
```

Also provide `abstract class AbstractSkill implements Skill` with `appliesTo() = true` by default so concrete skills stay terse.

**`SkillComposer`:**
```php
final class SkillComposer {
    /** @param list<Skill> $skills */
    public function compose(
        array $skills,
        array $update,
        array $catalogPreview, // [{slug,name,nl_description,param_schema}, ...]
        string $chatId
    ): string;
}
```

Output shape (rough; final copy can be tweaked):
```
Bạn là Trợ lý Workflow của hệ thống AiModel, nói chuyện qua Telegram (chat {$chatId}).

# Nguyên tắc
<skill[0]->promptFragment()>
<skill[1]->promptFragment()>
...

# Workflows có thể kích hoạt
- slug: <slug>
  mô tả: <nl_description>
  tham số: <json(param_schema)>
- ...

# Công cụ bạn được phép gọi
list_workflows, run_workflow, get_run_status, cancel_run, reply, compose_workflow (stub)

Tóm tắt: tìm workflow phù hợp, trích xuất tham số, gọi run_workflow. Đừng tự viết nội dung.
```

Skills are concatenated in **declared order** (not alphabetical) — order carries emphasis. Any skill whose `appliesTo($update)` returns `false` is skipped.

**Tests:**
- `compose_produces_vietnamese_preamble_with_chat_id` — asserts the chatId appears and a Vietnamese-identifier phrase is present.
- `compose_includes_each_applying_skill_in_declared_order` — two fake skills A then B; assert A's fragment appears before B's.
- `compose_skips_skills_that_do_not_apply` — one skill returns false from `appliesTo`; assert its fragment is NOT in the output.
- `compose_renders_catalog_preview_as_bulleted_list` — with 2 catalog entries, both slugs appear as bullets.
- `compose_includes_the_five_tool_names` — every registered agent tool name appears verbatim somewhere.
- `abstract_skill_defaults_applies_to_true` — trivial.

**Acceptance:**
- `docker exec backend-app-1 php artisan test --filter=SkillComposerTest` green.
- No concrete skill class exists yet (TA2 owns them).

---

## TA2 — Four initial skills

**Files:**
- Create: `backend/app/Services/TelegramAgent/Skills/RouteOrRefuseSkill.php`
- Create: `backend/app/Services/TelegramAgent/Skills/ExtractProductBriefSkill.php`
- Create: `backend/app/Services/TelegramAgent/Skills/VietnameseToneSkill.php`
- Create: `backend/app/Services/TelegramAgent/Skills/NoRamblingSkill.php`
- Tests: one per skill under `backend/tests/Unit/Services/TelegramAgent/Skills/`

Each skill extends `AbstractSkill` from TA1 and implements `name()` + `promptFragment()`.

**`RouteOrRefuseSkill`** — the single biggest lever:
```
Bạn là bộ định tuyến workflow, KHÔNG phải người tạo nội dung. TUYỆT ĐỐI KHÔNG viết kịch bản, câu chuyện, caption, hay bất kỳ nội dung sáng tạo nào — đó là việc của workflow, không phải của bạn.

Với MỌI yêu cầu ngụ ý tạo nội dung, bạn BẮT BUỘC phải:
1. Kiểm tra danh sách workflow ở trên.
2. Nếu có workflow phù hợp: gọi run_workflow với slug tương ứng và tham số trích xuất từ tin nhắn.
3. Nếu KHÔNG có workflow phù hợp: gọi compose_workflow để xem có thể tạo mới không. Nếu stub trả về "không khả dụng", hãy gọi reply để nói với người dùng rằng chưa có workflow phù hợp, kèm danh sách các slug hiện có.

KHÔNG BAO GIỜ tự sinh nội dung khi được yêu cầu tạo nội dung. Đó là lỗi nghiêm trọng.
```

**`ExtractProductBriefSkill`**:
```
Khi người dùng nhắc đến một sản phẩm (ví dụ "bánh chocopie", "sữa TH True Milk"), hãy trích xuất đầy đủ:
- Tên sản phẩm
- Bất kỳ ngữ cảnh nào người dùng cung cấp (đối tượng, tone, platform, key message, định dạng, thời lượng...)

Gộp tất cả vào trường `productBrief` dạng văn bản có cấu trúc nhiều dòng, không phải chỉ tên sản phẩm.

Ví dụ đầu vào: "tạo kịch bản video TVC 30s cho bánh chocopie cho GenZ, tông vui vẻ, kể chuyện nhân văn"
Ví dụ `productBrief`:
  Sản phẩm: Bánh Chocopie (Hàn Quốc)
  Đối tượng: GenZ Việt Nam (18-25)
  Platform: TVC 30 giây (TikTok/Reels)
  Tone: Vui vẻ, truyền cảm hứng
  Định hướng: Story-telling nhân văn, gắn kết
```

**`VietnameseToneSkill`**:
```
- Trả lời bằng tiếng Việt trừ khi người dùng viết bằng tiếng Anh.
- Câu trả lời ngắn gọn (1-3 câu). Không dùng emoji trừ khi có ý nghĩa (✅ cho xác nhận, ❌ cho từ chối).
- Xưng "Mình" hoặc "Trợ lý", gọi người dùng "Bạn".
- Không lặp lại câu hỏi của người dùng trước khi trả lời.
```

**`NoRamblingSkill`**:
```
Bạn CHỈ hỗ trợ chạy workflow và theo dõi tiến trình. Nếu người dùng hỏi:
- Thời tiết, toán, tin tức, triết lý, chat chit → gọi reply với lời từ chối lịch sự một câu và dừng lại.
- Tự viết code, viết văn, dịch thuật → giống như trên, đây không phải việc của bạn.
- Câu hỏi kỹ thuật về cách workflow hoạt động → trả lời ngắn gọn bằng reply (không cần tool).

Đừng kéo dài cuộc hội thoại khi yêu cầu nằm ngoài phạm vi.
```

**Tests** — one file per skill, each with 2-3 cases:
- `name_returns_expected_slug`
- `prompt_fragment_contains_key_phrase` — e.g. `RouteOrRefuseSkill` must contain "TUYỆT ĐỐI KHÔNG" and "run_workflow"; `NoRamblingSkill` must mention "từ chối".
- Optionally: `applies_to_returns_true_by_default`.

**Acceptance:**
- All four skill classes exist, extend `AbstractSkill`, pass their unit tests.
- Running `SkillComposerTest` with the four real skills still green (no cross-file breakage).

---

## TA3 — Rewrite `SystemPrompt::build()` to compose from skills

**Files:**
- Edit: `backend/app/Services/TelegramAgent/SystemPrompt.php`
- Edit: `backend/app/Services/TelegramAgent/TelegramAgent.php` (only if signature needs to change — shouldn't)
- Edit: `backend/app/Providers/TelegramAgentServiceProvider.php` (register skills)
- Create: `backend/config/telegram_agent.php` (skill ordering, optional toggles)
- Edit: `backend/tests/Unit/Services/TelegramAgent/SystemPromptTest.php` (rewrite assertions)

**New `SystemPrompt::build(array $catalogPreview, string $chatId, array $update = []): string`** — delegates entirely to `SkillComposer`. The skills list is resolved from `config('telegram_agent.skills')` → `app()->make(Skill::class)` per entry. The service provider binds the skill list as a tagged container collection.

**`config/telegram_agent.php`:**
```php
return [
    'skills' => [
        \App\Services\TelegramAgent\Skills\RouteOrRefuseSkill::class,
        \App\Services\TelegramAgent\Skills\ExtractProductBriefSkill::class,
        \App\Services\TelegramAgent\Skills\VietnameseToneSkill::class,
        \App\Services\TelegramAgent\Skills\NoRamblingSkill::class,
    ],
];
```

This makes skill ordering and enable/disable a config concern — non-developers can reorder or drop a skill without touching code. Future skill additions: one class + one line in this config.

**`SystemPromptTest` rewrite:** keep the three guardrail-invariant cases (0/1/2 catalog entries, chatId echoed, Vietnamese preamble). Add:
- `build_concatenates_route_or_refuse_first` — `RouteOrRefuseSkill`'s signature phrase appears before `VietnameseToneSkill`'s.
- `build_respects_config_skill_order` — reorder config, re-run, assert new order.

**Acceptance:**
- `--filter="SystemPromptTest|SkillComposerTest|RouteOrRefuseSkillTest|ExtractProductBriefSkillTest|VietnameseToneSkillTest|NoRamblingSkillTest"` all green.
- `TelegramAgentTest` still green (the agent still builds its system prompt via `SystemPrompt::build`).

---

## TA4 — Behavior regression harness

**Files:**
- Create: `backend/tests/Feature/AssistantBehaviorTest.php`

A single feature test with a data provider of ~12 canned Vietnamese user messages → expected behavior. Each case uses `TelegramAgent::fake(['some-canned-reply-if-needed'])` + `Http::fake(['api.telegram.org/*' => Http::response(['ok'=>true], 200)])`, constructs a `TelegramAgent`, calls `handle([...fake-update-with-text...], 'FAKE')`, then asserts:

- `TelegramAgent::assertPrompted($text)` — the raw user message reached `prompt()`.
- For tool-triggering cases: the test directly invokes the tool the model would have called (as LA5 demonstrates) to assert side-effects (ExecutionRun count, RunWorkflowJob pushed, etc.). This keeps the test hermetic — we don't rely on the real LLM to actually route.

**The 12 cases** (all Vietnamese unless noted; each case = one `@Test` method):

1. **Chocopie regression (the one that broke):** `"tạo cho tôi kịch bản giới thiệu sản phẩm bánh chocopie ... tập trung vào story telling ..."` → expected: would invoke `run_workflow(slug='story-writer-gated', params={productBrief:'<multi-line Vietnamese brief mentioning Chocopie, GenZ, story-telling>'})`. Assertion driven by directly invoking `RunWorkflowTool` and checking the `productBrief` string passes basic sanity (contains "Chocopie", contains "GenZ" or "giới trẻ").
2. **Status query:** `"/status"` → slash router handles it, no LLM call.
3. **List:** `"/list"` → slash router, reply mentions `story-writer-gated`.
4. **Plain product brief:** `"làm video về trà sữa TH"` → `run_workflow` with productBrief mentioning trà sữa.
5. **Off-topic weather:** `"thời tiết Hà Nội hôm nay thế nào"` → `reply` only; no `run_workflow`.
6. **Off-topic math:** `"giải phương trình x^2 + 3x - 4 = 0"` → `reply` only.
7. **Off-topic general chat:** `"hôm nay bạn khỏe không?"` → short `reply` decline.
8. **Direct content-gen trap:** `"viết cho tôi một bài thơ về mùa thu"` → must NOT generate; `reply` decline or suggest it's not in catalog.
9. **No-match case:** `"tạo landing page cho sản phẩm X"` (no matching workflow) → expected: `compose_workflow` called (returns stub "not available"), then `reply` explains politely.
10. **English fallback:** `"create a TVC script for coca-cola targeting teens"` → `run_workflow` with productBrief in English (VietnameseToneSkill allows English when user writes English).
11. **Ambiguous minimal:** `"làm video"` → must NOT dispatch with empty params; `reply` asking for the product / brief.
12. **Two products in one message:** `"video cho chocopie và oreo"` → acceptable behavior: either one dispatch per product (two `run_workflow` calls) OR one dispatch with a combined brief, OR a clarifying `reply`. Assert only that content was not generated inline.

**Implementation pattern** for each case — use `fake(['canned-reply'])` with `TelegramAgent::fake()`, then ALSO drive the expected tool directly when we want DB side-effect assertions. The test's job is not to validate the LLM picked the right tool (the prompt does that under MiniMax), but to pin behavior at the boundary: "if the prompt were to route, would the tool produce the right effect?"

Add a `test_harness_covers_all_twelve_cases` sanity test at the bottom asserting the provider yields exactly 12 entries — forces us to update the count when we add cases.

**Acceptance:**
- `docker exec backend-app-1 php artisan test --filter=AssistantBehaviorTest` green — all 12 cases pass.
- Runs in < 3s (no live LLM).

---

## TA5 — Stub `ComposeWorkflowTool`

**Files:**
- Create: `backend/app/Services/TelegramAgent/Tools/ComposeWorkflowTool.php`
- Edit: `backend/app/Services/TelegramAgent/TelegramAgent.php` (append to `tools()`)
- Test: `backend/tests/Unit/Services/TelegramAgent/Tools/ComposeWorkflowToolTest.php`

**Purpose:** give the agent a deliberate path when no catalog match exists. Today the tool simply reports "not available"; when `aimodel-645` (AI Planner) lands, swap the implementation to actually invoke the planner and return a proposed workflow.

**Contract:**
```php
final class ComposeWorkflowTool implements \Laravel\Ai\Contracts\Tool {
    public function description(): string {
        return 'Propose a new workflow when no catalog entry matches the user\'s brief. Input: a free-text `brief`. Returns either {available: true, proposal: {...}} or {available: false, reason: string}.';
    }

    public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array {
        return [
            'brief' => $schema->string()
                ->description('The user\'s creative brief including product, audience, tone, platform, etc.')
                ->required(),
        ];
    }

    public function handle(\Laravel\Ai\Tools\Request $request): string {
        return json_encode([
            'available' => false,
            'reason'    => 'Workflow composition is not yet implemented. Planning epic aimodel-645.',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
```

**Register** in `TelegramAgent::tools()` alongside the other five — no constructor args needed.

**Tests:**
- `description_mentions_compose_and_proposal`.
- `schema_declares_required_brief_string`.
- `handle_returns_not_available_json` — asserts `available === false` and `reason` mentions `aimodel-645`.

**Acceptance:**
- `--filter=ComposeWorkflowToolTest` green.
- `TelegramAgentTest` still green (6 → 6 tools now available to the agent).
- Regression sanity: `AssistantBehaviorTest` case 9 (no-match) still passes.

---

## TA6 — Live smoke + docs

**Files:**
- Edit: `docs/plans/2026-04-19-telegram-assistant-skills.md` — add "Done — results" footer with commit hashes.
- Edit: `AGENTS.md` — one-paragraph note: "Telegram Assistant behavior is controlled by composable Skills under `backend/app/Services/TelegramAgent/Skills/`. To change how the bot responds, edit a skill or add a new one and list it in `config/telegram_agent.php`. Never hard-code behavior into `TelegramAgent.php` or `SystemPrompt.php`."
- Optional: a disposable live smoke script replaying the chocopie message against real Fireworks to confirm `run_workflow` is now called (delete the script after).

**Steps:**
1. `docker restart backend-worker-1 backend-app-1`.
2. `docker exec backend-app-1 php artisan config:clear`.
3. Full test sweep: `docker exec backend-app-1 php artisan test --filter="AssistantBehaviorTest|SystemPromptTest|SkillComposerTest|RouteOrRefuseSkillTest|ExtractProductBriefSkillTest|VietnameseToneSkillTest|NoRamblingSkillTest|ComposeWorkflowToolTest|TelegramAgentTest|TelegramWebhookAgentRoutingTest"`.
4. Live smoke (optional but strongly preferred): replay the chocopie message. Confirm `run_workflow` is called this time. Delete the script.
5. Commit `docs(assistant): record skills framework results`, close the epic.

**Acceptance:**
- All tests green.
- Live smoke (if run) shows `run_workflow` invoked with a multi-line `productBrief`.
- `AGENTS.md` updated.
- `bd close` epic + this bead.

---

## Dependency order

```
TA1 (contract+composer) ──► TA2 (4 skills) ──► TA3 (SystemPrompt rewrite) ──► TA4 (behavior harness) ──► TA6 (smoke+docs)
                                                                             ├─► TA5 (ComposeWorkflowTool)
```

TA5 can run in parallel with TA4 if desired — different files, no overlap. TA6 waits on both.

## Relationship to other epics

- **Soft dep on aimodel-645 (AI Planner):** TA5's `ComposeWorkflowTool` is a stub. When 645 lands, swap the stub for the real planner invocation. The Assistant's contract doesn't change.
- **No relation to aimodel-4cq (Node Manifest Alignment):** different surface.
