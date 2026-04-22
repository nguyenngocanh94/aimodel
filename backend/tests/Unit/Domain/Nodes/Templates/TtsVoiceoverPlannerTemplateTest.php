<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes\Templates;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\Templates\TtsVoiceoverPlannerTemplate;
use App\Domain\PortPayload;
use App\Services\ArtifactStoreContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TtsVoiceoverPlannerTemplateTest extends TestCase
{
    private TtsVoiceoverPlannerTemplate $template;

    protected function setUp(): void
    {
        $this->template = new TtsVoiceoverPlannerTemplate();
    }

    #[Test]
    public function has_correct_metadata(): void
    {
        $this->assertSame('ttsVoiceoverPlanner', $this->template->type);
        $this->assertSame(NodeCategory::Audio, $this->template->category);
    }

    #[Test]
    public function ports_define_scenes_input_and_audio_plan_output(): void
    {
        $ports = $this->template->ports();
        $this->assertSame('scenes', $ports->inputs[0]->key);
        $this->assertSame(DataType::SceneList, $ports->inputs[0]->dataType);
        $this->assertSame('audioPlan', $ports->outputs[0]->key);
        $this->assertSame(DataType::AudioPlan, $ports->outputs[0]->dataType);
    }

    #[Test]
    public function execute_with_stub_returns_audio_plan(): void
    {
        $ctx = new NodeExecutionContext(
            nodeId: 'node-tts-1',
            config: $this->template->defaultConfig(),
            inputs: [
                'scenes' => PortPayload::success(
                    [['id' => 'scene-1', 'description' => 'Opening']],
                    DataType::SceneList,
                ),
            ],
            runId: 'run-tts-1',
            artifactStore: $this->createMock(ArtifactStoreContract::class),
        );

        $result = $this->template->execute($ctx);

        $this->assertArrayHasKey('audioPlan', $result);
        $this->assertTrue($result['audioPlan']->isSuccess());
    }

    #[Test]
    public function config_rules_expose_llm_keys(): void
    {
        $rules = $this->template->configRules();
        $this->assertArrayHasKey('llm.provider', $rules);
    }
}
