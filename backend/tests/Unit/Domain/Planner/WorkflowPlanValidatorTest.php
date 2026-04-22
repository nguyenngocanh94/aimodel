<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Planner;

use App\Domain\DataType;
use App\Domain\Execution\TypeCompatibility;
use App\Domain\NodeCategory;
use App\Domain\Nodes\ConfigSchemaTranspiler;
use App\Domain\Nodes\NodeExecutionContext;
use App\Domain\Nodes\NodeTemplate;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Planner\PlanEdge;
use App\Domain\Planner\PlanNode;
use App\Domain\Planner\WorkflowPlan;
use App\Domain\Planner\WorkflowPlanValidator;
use App\Domain\PortDefinition;
use App\Domain\PortSchema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers each validation surface with a passing + failing fixture.
 */
final class WorkflowPlanValidatorTest extends TestCase
{
    private WorkflowPlanValidator $validator;
    private NodeTemplateRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new NodeTemplateRegistry();
        $this->registry->register(new FakeInputTemplate());
        $this->registry->register(new FakeWriterTemplate());
        $this->registry->register(new FakeSplitterTemplate());

        $this->validator = new WorkflowPlanValidator(
            registry: $this->registry,
            typeCompat: new TypeCompatibility(),
            transpiler: new ConfigSchemaTranspiler(),
        );
    }

    // ── Happy path ────────────────────────────────────────────────────────

    #[Test]
    public function valid_plan_produces_no_errors(): void
    {
        $plan = $this->validThreeNodePlan();
        $result = $this->validator->validate($plan);

        $this->assertTrue($result->valid, 'Plan should be valid. Errors: ' . json_encode($result->errors));
        $this->assertSame([], $result->errors);
    }

    // ── Structural: empty / duplicates / self-loops / cycles ──────────────

    #[Test]
    public function empty_plan_is_rejected(): void
    {
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v', nodes: [], edges: [],
            assumptions: [], rationale: 'empty',
        );

        $result = $this->validator->validate($plan);
        $this->assertFalse($result->valid);
        $this->assertContains(WorkflowPlanValidator::CODE_EMPTY_PLAN, $result->errorCodes());
    }

    #[Test]
    public function duplicate_node_ids_are_rejected(): void
    {
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [
                new PlanNode('n1', 'fake-input', ['seed' => 'hi'], 'seed'),
                new PlanNode('n1', 'fake-writer', ['mode' => 'a'], 'dup'),
            ],
            edges: [],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        $this->assertFalse($result->valid);
        $this->assertContains(WorkflowPlanValidator::CODE_DUPLICATE_NODE_ID, $result->errorCodes());
        $this->assertSame('nodes[1].id', $result->errors[0]['path']);
    }

    #[Test]
    public function self_loop_edge_is_rejected(): void
    {
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [new PlanNode('n1', 'fake-writer', ['mode' => 'a'], 'seed')],
            edges: [new PlanEdge('n1', 'out', 'n1', 'in', 'loop')],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        $this->assertFalse($result->valid);
        $this->assertContains(WorkflowPlanValidator::CODE_EDGE_SELF_LOOP, $result->errorCodes());
    }

    #[Test]
    public function cycle_between_two_nodes_is_detected(): void
    {
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [
                new PlanNode('n1', 'fake-writer', ['mode' => 'a'], 'a'),
                new PlanNode('n2', 'fake-splitter', ['mode' => 'a'], 'b'),
            ],
            edges: [
                new PlanEdge('n1', 'out', 'n2', 'in', 'fwd'),
                new PlanEdge('n2', 'out', 'n1', 'in', 'back'),
            ],
            assumptions: [], rationale: 'r',
        );
        // types: writer:in=Prompt/out=Script, splitter:in=Script/out=SceneList —
        // the back-edge is type-incompatible too; but cycle detection should fire.
        $result = $this->validator->validate($plan);
        $this->assertFalse($result->valid);
        $this->assertContains(WorkflowPlanValidator::CODE_CYCLE_DETECTED, $result->errorCodes());
    }

    #[Test]
    public function edge_referencing_unknown_node_is_rejected(): void
    {
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [new PlanNode('n1', 'fake-input', ['seed' => 'hi'], 's')],
            edges: [new PlanEdge('n1', 'out', 'ghost', 'in', 'bad')],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        $this->assertFalse($result->valid);
        $this->assertContains(WorkflowPlanValidator::CODE_EDGE_UNKNOWN_NODE, $result->errorCodes());
    }

    // ── Node type existence ───────────────────────────────────────────────

    #[Test]
    public function unknown_node_type_is_rejected(): void
    {
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [new PlanNode('n1', 'nonexistent', [], 'why')],
            edges: [],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        $this->assertFalse($result->valid);
        $this->assertContains(WorkflowPlanValidator::CODE_UNKNOWN_NODE_TYPE, $result->errorCodes());
        $this->assertSame('nodes[0].type', $result->errors[0]['path']);
    }

    // ── Port shape ────────────────────────────────────────────────────────

    #[Test]
    public function edge_with_unknown_port_is_rejected(): void
    {
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [
                new PlanNode('n1', 'fake-input', ['seed' => 'hi'], 'a'),
                new PlanNode('n2', 'fake-writer', ['mode' => 'a'], 'b'),
            ],
            edges: [new PlanEdge('n1', 'out', 'n2', 'ghostPort', 'bad')],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        $this->assertFalse($result->valid);
        $this->assertContains(WorkflowPlanValidator::CODE_EDGE_UNKNOWN_PORT, $result->errorCodes());
    }

    // ── Type compatibility + coercion ─────────────────────────────────────

    #[Test]
    public function incompatible_port_types_are_rejected(): void
    {
        // splitter(out=SceneList) → writer(in=Prompt) — incompatible
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [
                new PlanNode('n1', 'fake-splitter', ['mode' => 'a'], 'a'),
                new PlanNode('n2', 'fake-writer', ['mode' => 'a'], 'b'),
            ],
            edges: [new PlanEdge('n1', 'out', 'n2', 'in', 'bad')],
            assumptions: [], rationale: 'r',
        );
        // n1 has a required input — add a fake-input feeding n1
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [
                new PlanNode('src', 'fake-input', ['seed' => 'x'], 'seed'),
                new PlanNode('n1', 'fake-splitter', ['mode' => 'a'], 'a'),
                new PlanNode('n2', 'fake-writer', ['mode' => 'a'], 'b'),
            ],
            edges: [
                // seed → splitter (incompatible: Text → Script)
                // but we want ONLY the splitter→writer incompat to show.
                // Actually use text-coercion path: seed(out=Prompt) → writer(in=Prompt) OK
                new PlanEdge('src', 'out', 'n1', 'in', 'x'),
                new PlanEdge('n1', 'out', 'n2', 'in', 'bad'),
            ],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        // splitter needs Script on input; src outputs Prompt — that's also incompatible.
        // Both will produce CODE_TYPE_INCOMPATIBLE entries; assert code present.
        $this->assertFalse($result->valid);
        $this->assertContains(WorkflowPlanValidator::CODE_TYPE_INCOMPATIBLE, $result->errorCodes());
    }

    #[Test]
    public function scalar_to_list_coercion_is_a_warning_not_error(): void
    {
        // fake-input(out=Text) → fake-list-consumer(in=TextList)
        $this->registry->register(new FakeListConsumerTemplate());
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [
                new PlanNode('src', 'fake-text-emitter', ['seed' => 'hi'], 'seed'),
                new PlanNode('dst', 'fake-list-consumer', [], 'consume'),
            ],
            edges: [new PlanEdge('src', 'out', 'dst', 'in', 'coerce')],
            assumptions: [], rationale: 'r',
        );
        $this->registry->register(new FakeTextEmitterTemplate());

        $result = $this->validator->validate($plan);
        $this->assertTrue($result->valid, 'Coercion should be a warning, not an error. Errors: ' . json_encode($result->errors));
        $codes = array_column($result->warnings, 'code');
        $this->assertContains(WorkflowPlanValidator::CODE_TYPE_COERCION_WARNING, $codes);
    }

    // ── Required inputs ───────────────────────────────────────────────────

    #[Test]
    public function missing_required_input_is_rejected(): void
    {
        // writer requires "in" — no edge, no config default
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [new PlanNode('n1', 'fake-writer', ['mode' => 'a'], 'solo')],
            edges: [],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        $this->assertFalse($result->valid);
        $this->assertContains(WorkflowPlanValidator::CODE_REQUIRED_INPUT_MISSING, $result->errorCodes());
    }

    #[Test]
    public function required_input_satisfied_by_config_default_is_ok(): void
    {
        // Supply the input via config.inputs.<key>
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [
                new PlanNode('n1', 'fake-writer', [
                    'mode' => 'a',
                    'inputs' => ['in' => 'Default prompt body'],
                ], 'solo with default'),
            ],
            edges: [],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        $this->assertTrue($result->valid, 'Config-supplied input should satisfy required port. Errors: ' . json_encode($result->errors));
    }

    // ── Config validation per node ────────────────────────────────────────

    #[Test]
    public function invalid_config_produces_actionable_error(): void
    {
        // fake-writer requires mode in: a,b,c
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [
                new PlanNode('src', 'fake-input', ['seed' => 'x'], 'seed'),
                new PlanNode('n1', 'fake-writer', ['mode' => 'zzz'], 'bad'),
            ],
            edges: [new PlanEdge('src', 'out', 'n1', 'in', 'wire')],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        $this->assertFalse($result->valid);

        $configErrors = array_values(array_filter(
            $result->errors,
            fn (array $e) => $e['code'] === WorkflowPlanValidator::CODE_CONFIG_INVALID,
        ));
        $this->assertNotEmpty($configErrors);
        $err = $configErrors[0];
        $this->assertSame('nodes[1].config.mode', $err['path']);
        $this->assertNotEmpty($err['message']);
        $this->assertSame('n1', $err['context']['nodeId']);
        $this->assertSame('mode', $err['context']['field']);
    }

    #[Test]
    public function missing_reason_is_a_warning_not_error(): void
    {
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [
                new PlanNode('n1', 'fake-input', ['seed' => 'x'], ''), // empty reason
            ],
            edges: [],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        $this->assertTrue($result->valid);
        $codes = array_column($result->warnings, 'code');
        $this->assertContains(WorkflowPlanValidator::CODE_MISSING_REASON, $codes);
    }

    #[Test]
    public function orphan_node_is_a_warning(): void
    {
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [
                new PlanNode('src', 'fake-input', ['seed' => 'x'], 'seed'),
                new PlanNode('n1', 'fake-writer', ['mode' => 'a', 'inputs' => ['in' => 'x']], 'solo'),
            ],
            edges: [],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        $codes = array_column($result->warnings, 'code');
        $this->assertContains(WorkflowPlanValidator::CODE_ORPHAN_NODE, $codes);
    }

    #[Test]
    public function duplicate_edge_is_rejected(): void
    {
        $plan = new WorkflowPlan(
            intent: 'x', vibeMode: 'v',
            nodes: [
                new PlanNode('src', 'fake-input', ['seed' => 'x'], 'seed'),
                new PlanNode('n1', 'fake-writer', ['mode' => 'a'], 'a'),
            ],
            edges: [
                new PlanEdge('src', 'out', 'n1', 'in', 'wire'),
                new PlanEdge('src', 'out', 'n1', 'in', 'dup'),
            ],
            assumptions: [], rationale: 'r',
        );
        $result = $this->validator->validate($plan);
        $this->assertFalse($result->valid);
        $this->assertContains(WorkflowPlanValidator::CODE_DUPLICATE_EDGE, $result->errorCodes());
    }

    #[Test]
    public function config_schema_for_returns_json_schema_for_known_type(): void
    {
        $schema = $this->validator->configSchemaFor('fake-writer');
        $this->assertIsArray($schema);
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('mode', $schema['properties']);
        $this->assertSame(['a', 'b', 'c'], $schema['properties']['mode']['enum']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function validThreeNodePlan(): WorkflowPlan
    {
        return new WorkflowPlan(
            intent: 'Funny genz storytelling',
            vibeMode: 'funny_storytelling',
            nodes: [
                new PlanNode('src', 'fake-input', ['seed' => 'A product demo'], 'seed prompt'),
                new PlanNode('writer', 'fake-writer', ['mode' => 'a'], 'write script'),
                new PlanNode('split', 'fake-splitter', ['mode' => 'b'], 'split to scenes'),
            ],
            edges: [
                new PlanEdge('src', 'out', 'writer', 'in', 'prompt → writer'),
                new PlanEdge('writer', 'out', 'split', 'in', 'script → splitter'),
            ],
            assumptions: ['platform=tiktok'],
            rationale: 'Three-stage linear plan',
            meta: ['plannerVersion' => 'v0.1.0'],
        );
    }
}

// ── Test-only NodeTemplates (anonymous classes don't work with `public $type = ...` hooks neatly; use small named subclasses) ──

final class FakeInputTemplate extends NodeTemplate
{
    public string $type { get => 'fake-input'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Fake Input'; }
    public NodeCategory $category { get => NodeCategory::Input; }
    public string $description { get => 'Test input node'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [],
            outputs: [PortDefinition::output('out', 'Out', DataType::Prompt)],
        );
    }

    public function configRules(): array
    {
        return ['seed' => ['required', 'string', 'min:1']];
    }

    public function defaultConfig(): array { return ['seed' => '']; }
    public function execute(NodeExecutionContext $ctx): array { return []; }
}

final class FakeWriterTemplate extends NodeTemplate
{
    public string $type { get => 'fake-writer'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Fake Writer'; }
    public NodeCategory $category { get => NodeCategory::Script; }
    public string $description { get => 'Test writer node'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [PortDefinition::input('in', 'In', DataType::Prompt, required: true)],
            outputs: [PortDefinition::output('out', 'Out', DataType::Script)],
        );
    }

    public function configRules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:a,b,c'],
        ];
    }

    public function defaultConfig(): array { return ['mode' => 'a']; }
    public function execute(NodeExecutionContext $ctx): array { return []; }
}

final class FakeSplitterTemplate extends NodeTemplate
{
    public string $type { get => 'fake-splitter'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Fake Splitter'; }
    public NodeCategory $category { get => NodeCategory::Script; }
    public string $description { get => 'Test splitter node'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [PortDefinition::input('in', 'In', DataType::Script, required: true)],
            outputs: [PortDefinition::output('out', 'Out', DataType::SceneList)],
        );
    }

    public function configRules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:a,b,c'],
        ];
    }

    public function defaultConfig(): array { return ['mode' => 'a']; }
    public function execute(NodeExecutionContext $ctx): array { return []; }
}

final class FakeTextEmitterTemplate extends NodeTemplate
{
    public string $type { get => 'fake-text-emitter'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Fake Text Emitter'; }
    public NodeCategory $category { get => NodeCategory::Input; }
    public string $description { get => 'Emits Text data type'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [],
            outputs: [PortDefinition::output('out', 'Out', DataType::Text)],
        );
    }

    public function configRules(): array
    {
        return ['seed' => ['required', 'string']];
    }

    public function defaultConfig(): array { return ['seed' => '']; }
    public function execute(NodeExecutionContext $ctx): array { return []; }
}

final class FakeListConsumerTemplate extends NodeTemplate
{
    public string $type { get => 'fake-list-consumer'; }
    public string $version { get => '1.0.0'; }
    public string $title { get => 'Fake List Consumer'; }
    public NodeCategory $category { get => NodeCategory::Utility; }
    public string $description { get => 'Takes a TextList'; }

    public function ports(): PortSchema
    {
        return new PortSchema(
            inputs: [PortDefinition::input('in', 'In', DataType::TextList, required: true)],
            outputs: [PortDefinition::output('out', 'Out', DataType::Json)],
        );
    }

    public function configRules(): array { return []; }
    public function defaultConfig(): array { return []; }
    public function execute(NodeExecutionContext $ctx): array { return []; }
}
