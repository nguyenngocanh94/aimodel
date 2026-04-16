<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\PortDefinition;
use App\Domain\PortPayload;
use App\Domain\PortSchema;
use PHPUnit\Framework\TestCase;

class StubTemplate extends NodeTemplate
{
    public string $type { get => 'stubNode'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Stub Node'; }
    public NodeCategory $category { get => NodeCategory::Utility; }
    public string $description { get => 'A test stub node'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [
                PortDefinition::input('textIn', 'Text Input', DataType::Text),
            ],
            outputs: [
                PortDefinition::output('textOut', 'Text Output', DataType::Text),
            ],
        );
    }

    public function configRules(): array
    {
        return [
            'prefix' => 'required|string',
        ];
    }

    public function defaultConfig(): array
    {
        return [
            'prefix' => 'Hello',
        ];
    }

    public function execute(NodeExecutionContext $ctx): array
    {
        $input = $ctx->inputValue('textIn') ?? '';
        $prefix = $ctx->config['prefix'] ?? '';

        return [
            'textOut' => PortPayload::success($prefix . ' ' . $input, DataType::Text),
        ];
    }
}

class ConfigDependentStubTemplate extends StubTemplate
{
    public string $type { get => 'configDependent'; }

    public function activePorts(array $config): PortSchema
    {
        if (($config['mode'] ?? '') === 'passthrough') {
            return new PortSchema(
                inputs: [
                    PortDefinition::input('textIn', 'Text Input', DataType::Text),
                ],
                outputs: [
                    PortDefinition::output('textOut', 'Text Output', DataType::Text),
                ],
            );
        }

        return $this->ports();
    }
}

class NodeTemplateTest extends TestCase
{
    private StubTemplate $template;

    protected function setUp(): void
    {
        $this->template = new StubTemplate();
    }

    public function test_abstract_properties_are_accessible(): void
    {
        $this->assertSame('stubNode', $this->template->type);
        $this->assertSame('1.0.0', $this->template->version);
        $this->assertSame('Stub Node', $this->template->title);
        $this->assertSame(NodeCategory::Utility, $this->template->category);
        $this->assertSame('A test stub node', $this->template->description);
    }

    public function test_ports_returns_port_schema(): void
    {
        $ports = $this->template->ports();

        $this->assertInstanceOf(PortSchema::class, $ports);
        $this->assertCount(1, $ports->inputs);
        $this->assertCount(1, $ports->outputs);
        $this->assertSame('textIn', $ports->inputs[0]->key);
        $this->assertSame('textOut', $ports->outputs[0]->key);
    }

    public function test_config_rules_returns_array(): void
    {
        $rules = $this->template->configRules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('prefix', $rules);
        $this->assertSame('required|string', $rules['prefix']);
    }

    public function test_default_config_returns_array(): void
    {
        $config = $this->template->defaultConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('prefix', $config);
        $this->assertSame('Hello', $config['prefix']);
    }

    public function test_active_ports_defaults_to_ports(): void
    {
        $activePorts = $this->template->activePorts(['prefix' => 'test']);
        $allPorts = $this->template->ports();

        $this->assertEquals($allPorts, $activePorts);
    }

    public function test_active_ports_can_be_overridden(): void
    {
        $template = new ConfigDependentStubTemplate();

        $passthroughPorts = $template->activePorts(['mode' => 'passthrough']);
        $this->assertCount(1, $passthroughPorts->inputs);
        $this->assertCount(1, $passthroughPorts->outputs);

        $defaultPorts = $template->activePorts(['mode' => 'full']);
        $this->assertEquals($template->ports(), $defaultPorts);
    }

    public function test_planner_guide_returns_node_guide(): void
    {
        $guide = $this->template->plannerGuide();

        $this->assertInstanceOf(NodeGuide::class, $guide);
        $this->assertSame('stubNode', $guide->nodeId);
        $this->assertSame($this->template->type, $guide->nodeId);
        $this->assertSame($this->template->description, $guide->purpose);
    }

    public function test_default_planner_guide_derives_ports_from_template(): void
    {
        $guide = $this->template->plannerGuide();
        $ports = $this->template->ports();

        // Should have 1 input + 1 output = 2 guide ports
        $this->assertCount(count($ports->inputs) + count($ports->outputs), $guide->ports);
        $this->assertSame('textIn', $guide->ports[0]->key);
        $this->assertSame('input', $guide->ports[0]->direction);
        $this->assertSame('textOut', $guide->ports[1]->key);
        $this->assertSame('output', $guide->ports[1]->direction);
    }
}
