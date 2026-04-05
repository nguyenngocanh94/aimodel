<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Nodes\NodeTemplateRegistry;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ConfigValidationTest extends TestCase
{
    private NodeTemplateRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(NodeTemplateRegistry::class);
    }

    private function validateConfig(string $type, array $config): \Illuminate\Validation\Validator
    {
        $template = $this->registry->get($type);
        $this->assertNotNull($template, "Template '{$type}' not found in registry");

        return Validator::make($config, $template->configRules());
    }

    #[Test]
    #[DataProvider('templatesWithValidDefaults')]
    public function default_config_passes_validation(string $type): void
    {
        $template = $this->registry->get($type);
        if ($template === null) {
            $this->markTestSkipped("Template '{$type}' not registered");
        }

        $rules = $template->configRules();
        if (empty($rules)) {
            $this->assertTrue(true);
            return;
        }

        $validator = Validator::make($template->defaultConfig(), $rules);
        $this->assertTrue(
            $validator->passes(),
            "Default config for '{$type}' fails validation: " . json_encode($validator->errors()->all())
        );
    }

    /**
     * Templates whose defaultConfig() passes validation.
     * userPrompt excluded: its default has empty prompt (user must fill in).
     */
    public static function templatesWithValidDefaults(): iterable
    {
        yield 'scriptWriter' => ['scriptWriter'];
        yield 'sceneSplitter' => ['sceneSplitter'];
        yield 'promptRefiner' => ['promptRefiner'];
        yield 'imageGenerator' => ['imageGenerator'];
        yield 'reviewCheckpoint' => ['reviewCheckpoint'];
        yield 'imageAssetMapper' => ['imageAssetMapper'];
        yield 'ttsVoiceoverPlanner' => ['ttsVoiceoverPlanner'];
        yield 'subtitleFormatter' => ['subtitleFormatter'];
        yield 'videoComposer' => ['videoComposer'];
        yield 'finalExport' => ['finalExport'];
    }

    public static function allTemplateTypes(): iterable
    {
        yield 'userPrompt' => ['userPrompt'];
        yield 'scriptWriter' => ['scriptWriter'];
        yield 'sceneSplitter' => ['sceneSplitter'];
        yield 'promptRefiner' => ['promptRefiner'];
        yield 'imageGenerator' => ['imageGenerator'];
        yield 'reviewCheckpoint' => ['reviewCheckpoint'];
        yield 'imageAssetMapper' => ['imageAssetMapper'];
        yield 'ttsVoiceoverPlanner' => ['ttsVoiceoverPlanner'];
        yield 'subtitleFormatter' => ['subtitleFormatter'];
        yield 'videoComposer' => ['videoComposer'];
        yield 'finalExport' => ['finalExport'];
    }

    #[Test]
    public function user_prompt_rejects_empty_prompt(): void
    {
        $v = $this->validateConfig('userPrompt', ['prompt' => '']);
        $this->assertTrue($v->fails());
    }

    #[Test]
    public function user_prompt_rejects_missing_prompt(): void
    {
        $v = $this->validateConfig('userPrompt', []);
        $this->assertTrue($v->fails());
    }

    #[Test]
    public function script_writer_rejects_invalid_structure(): void
    {
        $config = $this->registry->get('scriptWriter')->defaultConfig();
        $config['structure'] = 'invalid_structure';

        $v = $this->validateConfig('scriptWriter', $config);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('structure', $v->errors()->toArray());
    }

    #[Test]
    public function script_writer_rejects_zero_duration(): void
    {
        $config = $this->registry->get('scriptWriter')->defaultConfig();
        $config['targetDurationSeconds'] = 0;

        $v = $this->validateConfig('scriptWriter', $config);
        $this->assertTrue($v->fails());
    }

    #[Test]
    public function script_writer_rejects_excessive_duration(): void
    {
        $config = $this->registry->get('scriptWriter')->defaultConfig();
        $config['targetDurationSeconds'] = 9999;

        $v = $this->validateConfig('scriptWriter', $config);
        $this->assertTrue($v->fails());
    }

    #[Test]
    public function script_writer_rejects_missing_required_fields(): void
    {
        $v = $this->validateConfig('scriptWriter', []);
        $this->assertTrue($v->fails());

        $errors = $v->errors()->keys();
        $this->assertContains('style', $errors);
        $this->assertContains('structure', $errors);
        $this->assertContains('provider', $errors);
    }

    #[Test]
    public function image_generator_rejects_invalid_input_mode(): void
    {
        $v = $this->validateConfig('imageGenerator', ['inputMode' => 'invalid']);
        $this->assertTrue($v->fails());
    }

    #[Test]
    public function image_generator_rejects_invalid_output_mode(): void
    {
        $v = $this->validateConfig('imageGenerator', ['outputMode' => 'invalid']);
        $this->assertTrue($v->fails());
    }

    #[Test]
    public function prompt_refiner_rejects_invalid_aspect_ratio(): void
    {
        $config = $this->registry->get('promptRefiner')->defaultConfig();
        $config['aspectRatio'] = '3:2';

        $v = $this->validateConfig('promptRefiner', $config);
        $this->assertTrue($v->fails());
    }

    #[Test]
    public function prompt_refiner_rejects_invalid_detail_level(): void
    {
        $config = $this->registry->get('promptRefiner')->defaultConfig();
        $config['detailLevel'] = 'ultra';

        $v = $this->validateConfig('promptRefiner', $config);
        $this->assertTrue($v->fails());
    }

    #[Test]
    public function validation_returns_structured_errors(): void
    {
        $v = $this->validateConfig('scriptWriter', ['style' => '', 'structure' => 'bad']);
        $this->assertTrue($v->fails());

        $errors = $v->errors()->toArray();
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);

        // Each error key maps to an array of message strings
        foreach ($errors as $field => $messages) {
            $this->assertIsString($field);
            $this->assertIsArray($messages);
            foreach ($messages as $msg) {
                $this->assertIsString($msg);
            }
        }
    }
}
