<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Models\Workflow;
use App\Services\TelegramAgent\AgentContext;
use App\Services\TelegramAgent\Tools\ListWorkflowsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ListWorkflowsToolTest extends TestCase
{
    use RefreshDatabase;

    private AgentContext $ctx;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = new AgentContext(
            chatId: '123456',
            userId: 'user-1',
            sessionId: 'session-abc',
            botToken: 'bot-token-xyz',
        );
    }

    private function makeWorkflow(array $overrides = []): Workflow
    {
        return Workflow::create(array_merge([
            'name' => 'Test Workflow',
            'document' => ['nodes' => [], 'edges' => []],
        ], $overrides));
    }

    #[Test]
    public function definition_returns_correct_tool_definition(): void
    {
        $tool = new ListWorkflowsTool();
        $def = $tool->definition();

        $this->assertSame('list_workflows', $def->name);
        $this->assertStringContainsString('trigger', $def->description);
    }

    #[Test]
    public function execute_returns_only_triggerable_workflows(): void
    {
        // Two triggerable workflows
        $this->makeWorkflow([
            'name' => 'Story Writer',
            'slug' => 'story-writer-gated',
            'triggerable' => true,
            'nl_description' => 'Write a TVC script',
            'param_schema' => ['productBrief' => ['required', 'string']],
        ]);

        $this->makeWorkflow([
            'name' => 'TVC Pipeline',
            'slug' => 'tvc-pipeline',
            'triggerable' => true,
            'nl_description' => 'Full pipeline',
            'param_schema' => ['prompt' => ['required', 'string']],
        ]);

        // One non-triggerable workflow
        $this->makeWorkflow([
            'name' => 'Internal Demo',
            'slug' => 'internal-demo',
            'triggerable' => false,
        ]);

        $tool = new ListWorkflowsTool();
        $result = $tool->execute([], $this->ctx);

        $this->assertArrayHasKey('workflows', $result);
        $this->assertCount(2, $result['workflows']);

        $slugs = array_column($result['workflows'], 'slug');
        $this->assertContains('story-writer-gated', $slugs);
        $this->assertContains('tvc-pipeline', $slugs);
        $this->assertNotContains('internal-demo', $slugs);
    }

    #[Test]
    public function execute_returns_empty_array_when_no_triggerable_workflows(): void
    {
        $this->makeWorkflow(['name' => 'Hidden Workflow', 'triggerable' => false]);

        $tool = new ListWorkflowsTool();
        $result = $tool->execute([], $this->ctx);

        $this->assertArrayHasKey('workflows', $result);
        $this->assertCount(0, $result['workflows']);
    }

    #[Test]
    public function execute_returns_slug_name_nl_description_and_param_schema_fields(): void
    {
        $this->makeWorkflow([
            'name' => 'Story Writer',
            'slug' => 'story-writer-gated',
            'triggerable' => true,
            'nl_description' => 'Write a TVC script',
            'param_schema' => ['productBrief' => ['required', 'string']],
        ]);

        $tool = new ListWorkflowsTool();
        $result = $tool->execute([], $this->ctx);

        $workflow = $result['workflows'][0];
        $this->assertArrayHasKey('slug', $workflow);
        $this->assertArrayHasKey('name', $workflow);
        $this->assertArrayHasKey('nl_description', $workflow);
        $this->assertArrayHasKey('param_schema', $workflow);
    }
}
