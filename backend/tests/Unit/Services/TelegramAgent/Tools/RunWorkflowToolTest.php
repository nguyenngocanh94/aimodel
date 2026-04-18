<?php

declare(strict_types=1);

namespace Tests\Unit\Services\TelegramAgent\Tools;

use App\Jobs\RunWorkflowJob;
use App\Models\ExecutionRun;
use App\Models\Workflow;
use App\Services\TelegramAgent\Tools\RunWorkflowTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\StringType;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RunWorkflowToolTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT_ID = '123456';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function makeTool(): RunWorkflowTool
    {
        return new RunWorkflowTool(chatId: self::CHAT_ID);
    }

    private function makeTriggerable(array $overrides = []): Workflow
    {
        return Workflow::create(array_merge([
            'name'        => 'Test Workflow',
            'slug'        => 'test-slug',
            'triggerable' => true,
            'document'    => [
                'nodes' => [
                    [
                        'id'   => 'node-1',
                        'type' => 'scriptWriter',
                        'data' => ['config' => ['someKey' => 'someValue']],
                    ],
                ],
                'edges' => [],
            ],
        ], $overrides));
    }

    #[Test]
    public function description_returns_non_empty_string(): void
    {
        $this->assertNotEmpty($this->makeTool()->description());
    }

    #[Test]
    public function schema_returns_slug_and_params_with_correct_types(): void
    {
        $schema = $this->makeTool()->schema(new JsonSchemaTypeFactory());

        $this->assertArrayHasKey('slug', $schema);
        $this->assertArrayHasKey('params', $schema);
        $this->assertInstanceOf(StringType::class, $schema['slug']);
        $this->assertInstanceOf(ObjectType::class, $schema['params']);
    }

    #[Test]
    public function happy_path_dispatches_run_workflow_job_and_returns_run_id(): void
    {
        $this->makeTriggerable([
            'param_schema' => ['prompt' => ['required', 'string']],
        ]);

        $result = json_decode($this->makeTool()->handle(new Request([
            'slug'   => 'test-slug',
            'params' => ['prompt' => 'Make a TVC for Chocopie'],
        ])), true);

        $this->assertArrayHasKey('runId', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertSame('pending', $result['status']);
        $this->assertArrayHasKey('workflow', $result);

        Queue::assertPushed(RunWorkflowJob::class, function (RunWorkflowJob $job) use ($result): bool {
            return $job->runId === $result['runId'];
        });

        $this->assertDatabaseHas('execution_runs', [
            'id'      => $result['runId'],
            'trigger' => 'telegramWebhook',
            'status'  => 'pending',
        ]);
    }

    #[Test]
    public function unknown_slug_returns_workflow_not_found_error(): void
    {
        $result = json_decode($this->makeTool()->handle(new Request([
            'slug'   => 'does-not-exist',
            'params' => [],
        ])), true);

        $this->assertSame('workflow_not_found', $result['error']);
        $this->assertSame('does-not-exist', $result['slug']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function missing_required_param_returns_validation_failed_with_fields(): void
    {
        $this->makeTriggerable([
            'param_schema' => ['prompt' => ['required', 'string', 'min:10']],
        ]);

        $result = json_decode($this->makeTool()->handle(new Request([
            'slug'   => 'test-slug',
            'params' => [], // missing 'prompt'
        ])), true);

        $this->assertSame('validation_failed', $result['error']);
        $this->assertArrayHasKey('fields', $result);
        $this->assertArrayHasKey('prompt', $result['fields']);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function agent_params_injected_into_first_node_config_of_snapshot(): void
    {
        $this->makeTriggerable([
            'param_schema' => [],
            'document'     => [
                'nodes' => [
                    [
                        'id'   => 'node-1',
                        'type' => 'scriptWriter',
                        'data' => ['config' => ['someKey' => 'someValue']],
                    ],
                    [
                        'id'   => 'node-2',
                        'type' => 'imageGenerator',
                        'data' => ['config' => ['style' => 'cinematic']],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $params = ['prompt' => 'Make a TVC for Chocopie'];

        $result = json_decode($this->makeTool()->handle(new Request([
            'slug'   => 'test-slug',
            'params' => $params,
        ])), true);

        $this->assertArrayHasKey('runId', $result);

        $run = ExecutionRun::find($result['runId']);
        $this->assertNotNull($run);

        $snapshot   = $run->document_snapshot;
        $firstNode  = $snapshot['nodes'][0];
        $secondNode = $snapshot['nodes'][1];

        // _agentParams must be in the first node's config
        $this->assertArrayHasKey('_agentParams', $firstNode['data']['config']);
        $this->assertSame($params, $firstNode['data']['config']['_agentParams']);

        // Second node should NOT have _agentParams
        $this->assertArrayNotHasKey('_agentParams', $secondNode['data']['config']);
    }

    #[Test]
    public function trigger_payload_injected_into_telegram_trigger_node(): void
    {
        $this->makeTriggerable([
            'param_schema' => [],
            'document'     => [
                'nodes' => [
                    [
                        'id'   => 'node-1',
                        'type' => 'telegramTrigger',
                        'data' => ['config' => ['botToken' => 'bot-token-xyz']],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $params = ['prompt' => 'Make a TVC'];

        $result = json_decode($this->makeTool()->handle(new Request([
            'slug'   => 'test-slug',
            'params' => $params,
        ])), true);

        $run = ExecutionRun::find($result['runId']);
        $this->assertNotNull($run);

        $snapshot    = $run->document_snapshot;
        $triggerNode = $snapshot['nodes'][0];

        // _triggerPayload must be injected
        $this->assertArrayHasKey('_triggerPayload', $triggerNode['data']['config']);
        $triggerPayload = $triggerNode['data']['config']['_triggerPayload'];
        $this->assertArrayHasKey('message', $triggerPayload);
        $this->assertSame((int) self::CHAT_ID, $triggerPayload['message']['chat']['id']);
        $this->assertSame('Make a TVC', $triggerPayload['message']['text']);
    }

    #[Test]
    public function non_triggerable_workflow_with_same_slug_returns_not_found(): void
    {
        Workflow::create([
            'name'        => 'Hidden Workflow',
            'slug'        => 'test-slug',
            'triggerable' => false,
            'document'    => ['nodes' => [], 'edges' => []],
        ]);

        $result = json_decode($this->makeTool()->handle(new Request([
            'slug'   => 'test-slug',
            'params' => [],
        ])), true);

        $this->assertSame('workflow_not_found', $result['error']);
        Queue::assertNothingPushed();
    }
}
