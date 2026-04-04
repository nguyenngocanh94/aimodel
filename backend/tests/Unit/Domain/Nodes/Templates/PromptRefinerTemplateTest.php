<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\PromptRefinerTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\Adapters\StubAdapter;
use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PromptRefinerTemplateTest extends TestCase
{
    private PromptRefinerTemplate $template;

    protected function setUp(): void
    {
        $this->template = new PromptRefinerTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('promptRefiner', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame(NodeCategory::Script, $this->template->category);
    }

    #[Test]
    public function ports_has_scenes_input_and_prompts_output(): void
    {
        $ports = $this->template->ports();

        $this->assertCount(1, $ports->inputs);
        $this->assertSame('scenes', $ports->inputs[0]->key);
        $this->assertSame(DataType::SceneList, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('prompts', $ports->outputs[0]->key);
        $this->assertSame(DataType::PromptList, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function execute_returns_prompt_list(): void
    {
        $router = $this->createMock(ProviderRouter::class);
        $router->method('resolve')
            ->with(Capability::TextGeneration, $this->anything())
            ->willReturn(new StubAdapter());

        $scenes = [
            ['id' => 'scene-1', 'description' => 'Opening shot'],
            ['id' => 'scene-2', 'description' => 'Main action'],
        ];

        $ctx = new NodeExecutionContext(
            nodeId: 'node-4',
            config: ['provider' => 'stub'],
            inputs: [
                'scenes' => PortPayload::success($scenes, DataType::SceneList),
            ],
            runId: 'run-1',
            providerRouter: $router,
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('prompts', $result);
        $this->assertTrue($result['prompts']->isSuccess());
        $this->assertSame(DataType::PromptList, $result['prompts']->schemaType);
        $this->assertIsArray($result['prompts']->value);
        $this->assertNotEmpty($result['prompts']->value);
    }
}
