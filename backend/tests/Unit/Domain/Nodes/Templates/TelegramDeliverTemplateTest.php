<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\TelegramDeliverTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TelegramDeliverTemplateTest extends TestCase
{
    private TelegramDeliverTemplate $template;

    protected function setUp(): void
    {
        $this->template = new TelegramDeliverTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('telegramDeliver', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame('Telegram Deliver', $this->template->title);
        $this->assertSame(NodeCategory::Output, $this->template->category);
        $this->assertStringContainsString('Telegram chat', $this->template->description);
    }

    #[Test]
    public function ports_define_content_input_and_delivery_result_output(): void
    {
        $ports = $this->template->ports();

        $this->assertCount(2, $ports->inputs);

        $contentPort = $ports->getInput('content');
        $this->assertNotNull($contentPort);
        $this->assertSame(DataType::Json, $contentPort->dataType);
        $this->assertTrue($contentPort->required);

        $chatIdPort = $ports->getInput('chatId');
        $this->assertNotNull($chatIdPort);
        $this->assertSame(DataType::Text, $chatIdPort->dataType);
        $this->assertFalse($chatIdPort->required);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('deliveryResult', $ports->outputs[0]->key);
        $this->assertSame(DataType::Json, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function default_config_has_expected_values(): void
    {
        $config = $this->template->defaultConfig();

        $this->assertSame('', $config['botToken']);
        $this->assertSame('', $config['defaultChatId']);
        $this->assertSame('structured', $config['messageFormat']);
        $this->assertTrue($config['includeTimestamp']);
        $this->assertTrue($config['notifyOnSuccess']);
        $this->assertSame(4096, $config['maxMessageLength']);
    }

    #[Test]
    public function config_rules_require_bot_token_and_default_chat_id(): void
    {
        $rules = $this->template->configRules();

        $this->assertArrayHasKey('botToken', $rules);
        $this->assertContains('required', $rules['botToken']);

        $this->assertArrayHasKey('defaultChatId', $rules);
        $this->assertContains('required', $rules['defaultChatId']);

        $this->assertArrayHasKey('messageFormat', $rules);
    }

    #[Test]
    public function execute_with_stub_returns_delivery_result(): void
    {
        $config = array_merge($this->template->defaultConfig(), [
            'botToken' => 'stub',
            'defaultChatId' => '12345',
            'messageFormat' => 'text',
        ]);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-td-1',
            config: $config,
            inputs: [
                'content' => PortPayload::success(
                    ['title' => 'Test Result', 'status' => 'complete'],
                    DataType::Json,
                ),
            ],
            runId: 'run-td-1',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('deliveryResult', $result);
        $this->assertInstanceOf(PortPayload::class, $result['deliveryResult']);
        $this->assertTrue($result['deliveryResult']->isSuccess());
        $this->assertSame(DataType::Json, $result['deliveryResult']->schemaType);

        $delivery = $result['deliveryResult']->value;
        $this->assertTrue($delivery['ok']);
        $this->assertTrue($delivery['stub']);
        $this->assertSame('12345', $delivery['chatId']);
        $this->assertSame('text', $delivery['format']);
        $this->assertArrayHasKey('sentAt', $delivery);
    }

    #[Test]
    public function execute_uses_chat_id_from_input_over_config(): void
    {
        $config = array_merge($this->template->defaultConfig(), [
            'botToken' => 'stub',
            'defaultChatId' => '11111',
            'messageFormat' => 'structured',
        ]);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-td-2',
            config: $config,
            inputs: [
                'content' => PortPayload::success(['msg' => 'hello'], DataType::Json),
                'chatId' => PortPayload::success('99999', DataType::Text),
            ],
            runId: 'run-td-2',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $delivery = $result['deliveryResult']->value;
        $this->assertSame('99999', $delivery['chatId']);
    }

    #[Test]
    public function execute_uses_default_chat_id_when_input_empty(): void
    {
        $config = array_merge($this->template->defaultConfig(), [
            'botToken' => 'stub',
            'defaultChatId' => '55555',
            'messageFormat' => 'text',
        ]);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-td-3',
            config: $config,
            inputs: [
                'content' => PortPayload::success(['data' => 'value'], DataType::Json),
            ],
            runId: 'run-td-3',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $delivery = $result['deliveryResult']->value;
        $this->assertSame('55555', $delivery['chatId']);
    }

    #[Test]
    public function execute_respects_max_message_length(): void
    {
        $config = array_merge($this->template->defaultConfig(), [
            'botToken' => 'stub',
            'defaultChatId' => '12345',
            'messageFormat' => 'text',
            'includeTimestamp' => false,
            'maxMessageLength' => 100,
        ]);

        // Create content that would produce a long message
        $longContent = ['data' => str_repeat('A', 200)];

        $ctx = new NodeExecutionContext(
            nodeId: 'node-td-4',
            config: $config,
            inputs: [
                'content' => PortPayload::success($longContent, DataType::Json),
            ],
            runId: 'run-td-4',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $delivery = $result['deliveryResult']->value;
        $this->assertLessThanOrEqual(100, $delivery['messageLength']);
    }

    #[Test]
    public function execute_with_string_content(): void
    {
        $config = array_merge($this->template->defaultConfig(), [
            'botToken' => 'stub',
            'defaultChatId' => '12345',
            'messageFormat' => 'text',
            'includeTimestamp' => false,
        ]);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-td-5',
            config: $config,
            inputs: [
                'content' => PortPayload::success('Plain text content', DataType::Json),
            ],
            runId: 'run-td-5',
            providerRouter: $this->createMock(ProviderRouter::class),
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertTrue($result['deliveryResult']->isSuccess());
        $delivery = $result['deliveryResult']->value;
        $this->assertTrue($delivery['ok']);
    }
}
