<?php

declare(strict_types=1);

namespace App\Services\TelegramAgent\BehaviorSkills;

/**
 * Builds the Telegram Assistant system prompt from ordered behavior skills + catalog preview.
 *
 * Tool names match laravel/ai class_basename() derivation (see TelegramAgent::tools()).
 */
final class BehaviorSkillComposer
{
    /**
     * Registered agent tools (verbatim names the model must know).
     *
     * @var list<string>
     */
    private const TOOL_NAMES = [
        'ListWorkflowsTool',
        'RunWorkflowTool',
        'GetRunStatusTool',
        'CancelRunTool',
        'ReplyTool',
        'ComposeWorkflowTool',
    ];

    /**
     * @param  list<BehaviorSkill>  $skills
     * @param  array<string, mixed>  $update
     * @param  array<int, array{slug: string, name: string, nl_description: string|null, param_schema: array|null}>  $catalogPreview
     */
    public function compose(
        array $skills,
        array $update,
        array $catalogPreview,
        string $chatId,
    ): string {
        $principles = [];

        foreach ($skills as $skill) {
            if (! $skill->appliesTo($update)) {
                continue;
            }

            $principles[] = trim($skill->promptFragment());
        }

        $principlesBlock = $principles === []
            ? '_(Không có nguyên tắc bổ sung.)_'
            : implode("\n\n", $principles);

        $catalogSection = $this->renderCatalog($catalogPreview);
        $toolsLine      = implode(', ', self::TOOL_NAMES);

        return <<<PROMPT
        Bạn là Trợ lý Workflow của hệ thống AiModel, nói chuyện qua Telegram (chat {$chatId}).

        # Nguyên tắc

        {$principlesBlock}

        # Workflows có thể kích hoạt

        {$catalogSection}

        # Công cụ bạn được phép gọi

        {$toolsLine}

        Tóm tắt: tìm workflow phù hợp, trích xuất tham số, gọi RunWorkflowTool. Đừng tự viết nội dung sáng tạo thay cho workflow.
        PROMPT;
    }

    /**
     * @param  array<int, array{slug: string, name: string, nl_description: string|null, param_schema: array|null}>  $catalog
     */
    private function renderCatalog(array $catalog): string
    {
        if ($catalog === []) {
            return '_(Chưa có workflow — hãy gọi `ListWorkflowsTool` để cập nhật danh sách.)_';
        }

        $blocks = [];

        foreach ($catalog as $entry) {
            $slug        = $entry['slug'] ?? '(no-slug)';
            $description = $entry['nl_description'] ?? '(no description)';
            $schema      = $entry['param_schema'] ?? [];
            $schemaJson  = is_array($schema)
                ? json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                : '{}';

            $blocks[] = <<<ITEM
            - slug: {$slug}
              mô tả: {$description}
              tham số: {$schemaJson}
            ITEM;
        }

        return implode("\n", $blocks);
    }
}
