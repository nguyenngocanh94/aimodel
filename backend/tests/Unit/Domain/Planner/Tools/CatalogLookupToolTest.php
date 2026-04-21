<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Planner\Tools;

use App\Domain\Nodes\NodeTemplateRegistry;
use App\Domain\Planner\Tools\CatalogLookupTool;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CatalogLookupToolTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function empty_query_returns_no_matches(): void
    {
        $tool = $this->app->make(CatalogLookupTool::class);

        $result = json_decode($tool->handle(new Request(['query' => ''])), true);

        $this->assertSame([], $result['matches']);
        $this->assertSame('empty query', $result['note']);
    }

    #[Test]
    public function query_matches_workflow_by_name(): void
    {
        Workflow::create([
            'name' => 'Hero Product Commercial',
            'description' => 'Cinematic 30s commercial with voiceover.',
            'tags' => ['tvc'],
            'document' => ['nodes' => [], 'edges' => []],
        ]);

        $tool = $this->app->make(CatalogLookupTool::class);

        $result = json_decode(
            $tool->handle(new Request(['query' => 'Commercial', 'kind' => 'workflow'])),
            true,
        );

        $this->assertNotEmpty($result['matches']);
        $this->assertSame('workflow', $result['matches'][0]['kind']);
        $this->assertSame('Hero Product Commercial', $result['matches'][0]['name']);
        $this->assertStringContainsString('matched', $result['matches'][0]['why']);
    }

    #[Test]
    public function query_matches_node_by_purpose(): void
    {
        $tool = $this->app->make(CatalogLookupTool::class);

        // Pick a word likely to appear in at least one node's purpose — many
        // node guides reference "video" or "input" — use a broad term.
        $registry = $this->app->make(NodeTemplateRegistry::class);
        $guides = $registry->guides();
        $this->assertNotEmpty($guides, 'Expected node templates to be registered');
        $first = array_values($guides)[0];

        // Use a distinctive token from that guide's nodeId to guarantee a hit.
        $token = $first->nodeId;

        $result = json_decode(
            $tool->handle(new Request(['query' => $token, 'kind' => 'node'])),
            true,
        );

        $this->assertNotEmpty($result['matches']);
        $this->assertSame('node', $result['matches'][0]['kind']);
        $this->assertSame($first->nodeId, $result['matches'][0]['id']);
    }

    #[Test]
    public function unmatched_query_returns_empty_matches(): void
    {
        $tool = $this->app->make(CatalogLookupTool::class);

        $result = json_decode(
            $tool->handle(new Request(['query' => 'zzzzzzzzunlikelystringqqqq'])),
            true,
        );

        $this->assertSame([], $result['matches']);
    }

    #[Test]
    public function limit_is_capped_at_20(): void
    {
        $tool = $this->app->make(CatalogLookupTool::class);
        $result = json_decode(
            $tool->handle(new Request(['query' => 'x', 'limit' => 999])),
            true,
        );

        $this->assertLessThanOrEqual(20, count($result['matches']));
    }

    #[Test]
    public function tool_description_and_schema_are_well_formed(): void
    {
        $tool = $this->app->make(CatalogLookupTool::class);
        $desc = $tool->description();
        $this->assertNotEmpty((string) $desc);

        $schema = $tool->schema(new JsonSchemaTypeFactory());
        $this->assertArrayHasKey('query', $schema);
    }
}
