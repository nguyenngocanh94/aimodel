<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\Capability;
use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\TrendResearcherTemplate;
use App\Domain\PortPayload;
use App\Domain\Providers\Adapters\StubAdapter;
use App\Domain\Providers\ProviderRouter;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TrendResearcherTemplateTest extends TestCase
{
    private TrendResearcherTemplate $template;

    protected function setUp(): void
    {
        $this->template = new TrendResearcherTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('trendResearcher', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame('Trend Researcher', $this->template->title);
        $this->assertSame(NodeCategory::Script, $this->template->category);
        $this->assertStringContainsString('trend', strtolower($this->template->description));
    }

    #[Test]
    public function ports_define_context_and_topic_inputs_and_trend_brief_output(): void
    {
        $ports = $this->template->ports();

        $inputKeys = array_map(fn ($p) => $p->key, $ports->inputs);
        $this->assertContains('context', $inputKeys);
        $this->assertContains('topic', $inputKeys);

        // Both inputs are optional
        foreach ($ports->inputs as $input) {
            $this->assertFalse($input->required, "Input '{$input->key}' should be optional");
        }

        $outputKeys = array_map(fn ($p) => $p->key, $ports->outputs);
        $this->assertContains('trendBrief', $outputKeys);

        $trendBriefPort = $ports->outputs[0];
        $this->assertSame(DataType::Json, $trendBriefPort->dataType);
    }

    #[Test]
    public function default_config_targets_vietnam_tiktok(): void
    {
        $config = $this->template->defaultConfig();

        $this->assertSame('stub', $config['provider']);
        $this->assertSame('grok-3', $config['model']);
        $this->assertSame('vietnam', $config['market']);
        $this->assertSame('tiktok', $config['platform']);
        $this->assertSame('vi', $config['language']);
    }

    #[Test]
    public function execute_with_stub_returns_json_trend_brief(): void
    {
        $router = $this->createMock(ProviderRouter::class);
        $router->method('resolve')
            ->with(Capability::TextGeneration, $this->anything())
            ->willReturn(new StubAdapter());

        $ctx = new NodeExecutionContext(
            nodeId: 'node-trend-1',
            config: $this->template->defaultConfig(),
            inputs: [
                'context' => PortPayload::success(
                    ['product' => 'Vietnamese coffee brand', 'target' => 'Gen Z'],
                    DataType::Json,
                ),
                'topic' => PortPayload::success(
                    'Vietnamese coffee culture trends on TikTok',
                    DataType::Text,
                ),
            ],
            runId: 'run-trend-1',
            providerRouter: $router,
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('trendBrief', $result);
        $this->assertTrue($result['trendBrief']->isSuccess());
        $this->assertSame(DataType::Json, $result['trendBrief']->schemaType);
        $this->assertIsArray($result['trendBrief']->value);
    }

    #[Test]
    public function system_prompt_includes_market_and_platform(): void
    {
        $config = $this->template->defaultConfig();

        // Use reflection to test private method
        $reflection = new \ReflectionMethod($this->template, 'buildSystemPrompt');
        $systemPrompt = $reflection->invoke($this->template, $config);

        $this->assertStringContainsString('vietnam', $systemPrompt);
        $this->assertStringContainsString('tiktok', $systemPrompt);
        $this->assertStringContainsString('trendingFormats', $systemPrompt);
        $this->assertStringContainsString('trendingHashtags', $systemPrompt);
        $this->assertStringContainsString('contentAngles', $systemPrompt);
        $this->assertStringContainsString('avoidList', $systemPrompt);
    }
}
