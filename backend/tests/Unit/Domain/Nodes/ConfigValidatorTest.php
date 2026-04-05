<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\Nodes\ConfigValidator;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Nodes\Templates\ImageGeneratorTemplate;
use App\Domain\Nodes\Templates\PromptRefinerTemplate;
use App\Domain\Nodes\Templates\SceneSplitterTemplate;
use App\Domain\Nodes\Templates\ScriptWriterTemplate;
use App\Domain\Nodes\Templates\UserPromptTemplate;
use Tests\TestCase;

class ConfigValidatorTest extends TestCase
{
    private ConfigValidator $validator;
    private NodeTemplateRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new ConfigValidator();
        $this->registry = new NodeTemplateRegistry();

        // Register M1 templates
        $this->registry->register(new UserPromptTemplate());
        $this->registry->register(new ScriptWriterTemplate());
        $this->registry->register(new SceneSplitterTemplate());
        $this->registry->register(new PromptRefinerTemplate());
        $this->registry->register(new ImageGeneratorTemplate());
    }

    public function test_default_config_passes_validation_for_all_templates(): void
    {
        foreach ($this->registry->all() as $template) {
            $result = $this->validator->validate(
                $template->type,
                $template->defaultConfig(),
                $this->registry,
            );

            $this->assertTrue(
                $result['valid'],
                "defaultConfig() for {$template->type} failed validation: " . json_encode($result['errors']),
            );
        }
    }

    public function test_unknown_node_type_returns_error(): void
    {
        $result = $this->validator->validate('nonExistentType', [], $this->registry);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('_type', $result['errors']);
    }

    public function test_script_writer_missing_required_field_fails(): void
    {
        $config = (new ScriptWriterTemplate())->defaultConfig();
        unset($config['style']);

        $result = $this->validator->validate('scriptWriter', $config, $this->registry);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('style', $result['errors']);
    }

    public function test_script_writer_out_of_range_duration_fails(): void
    {
        $config = (new ScriptWriterTemplate())->defaultConfig();
        $config['targetDurationSeconds'] = 0;

        $result = $this->validator->validate('scriptWriter', $config, $this->registry);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('targetDurationSeconds', $result['errors']);
    }

    public function test_script_writer_duration_too_high_fails(): void
    {
        $config = (new ScriptWriterTemplate())->defaultConfig();
        $config['targetDurationSeconds'] = 9999;

        $result = $this->validator->validate('scriptWriter', $config, $this->registry);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('targetDurationSeconds', $result['errors']);
    }

    public function test_script_writer_invalid_structure_fails(): void
    {
        $config = (new ScriptWriterTemplate())->defaultConfig();
        $config['structure'] = 'invalid';

        $result = $this->validator->validate('scriptWriter', $config, $this->registry);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('structure', $result['errors']);
    }

    public function test_image_generator_invalid_input_mode_fails(): void
    {
        $config = (new ImageGeneratorTemplate())->defaultConfig();
        $config['inputMode'] = 'invalid';

        $result = $this->validator->validate('imageGenerator', $config, $this->registry);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('inputMode', $result['errors']);
    }

    public function test_errors_return_structured_messages(): void
    {
        $config = (new ScriptWriterTemplate())->defaultConfig();
        unset($config['style']);
        $config['targetDurationSeconds'] = -1;

        $result = $this->validator->validate('scriptWriter', $config, $this->registry);

        $this->assertFalse($result['valid']);
        $this->assertIsArray($result['errors']);

        // Each error key maps to an array of messages
        foreach ($result['errors'] as $field => $messages) {
            $this->assertIsArray($messages);
            foreach ($messages as $msg) {
                $this->assertIsString($msg);
            }
        }
    }
}
