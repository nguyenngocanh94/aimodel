<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\Tools;

use App\Domain\Planner\WorkflowPlan;

/**
 * Composes the re-plan prompt for {@see RefinePlanTool}.
 *
 * Smaller than {@see \App\Domain\Planner\WorkflowPlannerPrompt} — we've already
 * sunk the first-plan catalog + one-shot into the prior attempt; here we just
 * give the model the prior plan verbatim + user feedback + a compact catalog
 * preview (type/title/when_to_include/when_to_skip per node).
 *
 * Target size: ~3-4K tokens (vs ~8-10K for the first-plan prompt).
 */
final class RefinePlanPrompt
{
    public const LANG_VI = 'vi';
    public const LANG_EN = 'en';

    /**
     * @param list<array{type:string,title:string,whenToInclude:string,whenToSkip:string}> $catalogPreview
     */
    public static function build(WorkflowPlan $priorPlan, string $feedback, array $catalogPreview): string
    {
        $lang = self::detectLanguage($feedback);

        $sections = [
            self::rolePreamble($lang),
            self::rulesBlock($lang),
            self::priorPlanBlock($lang, $priorPlan),
            self::feedbackBlock($lang, $feedback),
            self::catalogBlock($lang, $catalogPreview),
            self::schemaBlock(),
            self::oneShotExample($lang),
            self::outputGuard($lang),
        ];

        return implode("\n\n", $sections);
    }

    public static function detectLanguage(string $feedback): string
    {
        if (preg_match('/[àáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ]/iu', $feedback) === 1) {
            return self::LANG_VI;
        }
        return self::LANG_EN;
    }

    private static function rolePreamble(string $lang): string
    {
        return $lang === self::LANG_VI
            ? "Bạn là WORKFLOW REFINER. Đây là plan hiện tại và yêu cầu chỉnh từ user. "
              . "Emit một plan cập nhật hoàn chỉnh theo đúng schema JSON cũ. "
              . "CHỈ thay đổi những gì user yêu cầu. Giữ nguyên các phần khác."
            : "You are a WORKFLOW REFINER. Here is the current plan and the user's refinement request. "
              . "Emit a complete updated plan in the same JSON schema. "
              . "ONLY change what the user asked for. Keep everything else as-is.";
    }

    private static function rulesBlock(string $lang): string
    {
        $rules = [
            'RULE 1: Preserve `intent` verbatim from the prior plan.',
            'RULE 2: `vibeMode` changes ONLY if the user explicitly asks for a different vibe.',
            'RULE 3: Keep node ids stable when possible — the same id should keep the same role.',
            'RULE 4: Every node + edge MUST have a non-empty `reason` string (carry over the prior reason if unchanged).',
            'RULE 5: Node `type` values must stay within the NODE CATALOG below.',
            'RULE 6: If the user asks to add a node, pick from the catalog; if they ask to remove one, drop it AND any edges touching it.',
            'RULE 7: If the user asks to change a knob, set it on the correct node\'s `config` — do not invent new knob names.',
        ];

        return ($lang === self::LANG_VI ? "QUY TẮC CHỈNH SỬA:\n" : "REFINEMENT RULES:\n")
            . implode("\n", $rules);
    }

    private static function priorPlanBlock(string $lang, WorkflowPlan $priorPlan): string
    {
        $header = $lang === self::LANG_VI ? 'PLAN HIỆN TẠI (prior — JSON verbatim):' : 'CURRENT PLAN (prior — verbatim JSON):';
        $json = json_encode(
            $priorPlan->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        );
        return $header . "\n" . $json;
    }

    private static function feedbackBlock(string $lang, string $feedback): string
    {
        $header = $lang === self::LANG_VI ? 'YÊU CẦU CHỈNH TỪ USER (verbatim):' : 'USER REFINEMENT REQUEST (verbatim):';
        return $header . "\n" . $feedback;
    }

    /**
     * @param list<array{type:string,title:string,whenToInclude:string,whenToSkip:string}> $catalogPreview
     */
    private static function catalogBlock(string $lang, array $catalogPreview): string
    {
        $header = $lang === self::LANG_VI
            ? 'NODE CATALOG (vocabulary — không phát minh type mới):'
            : 'NODE CATALOG (vocabulary — do not invent node types):';
        $lines = [$header];
        foreach ($catalogPreview as $entry) {
            $lines[] = sprintf(
                "• %s — %s\n   include_when: %s\n   skip_when:    %s",
                $entry['type'],
                $entry['title'],
                $entry['whenToInclude'],
                $entry['whenToSkip'],
            );
        }
        return implode("\n", $lines);
    }

    private static function schemaBlock(): string
    {
        return <<<'JSON'
OUTPUT JSON SCHEMA (emit ONLY this shape):
{
  "intent": string,
  "vibeMode": string,
  "nodes": [{"id": string, "type": string, "config": object, "reason": string, "label": string|null}],
  "edges": [{"sourceNodeId": string, "sourcePortKey": string, "targetNodeId": string, "targetPortKey": string, "reason": string}],
  "assumptions": [string],
  "rationale": string,
  "meta": {"plannerVersion": "1.0"}
}
JSON;
    }

    private static function oneShotExample(string $lang): string
    {
        $intro = $lang === self::LANG_VI
            ? "VÍ DỤ — user feedback \"thêm humor nhẹ\" trên plan có storyWriter:\n"
              . "→ Chỉ đổi `storyWriter.config.humor_density` từ \"none\" hoặc \"throughout\" thành \"punchline_only\". "
              . "Mọi node, edge, vibeMode, assumptions khác giữ nguyên. "
              . "Rationale thêm 1 câu giải thích sự thay đổi."
            : "EXAMPLE — user feedback \"add light humor\" on a plan with storyWriter:\n"
              . "→ Change only `storyWriter.config.humor_density` to \"punchline_only\". "
              . "All other nodes, edges, vibeMode, assumptions stay identical. "
              . "Rationale gets one extra sentence explaining the change.";

        return $intro;
    }

    private static function outputGuard(string $lang): string
    {
        // Structured output is enforced by the gateway schema.
        return $lang === self::LANG_VI
            ? 'OUTPUT: Trả về đúng schema đã khai báo — giữ nguyên trường, chỉ cập nhật theo yêu cầu.'
            : 'OUTPUT: Return the declared schema — keep fields intact and only update what the user requested.';
    }
}
