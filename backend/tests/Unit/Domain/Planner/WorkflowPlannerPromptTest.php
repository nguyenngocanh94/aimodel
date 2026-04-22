<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Planner;

use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Planner\PlannerInput;
use App\Domain\Planner\WorkflowPlannerPrompt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers prompt assembly in isolation — no LLM, no planner logic.
 */
final class WorkflowPlannerPromptTest extends TestCase
{
    private NodeTemplateRegistry $registry;

    /** @var list<\App\Domain\Nodes\NodeGuide> */
    private array $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(NodeTemplateRegistry::class);
        $this->catalog = array_values($this->registry->guides());
        ksort($this->catalog);
        $this->catalog = array_values($this->catalog);
    }

    #[Test]
    public function vietnamese_brief_uses_vietnamese_preamble(): void
    {
        $input = new PlannerInput(brief: 'Video TikTok 30s cho sản phẩm bí đao.');
        $prompt = WorkflowPlannerPrompt::build($input, $this->catalog);

        $this->assertStringContainsString('WORKFLOW DESIGNER cho pipeline', $prompt);
        $this->assertStringContainsString('QUY TẮC (đọc kỹ):', $prompt);
        $this->assertStringContainsString('BRIEF CỦA USER (verbatim):', $prompt);
    }

    #[Test]
    public function english_brief_uses_english_preamble(): void
    {
        $input = new PlannerInput(brief: 'Make a 30s TikTok for our new skincare serum.');
        $prompt = WorkflowPlannerPrompt::build($input, $this->catalog);

        $this->assertStringContainsString('WORKFLOW DESIGNER for a Vietnamese TVC', $prompt);
        $this->assertStringContainsString('RULES (ranked, read carefully):', $prompt);
        $this->assertStringContainsString('USER BRIEF (verbatim):', $prompt);
    }

    #[Test]
    public function catalog_section_lists_every_registered_template(): void
    {
        $input = new PlannerInput(brief: 'Test brief in English for catalog coverage.');
        $prompt = WorkflowPlannerPrompt::build($input, $this->catalog);

        foreach ($this->registry->all() as $type => $_tpl) {
            $this->assertStringContainsString("• {$type}", $prompt, "catalog missing type '{$type}'");
        }
    }

    #[Test]
    public function json_schema_snippet_is_present(): void
    {
        $input = new PlannerInput(brief: 'English brief body.');
        $prompt = WorkflowPlannerPrompt::build($input, $this->catalog);

        $this->assertStringContainsString('OUTPUT JSON SCHEMA', $prompt);
        $this->assertStringContainsString('"vibeMode": string', $prompt);
        $this->assertStringContainsString('"sourceNodeId"', $prompt);
    }

    #[Test]
    public function one_shot_example_is_included(): void
    {
        $input = new PlannerInput(brief: 'English brief body.');
        $prompt = WorkflowPlannerPrompt::build($input, $this->catalog);

        $this->assertStringContainsString('EXAMPLE OUTPUT', $prompt);
        $this->assertStringContainsString('"funny_storytelling"', $prompt);
        // Example must contain a twist-ending soft-sell node with product_appearance_moment=twist.
        $this->assertStringContainsString('"product_appearance_moment": "twist"', $prompt);
    }

    #[Test]
    public function vibe_hint_surfaces_in_prompt_when_set(): void
    {
        $input = new PlannerInput(
            brief: 'Short English brief.',
            vibeMode: 'raw_authentic',
        );
        $prompt = WorkflowPlannerPrompt::build($input, $this->catalog);

        $this->assertStringContainsString('USER HINT: vibeMode suggestion is `raw_authentic`', $prompt);
    }

    #[Test]
    public function vibe_hint_absent_when_not_set(): void
    {
        $input = new PlannerInput(brief: 'English brief body without hint.');
        $prompt = WorkflowPlannerPrompt::build($input, $this->catalog);

        $this->assertStringNotContainsString('USER HINT: vibeMode suggestion', $prompt);
    }

    #[Test]
    public function retry_prompt_includes_previous_errors_and_raw_output(): void
    {
        $input = new PlannerInput(brief: 'English brief body for retry.');
        $errors = [
            ['path' => 'nodes[0].type', 'code' => 'unknown_node_type', 'message' => "Unknown type 'foo'"],
            ['path' => 'edges', 'code' => 'cycle_detected', 'message' => 'Plan contains a cycle'],
        ];

        $prompt = WorkflowPlannerPrompt::retry(
            $input,
            $this->catalog,
            previousRawOutput: '{"broken": true}',
            errors: $errors,
        );

        $this->assertStringContainsString('Fix these issues:', $prompt);
        $this->assertStringContainsString("[unknown_node_type] nodes[0].type → Unknown type 'foo'", $prompt);
        $this->assertStringContainsString('[cycle_detected] edges → Plan contains a cycle', $prompt);
        $this->assertStringContainsString('{"broken": true}', $prompt);
        // Retry prompt still carries the full scaffold (rules + schema + catalog).
        $this->assertStringContainsString('OUTPUT JSON SCHEMA', $prompt);
        $this->assertStringContainsString('RULES (ranked, read carefully):', $prompt);
    }

    #[Test]
    public function retry_prompt_vietnamese_uses_vietnamese_retry_section(): void
    {
        $input = new PlannerInput(brief: 'Video TikTok 30s cho sản phẩm Cocoon.');
        $prompt = WorkflowPlannerPrompt::retry(
            $input,
            $this->catalog,
            previousRawOutput: '{}',
            errors: [['path' => 'nodes', 'code' => 'empty_plan', 'message' => 'no nodes']],
        );

        $this->assertStringContainsString('Các lỗi cần sửa:', $prompt);
        $this->assertStringContainsString('Lần thử trước của bạn đã thất bại', $prompt);
    }

    #[Test]
    public function parse_error_surfaces_in_retry_prompt(): void
    {
        $input = new PlannerInput(brief: 'English brief body for parse retry.');
        $prompt = WorkflowPlannerPrompt::retry(
            $input,
            $this->catalog,
            previousRawOutput: 'not json at all',
            errors: [],
            parseError: 'Syntax error at offset 5',
        );

        $this->assertStringContainsString('PARSE_ERROR: Syntax error at offset 5', $prompt);
    }
}
