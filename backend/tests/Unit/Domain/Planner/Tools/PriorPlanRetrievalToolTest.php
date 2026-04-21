<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Planner\Tools;

use App\Domain\Planner\Tools\PriorPlanRetrievalTool;
use App\Models\PastPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PriorPlanRetrievalToolTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function empty_brief_returns_no_priors(): void
    {
        $tool = $this->app->make(PriorPlanRetrievalTool::class);
        $result = json_decode($tool->handle(new Request(['brief' => ''])), true);

        $this->assertSame([], $result['priors']);
        $this->assertSame('empty brief', $result['note']);
    }

    #[Test]
    public function returns_matching_priors_via_ilike(): void
    {
        PastPlan::create([
            'brief' => 'A 30 second chocopie soft-sell TVC',
            'brief_hash' => PastPlan::hashBrief('A 30 second chocopie soft-sell TVC'),
            'plan' => ['nodes' => [], 'edges' => []],
            'provider' => 'fireworks',
            'model' => 'gpt-oss-120b',
        ]);
        PastPlan::create([
            'brief' => 'Completely different skincare product pitch',
            'brief_hash' => PastPlan::hashBrief('Completely different skincare product pitch'),
            'plan' => ['nodes' => [], 'edges' => []],
            'provider' => null,
            'model' => null,
        ]);

        $tool = $this->app->make(PriorPlanRetrievalTool::class);
        $result = json_decode(
            $tool->handle(new Request(['brief' => 'A 30 second chocopie soft-sell TVC'])),
            true,
        );

        $this->assertCount(1, $result['priors']);
        $this->assertStringContainsString('chocopie', $result['priors'][0]['brief']);
    }

    #[Test]
    public function caps_at_three_priors(): void
    {
        for ($i = 0; $i < 5; $i++) {
            PastPlan::create([
                'brief' => "A 30 second chocopie soft-sell TVC variant {$i}",
                'brief_hash' => PastPlan::hashBrief("chocopie-{$i}"),
                'plan' => ['nodes' => [], 'edges' => []],
            ]);
        }

        $tool = $this->app->make(PriorPlanRetrievalTool::class);
        $result = json_decode(
            $tool->handle(new Request(['brief' => 'A 30 second chocopie soft-sell TVC'])),
            true,
        );

        $this->assertCount(3, $result['priors']);
    }

    #[Test]
    public function tool_description_and_schema_are_well_formed(): void
    {
        $tool = $this->app->make(PriorPlanRetrievalTool::class);
        $this->assertNotEmpty((string) $tool->description());

        $schema = $tool->schema(new JsonSchemaTypeFactory());
        $this->assertArrayHasKey('brief', $schema);
    }
}
