<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\SubtitleFormatterTemplate;
use App\Domain\PortPayload;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SubtitleFormatterTemplateTest extends TestCase
{
    private SubtitleFormatterTemplate $template;

    protected function setUp(): void
    {
        $this->template = new SubtitleFormatterTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('subtitleFormatter', $this->template->type);
        $this->assertSame(NodeCategory::Audio, $this->template->category);
    }

    #[Test]
    public function ports_define_audio_plan_input_and_subtitles_output(): void
    {
        $ports = $this->template->ports();
        $this->assertSame('audioPlan', $ports->inputs[0]->key);
        $this->assertSame(DataType::AudioPlan, $ports->inputs[0]->dataType);
        $this->assertSame('subtitles', $ports->outputs[0]->key);
        $this->assertSame(DataType::SubtitleAsset, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function execute_with_stub_returns_structured_subtitles(): void
    {
        $ctx = new NodeExecutionContext(
            nodeId: 'node-sub-1',
            config: $this->template->defaultConfig(),
            inputs: [
                'audioPlan' => PortPayload::success(
                    ['segments' => [['text' => 'Hello', 'start' => 0, 'end' => 1]]],
                    DataType::AudioPlan,
                ),
            ],
            runId: 'run-sub-1',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('subtitles', $result);
        $this->assertTrue($result['subtitles']->isSuccess());
        $this->assertIsArray($result['subtitles']->value);
    }

    #[Test]
    public function config_rules_expose_llm_keys(): void
    {
        $rules = $this->template->configRules();
        $this->assertArrayHasKey('llm.provider', $rules);
        $this->assertArrayHasKey('llm.model', $rules);
    }
}
