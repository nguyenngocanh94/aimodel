<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Execution;

use App\Domain\DataType;
use App\Domain\Execution\InputResolver;
use App\Domain\Execution\RunCache;
use App\Domain\NodeCategory;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\PortDefinition;
use App\Domain\PortSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InputResolverTest extends TestCase
{
    use RefreshDatabase;

    private InputResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new InputResolver(new RunCache());
    }

    private function makeTemplate(
        string $type = 'test-node',
        array $inputs = [],
        array $outputs = [],
    ): NodeTemplate {
        return new class($type, $inputs, $outputs) extends NodeTemplate {
            public function __construct(
                public string $type,
                private array $inputPorts,
                private array $outputPorts,
                public string $version = '1.0.0',
                public string $title = 'Test',
                public NodeCategory $category = NodeCategory::Script,
                public string $description = 'test',
            ) {}

            public function ports(): PortSchema
            {
                return new PortSchema($this->inputPorts, $this->outputPorts);
            }

            public function configRules(): array { return []; }
            public function defaultConfig(): array { return []; }
            public function execute(NodeExecutionContext $ctx): array { return []; }
        };
    }

    #[Test]
    public function resolves_from_upstream_run_output(): void
    {
        $template = $this->makeTemplate('writer', [
            PortDefinition::input('in', 'Input', DataType::Prompt),
        ]);

        $node = ['id' => 'n2', 'type' => 'writer', 'config' => []];
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'input-node'],
                ['id' => 'n2', 'type' => 'writer'],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'out', 'targetHandle' => 'in'],
            ],
        ];

        $nodeRunRecords = [
            'n1' => [
                'status' => 'success',
                'output_payloads' => [
                    'out' => [
                        'value' => 'Hello prompt',
                        'status' => 'success',
                        'schemaType' => 'prompt',
                    ],
                ],
            ],
        ];

        $result = $this->resolver->resolve($node, $template, $document, $nodeRunRecords);

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('in', $result['inputs']);
        $this->assertSame('Hello prompt', $result['inputs']['in']->value);
    }

    #[Test]
    public function fails_when_required_input_has_no_connection(): void
    {
        $template = $this->makeTemplate('writer', [
            PortDefinition::input('in', 'Input', DataType::Prompt),
        ]);

        $node = ['id' => 'n1', 'type' => 'writer', 'config' => []];
        $document = [
            'nodes' => [['id' => 'n1', 'type' => 'writer']],
            'edges' => [],
        ];

        $result = $this->resolver->resolve($node, $template, $document, []);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('no connection', $result['reason']);
    }

    #[Test]
    public function blocked_when_upstream_not_run_yet(): void
    {
        $template = $this->makeTemplate('writer', [
            PortDefinition::input('in', 'Input', DataType::Prompt),
        ]);

        $node = ['id' => 'n2', 'type' => 'writer', 'config' => []];
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'input-node'],
                ['id' => 'n2', 'type' => 'writer'],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'out', 'targetHandle' => 'in'],
            ],
        ];

        // n1 has not run yet — empty records
        $result = $this->resolver->resolve($node, $template, $document, []);

        $this->assertFalse($result['ok']);
        $this->assertContains('n1', $result['blockedBy']);
    }

    #[Test]
    public function skips_optional_input_with_no_connection(): void
    {
        $template = $this->makeTemplate('writer', [
            PortDefinition::input('in', 'Input', DataType::Prompt, required: false),
        ]);

        $node = ['id' => 'n1', 'type' => 'writer', 'config' => []];
        $document = [
            'nodes' => [['id' => 'n1', 'type' => 'writer']],
            'edges' => [],
        ];

        $result = $this->resolver->resolve($node, $template, $document, []);

        $this->assertTrue($result['ok']);
        $this->assertEmpty($result['inputs']);
    }

    #[Test]
    public function resolves_from_document_preview_data(): void
    {
        $template = $this->makeTemplate('writer', [
            PortDefinition::input('in', 'Input', DataType::Prompt),
        ]);

        $node = ['id' => 'n2', 'type' => 'writer', 'config' => []];
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'input-node', 'data' => [
                    'out' => [
                        'value' => 'preview prompt',
                        'status' => 'success',
                        'schemaType' => 'prompt',
                    ],
                ]],
                ['id' => 'n2', 'type' => 'writer'],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'out', 'targetHandle' => 'in'],
            ],
        ];

        // n1 ran but failed — so priority 1 doesn't match
        $nodeRunRecords = [
            'n1' => ['status' => 'error', 'output_payloads' => []],
        ];

        $result = $this->resolver->resolve($node, $template, $document, $nodeRunRecords);

        $this->assertTrue($result['ok']);
        $this->assertSame('preview prompt', $result['inputs']['in']->value);
    }

    #[Test]
    public function resolves_with_cache_hit(): void
    {
        $cache = new RunCache();

        // Seed the cache with output data
        $cacheKey = 'test-cache-key-' . uniqid();
        $cache->put($cacheKey, 'writer', '1.0.0', [
            'out' => [
                'value' => 'cached output',
                'status' => 'success',
                'schemaType' => 'script',
            ],
        ]);

        $resolver = new InputResolver($cache);

        $template = $this->makeTemplate('splitter', [
            PortDefinition::input('in', 'Input', DataType::Script),
        ]);

        $node = ['id' => 'n2', 'type' => 'splitter', 'config' => []];
        $document = [
            'nodes' => [
                ['id' => 'n1', 'type' => 'writer'],
                ['id' => 'n2', 'type' => 'splitter'],
            ],
            'edges' => [
                ['source' => 'n1', 'target' => 'n2', 'sourceHandle' => 'out', 'targetHandle' => 'in'],
            ],
        ];

        // n1 didn't run successfully in this run, but has a cache key
        $nodeRunRecords = [
            'n1' => ['status' => 'pending', 'output_payloads' => [], 'cache_key' => $cacheKey],
        ];

        $result = $resolver->resolve($node, $template, $document, $nodeRunRecords);

        $this->assertTrue($result['ok']);
        $this->assertSame('cached output', $result['inputs']['in']->value);
    }
}
