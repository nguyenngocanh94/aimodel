<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\Exceptions\ReviewPendingException;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\HumanGateTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HumanGateTemplateTest extends TestCase
{
    private HumanGateTemplate $template;

    protected function setUp(): void
    {
        $this->template = new HumanGateTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('humanGate', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame('Human Gate', $this->template->title);
        $this->assertSame(NodeCategory::Utility, $this->template->category);
        $this->assertStringContainsString('Pauses workflow execution', $this->template->description);
    }

    #[Test]
    public function ports_define_data_input_and_response_output(): void
    {
        $ports = $this->template->ports();

        $this->assertCount(1, $ports->inputs);
        $this->assertSame('data', $ports->inputs[0]->key);
        $this->assertSame(DataType::Json, $ports->inputs[0]->dataType);
        $this->assertTrue($ports->inputs[0]->required);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('response', $ports->outputs[0]->key);
        $this->assertSame(DataType::Json, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function default_config_has_expected_values(): void
    {
        $config = $this->template->defaultConfig();

        $this->assertSame('', $config['messageTemplate']);
        $this->assertSame('ui', $config['channel']);
        $this->assertSame(0, $config['timeoutSeconds']);
        $this->assertNull($config['autoFallbackResponse']);
        $this->assertNull($config['options']);
    }

    #[Test]
    public function execute_throws_review_pending_when_no_response(): void
    {
        $ctx = new NodeExecutionContext(
            nodeId: 'node-gate-1',
            config: $this->template->defaultConfig(),
            inputs: [
                'data' => PortPayload::success(['question' => 'Pick one'], DataType::Json),
            ],
            runId: 'run-1',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        try {
            $this->template->execute($ctx);
            $this->fail('Expected ReviewPendingException');
        } catch (ReviewPendingException $e) {
            $this->assertSame('node-gate-1', $e->nodeId);
        }
    }

    #[Test]
    public function execute_returns_response_when_review_data_provided(): void
    {
        $responseData = ['choice' => 'A', 'reason' => 'Best option'];

        $config = array_merge($this->template->defaultConfig(), [
            '_gateResponse' => $responseData,
        ]);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-gate-2',
            config: $config,
            inputs: [
                'data' => PortPayload::success(['question' => 'Pick one'], DataType::Json),
            ],
            runId: 'run-1',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('response', $result);
        $this->assertTrue($result['response']->isSuccess());
        $this->assertSame(DataType::Json, $result['response']->schemaType);
        $this->assertSame($responseData, $result['response']->value);
    }

    #[Test]
    public function message_template_renders_with_input_data(): void
    {
        $rendered = $this->template->renderMessageTemplate(
            'Hello {{name}}, please choose from {{options}}.',
            ['name' => 'Alice', 'options' => ['A', 'B', 'C']],
        );

        $this->assertSame('Hello Alice, please choose from ["A","B","C"].', $rendered);
    }

    #[Test]
    public function message_template_leaves_unknown_placeholders(): void
    {
        $rendered = $this->template->renderMessageTemplate(
            'Hello {{name}}, your {{unknown}} is ready.',
            ['name' => 'Bob'],
        );

        $this->assertSame('Hello Bob, your {{unknown}} is ready.', $rendered);
    }

    #[Test]
    public function message_template_returns_empty_for_empty_template(): void
    {
        $rendered = $this->template->renderMessageTemplate('', ['key' => 'val']);

        $this->assertSame('', $rendered);
    }

    #[Test]
    public function execute_throws_with_custom_message_from_template(): void
    {
        $config = array_merge($this->template->defaultConfig(), [
            'messageTemplate' => 'Please review {{item}}',
        ]);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-gate-3',
            config: $config,
            inputs: [
                'data' => PortPayload::success(['item' => 'the script'], DataType::Json),
            ],
            runId: 'run-1',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        try {
            $this->template->execute($ctx);
            $this->fail('Expected ReviewPendingException');
        } catch (ReviewPendingException $e) {
            $this->assertSame('node-gate-3', $e->nodeId);
            $this->assertSame('Please review the script', $e->getMessage());
        }
    }
}
