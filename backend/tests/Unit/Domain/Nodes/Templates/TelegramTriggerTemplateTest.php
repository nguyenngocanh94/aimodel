<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\TelegramTriggerTemplate;
use App\Domain\PortPayload;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TelegramTriggerTemplateTest extends TestCase
{
    private TelegramTriggerTemplate $template;

    protected function setUp(): void
    {
        $this->template = new TelegramTriggerTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('telegramTrigger', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame('Telegram Trigger', $this->template->title);
        $this->assertSame(NodeCategory::Input, $this->template->category);
        $this->assertStringContainsString('Telegram message', $this->template->description);
    }

    #[Test]
    public function ports_has_no_inputs_and_four_outputs(): void
    {
        $ports = $this->template->ports();

        $this->assertCount(0, $ports->inputs);
        $this->assertCount(4, $ports->outputs);

        $outputKeys = array_map(fn ($p) => $p->key, $ports->outputs);
        $this->assertContains('message', $outputKeys);
        $this->assertContains('text', $outputKeys);
        $this->assertContains('images', $outputKeys);
        $this->assertContains('triggerInfo', $outputKeys);

        $this->assertSame(DataType::Json, $ports->getOutput('message')->dataType);
        $this->assertSame(DataType::Text, $ports->getOutput('text')->dataType);
        $this->assertSame(DataType::ImageAssetList, $ports->getOutput('images')->dataType);
        $this->assertSame(DataType::Json, $ports->getOutput('triggerInfo')->dataType);
    }

    #[Test]
    public function default_config_has_expected_values(): void
    {
        $config = $this->template->defaultConfig();

        $this->assertSame('', $config['botToken']);
        $this->assertSame([], $config['allowedChatIds']);
        $this->assertTrue($config['extractImages']);
        $this->assertSame(5, $config['maxImages']);
        $this->assertSame([], $config['filterKeywords']);
    }

    #[Test]
    public function config_rules_require_bot_token(): void
    {
        $rules = $this->template->configRules();

        $this->assertArrayHasKey('botToken', $rules);
        $this->assertContains('required', $rules['botToken']);
    }

    #[Test]
    public function execute_returns_idle_when_no_trigger_payload(): void
    {
        $ctx = new NodeExecutionContext(
            nodeId: 'node-tt-1',
            config: $this->template->defaultConfig(),
            inputs: [],
            runId: 'run-1',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('text', $result);
        $this->assertArrayHasKey('images', $result);
        $this->assertArrayHasKey('triggerInfo', $result);

        $this->assertTrue($result['message']->isIdle());
        $this->assertTrue($result['text']->isIdle());
        $this->assertTrue($result['images']->isIdle());
        $this->assertTrue($result['triggerInfo']->isIdle());
    }

    #[Test]
    public function execute_parses_text_message_from_trigger_payload(): void
    {
        $telegramUpdate = [
            'message' => [
                'message_id' => 42,
                'from' => ['id' => 12345, 'username' => 'testuser'],
                'chat' => ['id' => 67890],
                'date' => 1700000000,
                'text' => 'Hello workflow!',
            ],
        ];

        $config = array_merge($this->template->defaultConfig(), [
            '_triggerPayload' => $telegramUpdate,
        ]);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-tt-2',
            config: $config,
            inputs: [],
            runId: 'run-2',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        // message output
        $this->assertTrue($result['message']->isSuccess());
        $this->assertSame(DataType::Json, $result['message']->schemaType);
        $this->assertSame(42, $result['message']->value['message_id']);

        // text output
        $this->assertTrue($result['text']->isSuccess());
        $this->assertSame('Hello workflow!', $result['text']->value);

        // images output (no photos in this message)
        $this->assertTrue($result['images']->isSuccess());
        $this->assertSame([], $result['images']->value);

        // triggerInfo output
        $this->assertTrue($result['triggerInfo']->isSuccess());
        $this->assertSame('67890', $result['triggerInfo']->value['chatId']);
        $this->assertSame(42, $result['triggerInfo']->value['messageId']);
        $this->assertSame('testuser', $result['triggerInfo']->value['fromUsername']);
    }

    #[Test]
    public function execute_extracts_photos_from_trigger_payload(): void
    {
        $telegramUpdate = [
            'message' => [
                'message_id' => 99,
                'from' => ['id' => 111],
                'chat' => ['id' => 222],
                'date' => 1700000001,
                'caption' => 'Check this out',
                'photo' => [
                    ['file_id' => 'small_id', 'width' => 90, 'height' => 90],
                    ['file_id' => 'medium_id', 'width' => 320, 'height' => 320],
                    ['file_id' => 'large_id', 'width' => 800, 'height' => 800],
                ],
            ],
        ];

        $config = array_merge($this->template->defaultConfig(), [
            '_triggerPayload' => $telegramUpdate,
        ]);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-tt-3',
            config: $config,
            inputs: [],
            runId: 'run-3',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        // text should come from caption
        $this->assertSame('Check this out', $result['text']->value);

        // images should contain the largest variant
        $images = $result['images']->value;
        $this->assertCount(1, $images);
        $this->assertSame('large_id', $images[0]['fileId']);
        $this->assertSame(800, $images[0]['width']);
    }

    #[Test]
    public function execute_skips_image_extraction_when_disabled(): void
    {
        $telegramUpdate = [
            'message' => [
                'message_id' => 100,
                'from' => ['id' => 111],
                'chat' => ['id' => 222],
                'date' => 1700000002,
                'text' => 'Photo message',
                'photo' => [
                    ['file_id' => 'photo_id', 'width' => 800, 'height' => 600],
                ],
            ],
        ];

        $config = array_merge($this->template->defaultConfig(), [
            'extractImages' => false,
            '_triggerPayload' => $telegramUpdate,
        ]);

        $ctx = new NodeExecutionContext(
            nodeId: 'node-tt-4',
            config: $config,
            inputs: [],
            runId: 'run-4',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertSame([], $result['images']->value);
    }
}
