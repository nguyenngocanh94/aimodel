<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent;

use App\Services\TelegramAgent\SystemPrompt;
use PHPUnit\Framework\TestCase;

final class SystemPromptTest extends TestCase
{
    public function test_build_with_zero_catalog_entries(): void
    {
        $prompt = SystemPrompt::build([], '12345');

        // Vietnamese guardrails must be present.
        $this->assertStringContainsString('cầu nối', $prompt);
        $this->assertStringContainsString('ListWorkflowsTool', $prompt);
        $this->assertStringContainsString('RunWorkflowTool', $prompt);
        $this->assertStringContainsString('ReplyTool', $prompt);
        $this->assertStringContainsString('param_schema', $prompt);

        // Chat ID must appear.
        $this->assertStringContainsString('12345', $prompt);

        // Empty-catalog placeholder.
        $this->assertStringContainsString('ListWorkflowsTool', $prompt);
    }

    public function test_build_with_one_catalog_entry(): void
    {
        $catalog = [
            [
                'slug'           => 'story-writer-gated',
                'name'           => 'StoryWriter (per-node gate) – Telegram',
                'nl_description' => 'Viết kịch bản video TVC ngắn tiếng Việt.',
                'param_schema'   => ['productBrief' => ['required', 'string', 'min:5']],
            ],
        ];

        $prompt = SystemPrompt::build($catalog, '99999');

        // Slug must appear verbatim.
        $this->assertStringContainsString('story-writer-gated', $prompt);

        // Description must appear.
        $this->assertStringContainsString('Viết kịch bản video TVC', $prompt);

        // Parameter name must appear so the LLM knows what to collect.
        $this->assertStringContainsString('productBrief', $prompt);

        // Chat ID.
        $this->assertStringContainsString('99999', $prompt);

        // Vietnamese guardrails.
        $this->assertStringContainsString('Tuyệt đối không tự bịa slug', $prompt);
        $this->assertStringContainsString('RunWorkflowTool', $prompt);
    }

    public function test_build_with_two_catalog_entries(): void
    {
        $catalog = [
            [
                'slug'           => 'story-writer-gated',
                'name'           => 'StoryWriter',
                'nl_description' => 'Viết kịch bản video.',
                'param_schema'   => ['productBrief' => ['required', 'string']],
            ],
            [
                'slug'           => 'tvc-pipeline',
                'name'           => 'M1 Demo – AI Video Pipeline',
                'nl_description' => 'Pipeline đầy đủ từ prompt đến video.',
                'param_schema'   => ['prompt' => ['required', 'string', 'min:10']],
            ],
        ];

        $prompt = SystemPrompt::build($catalog, '777');

        // Both slugs appear.
        $this->assertStringContainsString('story-writer-gated', $prompt);
        $this->assertStringContainsString('tvc-pipeline', $prompt);

        // Both param fields appear.
        $this->assertStringContainsString('productBrief', $prompt);
        $this->assertStringContainsString('prompt', $prompt);

        // ChatId.
        $this->assertStringContainsString('777', $prompt);

        // Guardrails.
        $this->assertStringContainsString('Tuyệt đối không tự bịa slug', $prompt);
        $this->assertStringContainsString('từ chối lịch sự', $prompt);
        $this->assertStringContainsString('stack trace', $prompt);
    }

    public function test_guardrails_are_present_regardless_of_catalog(): void
    {
        foreach ([[], [['slug' => 'x', 'name' => 'X', 'nl_description' => null, 'param_schema' => null]]] as $catalog) {
            $prompt = SystemPrompt::build($catalog, '1');

            // Core behavioural guardrails.
            $this->assertStringContainsString('Tuyệt đối không tự bịa slug', $prompt, 'no-fabricate-slug guardrail missing');
            $this->assertStringContainsString('RunWorkflowTool', $prompt, 'RunWorkflowTool mention missing');
            $this->assertStringContainsString('ReplyTool', $prompt, 'ReplyTool tool mention missing');
            $this->assertStringContainsString('stack trace', $prompt, 'stack-trace guardrail missing');
            $this->assertStringContainsString('từ chối lịch sự', $prompt, 'polite-decline guardrail missing');
        }
    }
}
