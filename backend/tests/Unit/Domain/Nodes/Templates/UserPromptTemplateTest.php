<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\UserPromptTemplate;
use App\Domain\PortPayload;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserPromptTemplateTest extends TestCase
{
    private UserPromptTemplate $template;

    protected function setUp(): void
    {
        $this->template = new UserPromptTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('userPrompt', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame('User Prompt', $this->template->title);
        $this->assertSame(NodeCategory::Input, $this->template->category);
    }

    #[Test]
    public function ports_has_no_inputs_and_one_prompt_output(): void
    {
        $ports = $this->template->ports();

        $this->assertCount(0, $ports->inputs);
        $this->assertCount(1, $ports->outputs);
        $this->assertSame('prompt', $ports->outputs[0]->key);
        $this->assertSame(DataType::Prompt, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function execute_returns_prompt_from_config(): void
    {
        $ctx = new NodeExecutionContext(
            nodeId: 'node-1',
            config: ['prompt' => 'Create a video about cats'],
            inputs: [],
            runId: 'run-1',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('prompt', $result);
        $this->assertInstanceOf(PortPayload::class, $result['prompt']);
        $this->assertTrue($result['prompt']->isSuccess());
        $this->assertSame('Create a video about cats', $result['prompt']->value);
        $this->assertSame(DataType::Prompt, $result['prompt']->schemaType);
    }

    #[Test]
    public function config_rules_require_prompt(): void
    {
        $rules = $this->template->configRules();

        $this->assertArrayHasKey('prompt', $rules);
    }

    #[Test]
    public function default_config_has_empty_prompt(): void
    {
        $config = $this->template->defaultConfig();

        $this->assertSame('', $config['prompt']);
    }
}
