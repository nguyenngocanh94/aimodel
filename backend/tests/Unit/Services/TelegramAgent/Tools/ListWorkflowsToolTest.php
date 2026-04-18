<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Models\Workflow;
use App\Services\TelegramAgent\Tools\ListWorkflowsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ListWorkflowsToolTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkflow(array $overrides = []): Workflow
    {
        return Workflow::create(array_merge([
            'name'     => 'Test Workflow',
            'document' => ['nodes' => [], 'edges' => []],
        ], $overrides));
    }

    #[Test]
    public function description_returns_non_empty_string(): void
    {
        $tool = new ListWorkflowsTool();

        $this->assertNotEmpty($tool->description());
        $this->assertStringContainsString('trigger', $tool->description());
    }

    #[Test]
    public function schema_returns_empty_array_no_required_inputs(): void
    {
        $tool   = new ListWorkflowsTool();
        $schema = $tool->schema(new JsonSchemaTypeFactory());

        $this->assertSame([], $schema);
    }

    #[Test]
    public function handle_returns_only_triggerable_workflows(): void
    {
        // Two triggerable workflows
        $this->makeWorkflow([
            'name'           => 'Story Writer',
            'slug'           => 'story-writer-gated',
            'triggerable'    => true,
            'nl_description' => 'Write a TVC script',
            'param_schema'   => ['productBrief' => ['required', 'string']],
        ]);

        $this->makeWorkflow([
            'name'           => 'TVC Pipeline',
            'slug'           => 'tvc-pipeline',
            'triggerable'    => true,
            'nl_description' => 'Full pipeline',
            'param_schema'   => ['prompt' => ['required', 'string']],
        ]);

        // One non-triggerable workflow
        $this->makeWorkflow([
            'name'        => 'Internal Demo',
            'slug'        => 'internal-demo',
            'triggerable' => false,
        ]);

        $tool   = new ListWorkflowsTool();
        $result = json_decode($tool->handle(new Request([])), true);

        $this->assertArrayHasKey('workflows', $result);
        $this->assertCount(2, $result['workflows']);

        $slugs = array_column($result['workflows'], 'slug');
        $this->assertContains('story-writer-gated', $slugs);
        $this->assertContains('tvc-pipeline', $slugs);
        $this->assertNotContains('internal-demo', $slugs);
    }

    #[Test]
    public function handle_returns_empty_array_when_no_triggerable_workflows(): void
    {
        $this->makeWorkflow(['name' => 'Hidden Workflow', 'triggerable' => false]);

        $tool   = new ListWorkflowsTool();
        $result = json_decode($tool->handle(new Request([])), true);

        $this->assertArrayHasKey('workflows', $result);
        $this->assertCount(0, $result['workflows']);
    }

    #[Test]
    public function handle_returns_slug_name_nl_description_and_param_schema_fields(): void
    {
        $this->makeWorkflow([
            'name'           => 'Story Writer',
            'slug'           => 'story-writer-gated',
            'triggerable'    => true,
            'nl_description' => 'Write a TVC script',
            'param_schema'   => ['productBrief' => ['required', 'string']],
        ]);

        $tool   = new ListWorkflowsTool();
        $result = json_decode($tool->handle(new Request([])), true);

        $workflow = $result['workflows'][0];
        $this->assertArrayHasKey('slug', $workflow);
        $this->assertArrayHasKey('name', $workflow);
        $this->assertArrayHasKey('nl_description', $workflow);
        $this->assertArrayHasKey('param_schema', $workflow);
    }
}
