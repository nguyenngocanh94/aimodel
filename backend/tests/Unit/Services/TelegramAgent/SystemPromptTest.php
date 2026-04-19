<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent;

use App\Services\TelegramAgent\Skills\RouteOrRefuseSkill;
use App\Services\TelegramAgent\Skills\VietnameseToneSkill;
use App\Services\TelegramAgent\SystemPrompt;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class SystemPromptTest extends TestCase
{
    public function test_build_with_zero_catalog_entries(): void
    {
        $prompt = SystemPrompt::build([], '12345');

        $this->assertStringContainsString('Trợ lý Workflow', $prompt);
        $this->assertStringContainsString('ListWorkflowsTool', $prompt);
        $this->assertStringContainsString('RunWorkflowTool', $prompt);
        $this->assertStringContainsString('ReplyTool', $prompt);
        $this->assertStringContainsString('ComposeWorkflowTool', $prompt);
        $this->assertStringContainsString('TUYỆT ĐỐI KHÔNG', $prompt);

        $this->assertStringContainsString('12345', $prompt);

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

        $this->assertStringContainsString('story-writer-gated', $prompt);
        $this->assertStringContainsString('Viết kịch bản video TVC', $prompt);
        $this->assertStringContainsString('productBrief', $prompt);
        $this->assertStringContainsString('99999', $prompt);
        $this->assertStringContainsString('run_workflow', $prompt);
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

        $this->assertStringContainsString('story-writer-gated', $prompt);
        $this->assertStringContainsString('tvc-pipeline', $prompt);
        $this->assertStringContainsString('productBrief', $prompt);
        $this->assertStringContainsString('prompt', $prompt);
        $this->assertStringContainsString('777', $prompt);
        $this->assertStringContainsString('từ chối', $prompt);
    }

    public function test_guardrails_are_present_regardless_of_catalog(): void
    {
        foreach ([[], [['slug' => 'x', 'name' => 'X', 'nl_description' => null, 'param_schema' => null]]] as $catalog) {
            $prompt = SystemPrompt::build($catalog, '1');

            $this->assertStringContainsString('TUYỆT ĐỐI KHÔNG', $prompt, 'route guardrail missing');
            $this->assertStringContainsString('RunWorkflowTool', $prompt, 'RunWorkflowTool mention missing');
            $this->assertStringContainsString('ReplyTool', $prompt, 'ReplyTool mention missing');
            $this->assertStringContainsString('từ chối', $prompt, 'polite-decline guardrail missing');
        }
    }

    public function test_build_concatenates_route_or_refuse_first(): void
    {
        $prompt = SystemPrompt::build([], '1');

        $posRoute = strpos($prompt, 'TUYỆT ĐỐI KHÔNG');
        $posTone    = strpos($prompt, 'Trả lời bằng tiếng Việt');
        $this->assertNotFalse($posRoute);
        $this->assertNotFalse($posTone);
        $this->assertLessThan($posTone, $posRoute);
    }

    public function test_build_respects_config_skill_order(): void
    {
        Config::set('telegram_agent.skills', [
            VietnameseToneSkill::class,
            RouteOrRefuseSkill::class,
        ]);

        $prompt = SystemPrompt::build([], '1');

        $posTone  = strpos($prompt, 'Trả lời bằng tiếng Việt');
        $posRoute = strpos($prompt, 'TUYỆT ĐỐI KHÔNG');
        $this->assertNotFalse($posTone);
        $this->assertNotFalse($posRoute);
        $this->assertLessThan($posRoute, $posTone);
    }
}
