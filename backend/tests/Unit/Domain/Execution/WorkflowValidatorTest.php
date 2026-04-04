<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use App\Domain\DataType;
use App\Domain\Execution\WorkflowValidator;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\PortDefinition;
use App\Domain\PortSchema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WorkflowValidatorTest extends TestCase
{
    private WorkflowValidator $validator;
    private NodeTemplateRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new WorkflowValidator();
        $this->registry = new NodeTemplateRegistry();
        $this->registry->register($this->createTemplate('writer', DataType::Prompt, DataType::Script));
        $this->registry->register($this->createTemplate('splitter', DataType::Script, DataType::SceneList));
    }

    private function createTemplate(string $type, DataType $inputType, DataType $outputType): NodeTemplate
    {
        return new class($type, $inputType, $outputType) extends NodeTemplate {
            public function __construct(
                public string $type,
                private DataType $inputType,
                private DataType $outputType,
                public string $version = '1.0.0',
                public string $title = 'Test',
                public NodeCategory $category = NodeCategory::Script,
                public string $description = 'Test template',
            ) {}

            public function ports(): PortSchema
            {
                return new PortSchema(
                    inputs: [PortDefinition::input('in', 'Input', $this->inputType)],
                    outputs: [PortDefinition::output('out', 'Output', $this->outputType)],
                );
            }

            public function configRules(): array { return []; }
            public function defaultConfig(): array { return []; }
            public function execute(NodeExecutionContext $ctx): array { return []; }
        };
    }

    private function createInputTemplate(): NodeTemplate
    {
        return new class extends NodeTemplate {
            public string $type = 'input-node';
            public string $version = '1.0.0';
            public string $title = 'Input';
            public NodeCategory $category = NodeCategory::Input;
            public string $description = 'Input node';

            public function ports(): PortSchema
            {
                return new PortSchema(
                    inputs: [],
                    outputs: [PortDefinition::output('out', 'Output', DataType::Prompt)],
                );
            }

            public function configRules(): array { return []; }
            public function defaultConfig(): array { return []; }
            public function execute(NodeExecutionContext $ctx): array { return []; }
        };
    }

    #[Test]
    public function valid_document_returns_no_errors(): void
    {
        $this->registry->register($this->createInputTemplate());

        $document = [
            'nodes' => [
                ['id' => 'n0', 'type' => 'input-node', 'config' => []],
                ['id' => 'n1', 'type' => 'writer', 'config' => []],
                ['id' => 'n2', 'type' => 'splitter', 'config' => []],
            ],
            'edges' => [
                ['source' => 'n0', 'target' => 'n1', 'sourceHandle' => 'out', 'targetHandle' => 'in'],
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'out', 'targetHandle' => 'in'],
            ],
        ];

        $issues = $this->validator->validate($document, $this->registry);
        $errors = array_filter($issues, fn ($i) => $i['severity'] === 'error');

        $this->assertCount(0, $errors);
    }

    #[Test]
    public function detects_cycle(): void
    {
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'writer', 'config' => []],
                ['id' => 'n2', 'type' => 'splitter', 'config' => []],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'out', 'targetHandle' => 'in'],
                ['source' => 'n2', 'target' => 'n1', 'sourceHandle' => 'out', 'targetHandle' => 'in'],
            ],
        ];

        $issues = $this->validator->validate($document, $this->registry);
        $cycles = array_filter($issues, fn ($i) => $i['type'] === 'cycle_detected');

        $this->assertNotEmpty($cycles);
    }

    #[Test]
    public function detects_unknown_node_type(): void
    {
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'nonexistent', 'config' => []],
            ],
            'edges' => [],
        ];

        $issues = $this->validator->validate($document, $this->registry);
        $unknown = array_filter($issues, fn ($i) => $i['type'] === 'unknown_type');

        $this->assertCount(1, $unknown);
    }

    #[Test]
    public function detects_type_incompatibility(): void
    {
        // Connect splitter (output=SceneList) to writer (input=Prompt) — incompatible
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'splitter', 'config' => []],
                ['id' => 'n2', 'type' => 'writer', 'config' => []],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'out', 'targetHandle' => 'in'],
            ],
        ];

        $issues = $this->validator->validate($document, $this->registry);
        $typeIssues = array_filter($issues, fn ($i) => $i['type'] === 'type_incompatible');

        $this->assertNotEmpty($typeIssues);
    }

    #[Test]
    public function detects_missing_required_input(): void
    {
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'writer', 'config' => []],
            ],
            'edges' => [],
        ];

        $issues = $this->validator->validate($document, $this->registry);
        $missing = array_filter($issues, fn ($i) => $i['type'] === 'missing_input');

        $this->assertNotEmpty($missing);
    }

    #[Test]
    public function detects_inactive_port_connection(): void
    {
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'writer', 'config' => []],
                ['id' => 'n2', 'type' => 'splitter', 'config' => []],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'nonexistent-port', 'targetHandle' => 'in'],
            ],
        ];

        $issues = $this->validator->validate($document, $this->registry);
        $inactive = array_filter($issues, fn ($i) => $i['type'] === 'inactive_port');

        $this->assertNotEmpty($inactive);
    }

    #[Test]
    public function detects_orphan_nodes_as_warning(): void
    {
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'writer', 'config' => []],
                ['id' => 'n2', 'type' => 'splitter', 'config' => []],
            ],
            'edges' => [],
        ];

        $issues = $this->validator->validate($document, $this->registry);
        $orphans = array_filter($issues, fn ($i) => $i['type'] === 'orphan_node');

        $this->assertNotEmpty($orphans);
        foreach ($orphans as $orphan) {
            $this->assertSame('warning', $orphan['severity']);
        }
    }

    #[Test]
    public function config_validation_detects_invalid_config(): void
    {
        // Create a template with config rules
        $template = new class extends NodeTemplate {
            public string $type = 'configured-node';
            public string $version = '1.0.0';
            public string $title = 'Configured Node';
            public NodeCategory $category = NodeCategory::Utility;
            public string $description = 'Needs config';

            public function ports(): PortSchema { return new PortSchema(); }
            public function configRules(): array { return ['name' => 'required|string']; }
            public function defaultConfig(): array { return ['name' => 'default']; }
            public function execute(NodeExecutionContext $ctx): array { return []; }
        };

        $this->registry->register($template);

        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'configured-node', 'config' => []], // missing required 'name'
            ],
            'edges' => [],
        ];

        $issues = $this->validator->validate($document, $this->registry);
        $configIssues = array_filter($issues, fn ($i) => $i['type'] === 'config_invalid');

        $this->assertNotEmpty($configIssues);
    }
}
