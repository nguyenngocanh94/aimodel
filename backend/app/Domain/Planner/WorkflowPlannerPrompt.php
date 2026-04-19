<?php

declare(strict_types=1);

namespace App\Domain\Planner;

use App\Domain\Nodes\GuideKnob;
use App\Domain\Nodes\GuidePort;
use App\Domain\Nodes\NodeGuide;

/**
 * Builds the planner LLM prompt.
 *
 * Separated from {@see WorkflowPlanner} so prompt text can be edited + tested
 * in isolation (prompt engineering is the heart of this seam). The prompt has
 * two forms:
 *
 *   1. {@see build()}    — first attempt. Emits a full catalog + one-shot
 *                          example + rules + schema.
 *   2. {@see retry()}    — subsequent attempts. Same scaffolding plus a
 *                          "previous attempt failed with these errors" block
 *                          so the model can self-correct.
 *
 * Language detection is purposefully cheap (Vietnamese diacritics probe). The
 * planner replies in whichever language the brief arrives in; the JSON schema
 * + catalog remain English for determinism.
 */
final class WorkflowPlannerPrompt
{
    public const LANG_VI = 'vi';
    public const LANG_EN = 'en';

    public const KNOWN_VIBE_MODES = [
        'funny_storytelling',
        'clean_education',
        'aesthetic_mood',
        'raw_authentic',
    ];

    /**
     * Build the initial (round-1) planner prompt.
     *
     * @param list<NodeGuide> $catalog Every registered template's plannerGuide().
     */
    public static function build(PlannerInput $input, array $catalog, ?string $lang = null): string
    {
        $lang ??= self::detectLanguage($input->brief);

        $sections = [
            self::rolePreamble($lang),
            self::rulesBlock($lang),
            self::vibeModeBlock($lang, $input->vibeMode),
            self::schemaBlock(),
            self::catalogBlock($catalog),
            self::oneShotExample($lang),
            self::negativeExamples($lang),
            self::briefBlock($lang, $input),
            self::outputGuard($lang),
        ];

        return implode("\n\n", $sections);
    }

    /**
     * Build a retry prompt that shows the model its previous attempt +
     * validation errors and asks for a corrected JSON response.
     *
     * @param list<NodeGuide>                                                  $catalog
     * @param list<array{path:string, code:string, message:string, context?: array<string, mixed>}> $errors
     */
    public static function retry(
        PlannerInput $input,
        array $catalog,
        string $previousRawOutput,
        array $errors,
        ?string $parseError = null,
        ?string $lang = null,
    ): string {
        $lang ??= self::detectLanguage($input->brief);

        $errorLines = [];
        if ($parseError !== null) {
            $errorLines[] = '- PARSE_ERROR: ' . $parseError;
        }
        foreach ($errors as $err) {
            $errorLines[] = sprintf(
                '- [%s] %s → %s',
                $err['code'] ?? 'unknown',
                $err['path'] ?? '?',
                $err['message'] ?? '(no message)',
            );
        }
        $errorText = $errorLines === []
            ? ($lang === self::LANG_VI
                ? 'Không có lỗi cụ thể, nhưng plan chưa hợp lệ. Hãy thử lại cẩn thận hơn.'
                : 'No specific errors surfaced but the plan is not yet valid. Try again more carefully.')
            : implode("\n", $errorLines);

        $header = $lang === self::LANG_VI
            ? 'Lần thử trước của bạn đã thất bại. Fix các lỗi bên dưới và emit JSON đúng format.'
            : 'Your previous attempt failed. Fix the issues below and emit a correct JSON plan.';

        $prevLabel = $lang === self::LANG_VI ? 'Output trước đó (verbatim):' : 'Previous output (verbatim):';
        $errLabel  = $lang === self::LANG_VI ? 'Các lỗi cần sửa:' : 'Fix these issues:';

        $retryBlock = implode("\n", [
            $header,
            '',
            $prevLabel,
            '```',
            $previousRawOutput,
            '```',
            '',
            $errLabel,
            $errorText,
        ]);

        return self::build($input, $catalog, $lang) . "\n\n---\n\n" . $retryBlock;
    }

    public static function detectLanguage(string $brief): string
    {
        // Vietnamese diacritic probe — broad enough for typical briefs.
        if (preg_match('/[àáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ]/iu', $brief) === 1) {
            return self::LANG_VI;
        }
        return self::LANG_EN;
    }

    // ── Sections ──────────────────────────────────────────────────────────

    private static function rolePreamble(string $lang): string
    {
        return $lang === self::LANG_VI
            ? "Bạn là WORKFLOW DESIGNER cho pipeline sản xuất TVC Việt Nam. "
              . "Đọc brief của người dùng, chọn node từ catalog, nối dây giữa chúng, và set knobs. "
              . "Luôn ưu tiên giữ vibe đúng với brief — đừng drift về TV-ad template hoặc ingredient-breakdown."
            : "You are a WORKFLOW DESIGNER for a Vietnamese TVC generation pipeline. "
              . "Read the user brief, pick nodes from the catalog, wire them together, and set knobs. "
              . "Always keep the output's vibe faithful to the brief — do NOT drift toward generic TV-ad structure or ingredient-breakdown formats.";
    }

    private static function rulesBlock(string $lang): string
    {
        $rules = [
            'RULE 1: `vibeMode` MUST be exactly one of ['
                . implode(', ', self::KNOWN_VIBE_MODES) . '].',
            'RULE 2: Every node in `nodes[]` MUST have a `type` that appears in the NODE CATALOG below.',
            'RULE 3: Every node MUST have a non-empty `reason` explaining why it was picked.',
            'RULE 4: Every edge MUST have a non-empty `reason` explaining the data dependency.',
            'RULE 5: Edge `sourcePortKey` and `targetPortKey` MUST be keys defined on the referenced nodes.',
            'RULE 6: The graph MUST be a DAG — no cycles, no self-loops, no duplicate edges.',
            'RULE 7: Node `config` values MUST obey the node\'s knob types and option lists from the catalog.',
            'RULE 8: When a knob has a `vibe_mapping` for the chosen `vibeMode`, USE that mapped value unless the brief overrides it.',
            'RULE 9: Pick the MINIMUM viable set of nodes that satisfies the brief. Do not add nodes that earn their slot with "nice-to-have" rationale.',
            'RULE 10: If the brief is ambiguous, still emit a valid plan and record the ambiguities in `assumptions[]`.',
        ];

        return ($lang === self::LANG_VI ? "QUY TẮC (đọc kỹ):\n" : "RULES (ranked, read carefully):\n")
            . implode("\n", $rules);
    }

    private static function vibeModeBlock(string $lang, ?string $vibeHint): string
    {
        $known = implode(', ', self::KNOWN_VIBE_MODES);
        $intro = $lang === self::LANG_VI
            ? "VIBE MODES:\nChọn 1 trong 4: {$known}\n"
              . "- funny_storytelling: hài relatable GenZ, kể chuyện, twist cuối, product nhẹ nhàng xuất hiện muộn.\n"
              . "- clean_education: nhấn features/thành phần, tone giáo dục, sản phẩm xuất hiện sớm, CTA cuối.\n"
              . "- aesthetic_mood: chỉ mood, slow-paced, ASMR / satisfying, không dialogue.\n"
              . "- raw_authentic: UGC talking head, điện thoại quay, minimal edit, không hook mạnh, không twist.\n"
            : "VIBE MODES:\nPick exactly 1 of 4: {$known}\n"
              . "- funny_storytelling: relatable GenZ humor, narrative-driven, twist ending, product enters late.\n"
              . "- clean_education: feature/ingredient-driven, educational tone, product early, explicit CTA.\n"
              . "- aesthetic_mood: mood-first, slow-paced, ASMR / satisfying, no dialogue.\n"
              . "- raw_authentic: UGC talking head, phone camera, minimal edit, no strong hook, no twist.\n";

        if ($vibeHint !== null) {
            $hintLine = $lang === self::LANG_VI
                ? "\nHINT TỪ USER: vibeMode gợi ý là `{$vibeHint}` — hãy sử dụng nếu brief không mâu thuẫn."
                : "\nUSER HINT: vibeMode suggestion is `{$vibeHint}` — use it unless the brief contradicts it.";
            $intro .= $hintLine;
        }

        return $intro;
    }

    private static function schemaBlock(): string
    {
        $schema = <<<'JSON'
OUTPUT JSON SCHEMA (emit ONLY this shape, no prose outside):
{
  "intent": string,                        // echo the user brief verbatim
  "vibeMode": string,                      // one of the KNOWN_VIBE_MODES
  "nodes": [
    {
      "id": string,                        // unique slug within the plan
      "type": string,                      // must exist in NODE CATALOG
      "config": object,                    // knob name → knob value
      "reason": string,                    // why this node was picked
      "label": string|null
    }
  ],
  "edges": [
    {
      "sourceNodeId": string,
      "sourcePortKey": string,
      "targetNodeId": string,
      "targetPortKey": string,
      "reason": string
    }
  ],
  "assumptions": [string],                 // what you assumed about the brief
  "rationale": string,                     // 2-4 sentences tying nodes+edges back to intent+vibeMode
  "meta": { "plannerVersion": "1.0" }
}
JSON;
        return $schema;
    }

    /**
     * @param list<NodeGuide> $catalog
     */
    private static function catalogBlock(array $catalog): string
    {
        $lines = ['NODE CATALOG (your entire vocabulary — do not invent node types):'];
        foreach ($catalog as $guide) {
            $lines[] = self::renderCatalogEntry($guide);
        }
        return implode("\n", $lines);
    }

    private static function renderCatalogEntry(NodeGuide $g): string
    {
        $lines = [];
        $lines[] = "• {$g->nodeId}  —  vibe_impact: {$g->vibeImpact->value}; position: {$g->position}";
        $lines[] = "   purpose: {$g->purpose}";
        $lines[] = "   include_when: {$g->whenToInclude}";
        $lines[] = "   skip_when:    {$g->whenToSkip}";

        if ($g->ports !== []) {
            $portStrs = array_map(
                fn (GuidePort $p) => ($p->direction . ':' . $p->key . '[' . $p->type . ']' . ($p->required ? '!' : '')),
                $g->ports,
            );
            $lines[] = '   ports: ' . implode(', ', $portStrs);
        }

        if ($g->knobs !== []) {
            $lines[] = '   knobs:';
            foreach ($g->knobs as $k) {
                $lines[] = '     ' . self::renderKnob($k);
            }
        }

        return implode("\n", $lines);
    }

    private static function renderKnob(GuideKnob $k): string
    {
        $options = $k->options !== null
            ? ' options=[' . implode('|', array_map(static fn ($o) => is_bool($o) ? ($o ? 'true' : 'false') : (string) $o, $k->options)) . ']'
            : '';
        $vibe = $k->vibeMapping !== []
            ? ' vibe={' . implode(', ', array_map(
                static fn ($v, $key) => "{$key}→{$v}",
                array_values($k->vibeMapping),
                array_keys($k->vibeMapping),
            )) . '}'
            : '';
        $default = is_bool($k->default) ? ($k->default ? 'true' : 'false') : (string) $k->default;
        return "- {$k->name} ({$k->type}){$options} default={$default}{$vibe}  // {$k->effect}";
    }

    private static function oneShotExample(string $lang): string
    {
        $briefLine = $lang === self::LANG_VI
            ? 'BRIEF (ví dụ): "Video TikTok 30s cho serum Cocoon bí đao. Tone GenZ vui vẻ, kể chuyện nhỏ, sản phẩm xuất hiện cuối như twist."'
            : 'EXAMPLE BRIEF: "30s TikTok for Cocoon winter-melon serum. Fun GenZ tone, small narrative, product appears at the end as a twist."';

        $example = <<<'JSON'
EXAMPLE OUTPUT (for a soft-sell brief):
{
  "intent": "30s TikTok for Cocoon winter-melon serum. Fun GenZ tone, small narrative, product appears at the end as a twist.",
  "vibeMode": "funny_storytelling",
  "nodes": [
    {"id": "analyze", "type": "productAnalyzer", "config": {}, "reason": "Extract product facts as grounding for the story.", "label": null},
    {"id": "story",   "type": "storyWriter",     "config": {"storyFormula": "problem_agitation_solution", "emotionalTone": "relatable_humor", "productIntegrationStyle": "subtle_background", "story_tension_curve": "fast_hit", "product_appearance_moment": "twist", "humor_density": "throughout", "ending_type_preference": "twist_reveal", "targetDurationSeconds": 30, "provider": "fireworks", "genZAuthenticity": "ultra", "vietnameseDialect": "neutral"}, "reason": "Brief asks for soft-sell narrative — use storyWriter with twist ending + humor throughout.", "label": "Soft-sell story"},
    {"id": "scenes",  "type": "sceneSplitter",   "config": {}, "reason": "Split story arc into shot-level scenes for image+video pipeline.", "label": null},
    {"id": "prompts", "type": "promptRefiner",   "config": {}, "reason": "Refine scene descriptions into image+video prompts.", "label": null},
    {"id": "imgs",    "type": "imageGenerator",  "config": {}, "reason": "Produce key-frame images per scene.", "label": null},
    {"id": "compose", "type": "videoComposer",   "config": {}, "reason": "Assemble final video from generated assets.", "label": null}
  ],
  "edges": [
    {"sourceNodeId": "analyze", "sourcePortKey": "productAnalysis", "targetNodeId": "story",   "targetPortKey": "productAnalysis", "reason": "Story needs product facts to keep claims grounded."},
    {"sourceNodeId": "story",   "sourcePortKey": "storyArc",        "targetNodeId": "scenes",  "targetPortKey": "storyArc",        "reason": "Scenes are derived from the story shots."},
    {"sourceNodeId": "scenes",  "sourcePortKey": "scenes",          "targetNodeId": "prompts", "targetPortKey": "scenes",          "reason": "Prompts are written per scene."},
    {"sourceNodeId": "prompts", "sourcePortKey": "prompts",         "targetNodeId": "imgs",    "targetPortKey": "prompts",         "reason": "Image generation consumes prompts."},
    {"sourceNodeId": "imgs",    "sourcePortKey": "images",          "targetNodeId": "compose", "targetPortKey": "images",          "reason": "Final composition stitches the generated images."}
  ],
  "assumptions": [
    "Platform: TikTok vertical 9:16",
    "Product: Cocoon winter-melon serum — real ingredients respected",
    "No explicit CTA per brief"
  ],
  "rationale": "Soft-sell briefs map to funny_storytelling; storyWriter with twist_reveal keeps the product as punchline. scriptWriter + trendResearcher are deliberately NOT picked — they would drive an ingredient-breakdown / hero-shot structure that contradicts the brief.",
  "meta": {"plannerVersion": "1.0"}
}
JSON;

        return $briefLine . "\n\n" . $example;
    }

    private static function negativeExamples(string $lang): string
    {
        return $lang === self::LANG_VI
            ? "DO NOT:\n"
              . "- DO NOT wrap output in markdown code fences (```json ... ```).\n"
              . "- DO NOT emit commentary, prose, or explanation outside the JSON object.\n"
              . "- DO NOT include JSON comments (// …) — they are not valid JSON.\n"
              . "- DO NOT invent node types that aren't in the catalog.\n"
              . "- DO NOT pick scriptWriter when the brief says \"no dialogue\" or \"no script\".\n"
              . "- DO NOT pick storyWriter when the brief explicitly rejects storyline.\n"
              . "- DO NOT pick trendResearcher when the brief demands no trends / no hooks.\n"
            : "DO NOT:\n"
              . "- DO NOT wrap output in markdown code fences (```json ... ```).\n"
              . "- DO NOT emit commentary, prose, or explanation outside the JSON object.\n"
              . "- DO NOT include JSON comments (// …) — they are not valid JSON.\n"
              . "- DO NOT invent node types that aren't in the catalog.\n"
              . "- DO NOT pick scriptWriter when the brief says \"no dialogue\" or \"no script\".\n"
              . "- DO NOT pick storyWriter when the brief explicitly rejects storyline.\n"
              . "- DO NOT pick trendResearcher when the brief demands no trends / no hooks.\n";
    }

    private static function briefBlock(string $lang, PlannerInput $input): string
    {
        $header = $lang === self::LANG_VI ? 'BRIEF CỦA USER (verbatim):' : 'USER BRIEF (verbatim):';
        $productLine = '';
        if ($input->product !== null && $input->product !== '') {
            $productLine = ($lang === self::LANG_VI ? "Sản phẩm: " : "Product: ") . $input->product . "\n";
        }
        return $header . "\n" . $productLine . $input->brief;
    }

    private static function outputGuard(string $lang): string
    {
        return $lang === self::LANG_VI
            ? 'OUTPUT: Emit CHỈ 1 JSON object duy nhất, không có gì khác. Không markdown. Không prose. Không code fence.'
            : 'OUTPUT: Emit ONLY a single JSON object. Nothing else. No markdown. No prose. No code fences.';
    }
}
