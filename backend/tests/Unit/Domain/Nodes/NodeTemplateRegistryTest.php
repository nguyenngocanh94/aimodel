<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\DataType;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeGuide;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\PortDefinition;
use App\Domain\PortSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NodeTemplateRegistryTest extends TestCase
{
    private NodeTemplateRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new NodeTemplateRegistry();
    }

    private function createMockTemplate(string $type = 'test-node'): NodeTemplate
    {
        return new class($type) extends NodeTemplate {
            public function __construct(
                public string $type,
                public string $version = '1.0.0',
                public string $title = 'Test Node',
                public NodeCategory $category = NodeCategory::Utility,
                public string $description = 'A test node',
            ) {}

            public function ports(): PortSchema
            {
                return new PortSchema(
                    inputs: [PortDefinition::input('in', 'Input', DataType::Text)],
                    outputs: [PortDefinition::output('out', 'Output', DataType::Text)],
                );
            }

            public function configRules(): array
            {
                return ['name' => 'required|string'];
            }

            public function defaultConfig(): array
            {
                return ['name' => 'default'];
            }

            public function execute(NodeExecutionContext $ctx): array
            {
                return [];
            }
        };
    }

    #[Test]
    public function register_and_get(): void
    {
        $template = $this->createMockTemplate('my-node');
        $this->registry->register($template);

        $result = $this->registry->get('my-node');
        $this->assertSame($template, $result);
    }

    #[Test]
    public function get_returns_null_for_unknown_type(): void
    {
        $this->assertNull($this->registry->get('nonexistent'));
    }

    #[Test]
    public function all_returns_registered_templates(): void
    {
        $t1 = $this->createMockTemplate('node-a');
        $t2 = $this->createMockTemplate('node-b');

        $this->registry->register($t1);
        $this->registry->register($t2);

        $all = $this->registry->all();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('node-a', $all);
        $this->assertArrayHasKey('node-b', $all);
    }

    #[Test]
    public function metadata_returns_structured_array(): void
    {
        $this->registry->register($this->createMockTemplate('my-node'));

        $meta = $this->registry->metadata();

        $this->assertCount(1, $meta);
        $entry = $meta[0];

        $this->assertSame('my-node', $entry['type']);
        $this->assertSame('1.0.0', $entry['version']);
        $this->assertSame('Test Node', $entry['title']);
        $this->assertSame('utility', $entry['category']);
        $this->assertSame('A test node', $entry['description']);
        $this->assertCount(1, $entry['inputs']);
        $this->assertCount(1, $entry['outputs']);
        $this->assertSame('in', $entry['inputs'][0]['key']);
        $this->assertSame('out', $entry['outputs'][0]['key']);
    }

    #[Test]
    public function register_overwrites_same_type(): void
    {
        $t1 = $this->createMockTemplate('node-a');
        $t2 = $this->createMockTemplate('node-a');

        $this->registry->register($t1);
        $this->registry->register($t2);

        $this->assertCount(1, $this->registry->all());
        $this->assertSame($t2, $this->registry->get('node-a'));
    }

    #[Test]
    public function guides_returns_all_node_guides_keyed_by_type(): void
    {
        $this->registry->register($this->createMockTemplate('node-a'));
        $this->registry->register($this->createMockTemplate('node-b'));

        $guides = $this->registry->guides();

        $this->assertIsArray($guides);
        $this->assertCount(2, $guides);
        foreach ($guides as $type => $guide) {
            $this->assertInstanceOf(NodeGuide::class, $guide);
            $this->assertSame($type, $guide->nodeId);
        }
    }

    #[Test]
    public function guides_yaml_returns_concatenated_yaml(): void
    {
        $this->registry->register($this->createMockTemplate('node-a'));
        $this->registry->register($this->createMockTemplate('node-b'));

        $yaml = $this->registry->guidesYaml();

        $this->assertIsString($yaml);
        // Should contain the separator between node cards
        $this->assertStringContainsString('---', $yaml);
        // Should contain both node IDs
        $this->assertStringContainsString('node-a', $yaml);
        $this->assertStringContainsString('node-b', $yaml);
    }
}
