<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Planner;

use App\Domain\Execution\WorkflowValidator;
use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Planner\PlanEdge;
use App\Domain\Planner\PlanNode;
use App\Domain\Planner\WorkflowPlan;
use App\Domain\Planner\WorkflowPlanToDocumentConverter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ensures a validated WorkflowPlan converts to a WorkflowDocument array shape
 * that passes the existing backend WorkflowValidator (proving interop with the
 * runtime). Uses the same FakeInput/FakeWriter/FakeSplitter templates defined
 * for WorkflowPlanValidatorTest — PSR-4 autoload picks them up via the test
 * namespace (Tests\Unit\Domain\Planner).
 */
final class WorkflowPlanToDocumentConverterTest extends TestCase
{
    private NodeTemplateRegistry $registry;
    private WorkflowPlanToDocumentConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new NodeTemplateRegistry();
        $this->registry->register(new FakeInputTemplate());
        $this->registry->register(new FakeWriterTemplate());
        $this->registry->register(new FakeSplitterTemplate());

        $this->converter = new WorkflowPlanToDocumentConverter($this->registry);
    }

    #[Test]
    public function converted_document_matches_expected_top_level_shape(): void
    {
        $plan = $this->samplePlan();
        $doc = $this->converter->convert($plan, workflowId: 'wf-123', name: 'Test WF');

        $this->assertSame('wf-123', $doc['id']);
        $this->assertSame('Test WF', $doc['name']);
        $this->assertSame(1, $doc['schemaVersion']);
        $this->assertSame(['funny_storytelling'], $doc['tags']);
        $this->assertCount(3, $doc['nodes']);
        $this->assertCount(2, $doc['edges']);

        // Frontend-canonical edge keys.
        $firstEdge = $doc['edges'][0];
        $this->assertArrayHasKey('sourceNodeId', $firstEdge);
        $this->assertArrayHasKey('sourcePortKey', $firstEdge);
        $this->assertArrayHasKey('targetNodeId', $firstEdge);
        $this->assertArrayHasKey('targetPortKey', $firstEdge);
        // Backend aliases for WorkflowValidator/RunExecutor.
        $this->assertArrayHasKey('source', $firstEdge);
        $this->assertArrayHasKey('target', $firstEdge);
        $this->assertArrayHasKey('sourceHandle', $firstEdge);
        $this->assertArrayHasKey('targetHandle', $firstEdge);

        // Node carries planner reason as notes.
        $this->assertSame('seed prompt', $doc['nodes'][0]['notes']);

        // Meta preserves planner context for drift-eval.
        $this->assertSame('workflow-designer', $doc['meta']['source']);
        $this->assertSame('funny_storytelling', $doc['meta']['vibeMode']);
        $this->assertSame(['platform=tiktok'], $doc['meta']['assumptions']);
    }

    #[Test]
    public function converted_document_passes_existing_workflow_validator(): void
    {
        $plan = $this->samplePlan();
        $doc = $this->converter->convert($plan);

        $validator = new WorkflowValidator();
        $issues = $validator->validate($doc, $this->registry);

        $errors = array_filter($issues, fn (array $i) => ($i['severity'] ?? '') === 'error');
        $this->assertSame(
            [],
            array_values($errors),
            'Converted document should pass WorkflowValidator with no errors. Issues: ' . json_encode($issues),
        );
    }

    #[Test]
    public function generated_id_is_uuid_when_not_provided(): void
    {
        $doc = $this->converter->convert($this->samplePlan());
        $this->assertIsString($doc['id']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $doc['id'],
        );
    }

    private function samplePlan(): WorkflowPlan
    {
        return new WorkflowPlan(
            intent: 'Funny genz storytelling for TikTok Vietnam',
            vibeMode: 'funny_storytelling',
            nodes: [
                new PlanNode('src', 'fake-input', ['seed' => 'A product demo'], 'seed prompt'),
                new PlanNode('writer', 'fake-writer', ['mode' => 'a'], 'need script'),
                new PlanNode('split', 'fake-splitter', ['mode' => 'b'], 'split scenes'),
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
