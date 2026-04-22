<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\ScriptWriterTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\Adapters\StubAdapter;
use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScriptWriterTemplateTest extends TestCase
{
    private ScriptWriterTemplate $template;

    protected function setUp(): void
    {
        $this->template = new ScriptWriterTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('scriptWriter', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame(NodeCategory::Script, $this->template->category);
    }

    #[Test]
    public function ports_has_prompt_input_and_script_output(): void
    {
        $ports = $this->template->ports();

        $this->assertCount(1, $ports->inputs);
        $this->assertSame('prompt', $ports->inputs[0]->key);
        $this->assertSame(DataType::Prompt, $ports->inputs[0]->dataType);

        $this->assertCount(1, $ports->outputs);
        $this->assertSame('script', $ports->outputs[0]->key);
        $this->assertSame(DataType::Script, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function execute_returns_script_via_provider(): void
    {
        $router = $this->createMock(ProviderRouter::class);
        $router->method('resolve')
            ->with(Capability::TextGeneration, $this->anything())
            ->willReturn(new StubAdapter());

        $ctx = new NodeExecutionContext(
            nodeId: 'node-2',
            config: ['provider' => 'stub'],
            inputs: [
                'prompt' => PortPayload::success('Write a script about nature', DataType::Prompt),
            ],
            runId: 'run-1',
            providerRouter: $router,
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('script', $result);
        $this->assertTrue($result['script']->isSuccess());
        $this->assertSame(DataType::Script, $result['script']->schemaType);
        $this->assertIsArray($result['script']->value);
        $this->assertArrayHasKey('title', $result['script']->value);
    }
}
