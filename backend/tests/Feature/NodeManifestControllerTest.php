<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NodeManifestControllerTest extends TestCase
{
    // ── Happy-path ────────────────────────────────────────────────────────

    #[Test]
    public function endpoint_returns_200_with_cache_control_header(): void
    {
        $response = $this->getJson('/api/nodes/manifest');

        $response->assertStatus(200);

        $cacheControl = $response->headers->get('Cache-Control') ?? '';
        // Header may be "public, max-age=300" or "max-age=300, public" depending on Symfony version
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
    }

    #[Test]
    public function response_has_version_and_nodes_keys(): void
    {
        $response = $this->getJson('/api/nodes/manifest');

        $response->assertJsonStructure(['version', 'nodes']);
        $this->assertNotEmpty($response->json('version'));
        $this->assertIsArray($response->json('nodes'));
    }

    // ── storyWriter manifest shape ─────────────────────────────────────────

    #[Test]
    public function story_writer_manifest_has_full_shape(): void
    {
        $response = $this->getJson('/api/nodes/manifest');
        $response->assertStatus(200);

        $sw = $response->json('nodes.storyWriter');

        $this->assertNotNull($sw, 'storyWriter node must exist in manifest');
        $this->assertSame('storyWriter', $sw['type']);
        $this->assertNotEmpty($sw['version']);
        $this->assertNotEmpty($sw['title']);
        $this->assertNotEmpty($sw['description']);
        $this->assertNotEmpty($sw['category']);

        // ports
        $this->assertArrayHasKey('ports', $sw);
        $this->assertArrayHasKey('inputs', $sw['ports']);
        $this->assertArrayHasKey('outputs', $sw['ports']);
        $this->assertNotEmpty($sw['ports']['inputs']);
        $this->assertNotEmpty($sw['ports']['outputs']);

        // configSchema
        $this->assertArrayHasKey('configSchema', $sw);
        $this->assertSame('object', $sw['configSchema']['type']);
        $this->assertArrayHasKey('properties', $sw['configSchema']);

        // defaultConfig
        $this->assertArrayHasKey('defaultConfig', $sw);
        $this->assertIsArray($sw['defaultConfig']);
    }

    #[Test]
    public function story_writer_human_gate_enabled_is_true(): void
    {
        $response = $this->getJson('/api/nodes/manifest');

        $this->assertTrue($response->json('nodes.storyWriter.humanGateEnabled'));
    }

    // ── humanGate nested schema ────────────────────────────────────────────

    #[Test]
    public function story_writer_config_schema_has_nested_human_gate_object(): void
    {
        $response = $this->getJson('/api/nodes/manifest');

        $hg = $response->json('nodes.storyWriter.configSchema.properties.humanGate');

        $this->assertNotNull($hg, 'humanGate property must exist in configSchema');
        $this->assertSame('object', $hg['type']);
        $this->assertArrayHasKey('properties', $hg);
    }

    #[Test]
    public function human_gate_enabled_child_is_boolean_with_false_default(): void
    {
        $response = $this->getJson('/api/nodes/manifest');

        $enabled = $response->json('nodes.storyWriter.configSchema.properties.humanGate.properties.enabled');

        $this->assertNotNull($enabled);
        $this->assertSame('boolean', $enabled['type']);
        $this->assertFalse($enabled['default']);
    }

    // ── humanGate template itself ──────────────────────────────────────────

    #[Test]
    public function human_gate_node_exists_in_manifest(): void
    {
        $response = $this->getJson('/api/nodes/manifest');

        $hg = $response->json('nodes.humanGate');
        $this->assertNotNull($hg, 'humanGate node must be registered in manifest');
        $this->assertSame('humanGate', $hg['type']);
    }

    // ── userPrompt has humanGateEnabled = false ────────────────────────────

    #[Test]
    public function user_prompt_human_gate_enabled_is_false(): void
    {
        $response = $this->getJson('/api/nodes/manifest');

        $this->assertFalse($response->json('nodes.userPrompt.humanGateEnabled'));
    }

    // ── Stable version hash ────────────────────────────────────────────────

    #[Test]
    public function two_calls_return_same_version_hash(): void
    {
        $first = $this->getJson('/api/nodes/manifest')->json('version');
        $second = $this->getJson('/api/nodes/manifest')->json('version');

        $this->assertSame($first, $second);
    }

    // ── All registered templates appear in manifest ────────────────────────

    #[Test]
    public function all_registered_template_types_appear_in_manifest(): void
    {
        $response = $this->getJson('/api/nodes/manifest');
        $nodes = $response->json('nodes');

        $expectedTypes = [
            'userPrompt',
            'scriptWriter',
            'sceneSplitter',
            'promptRefiner',
            'imageGenerator',
            'reviewCheckpoint',
            'humanGate',
            'imageAssetMapper',
            'ttsVoiceoverPlanner',
            'subtitleFormatter',
            'videoComposer',
            'finalExport',
            'wanR2V',
            'trendResearcher',
            'productAnalyzer',
            'storyWriter',
            'telegramTrigger',
            'telegramDeliver',
        ];

        foreach ($expectedTypes as $type) {
            $this->assertArrayHasKey($type, $nodes, "Expected template type '{$type}' in manifest");
        }
    }

    // ── Each node has required fields ──────────────────────────────────────

    #[Test]
    public function every_manifest_node_has_required_top_level_fields(): void
    {
        $response = $this->getJson('/api/nodes/manifest');
        $nodes = $response->json('nodes');

        $required = ['type', 'version', 'title', 'description', 'category', 'ports', 'configSchema', 'defaultConfig', 'humanGateEnabled', 'executable'];

        foreach ($nodes as $type => $node) {
            foreach ($required as $field) {
                $this->assertArrayHasKey($field, $node, "Node '{$type}' missing field '{$field}'");
            }
        }
    }

    // ── Port structure validation ──────────────────────────────────────────

    #[Test]
    public function story_writer_input_ports_have_required_fields(): void
    {
        $response = $this->getJson('/api/nodes/manifest');
        $inputs = $response->json('nodes.storyWriter.ports.inputs');

        $this->assertNotEmpty($inputs);

        foreach ($inputs as $port) {
            $this->assertArrayHasKey('key', $port);
            $this->assertArrayHasKey('label', $port);
            $this->assertArrayHasKey('dataType', $port);
            $this->assertArrayHasKey('direction', $port);
            $this->assertArrayHasKey('required', $port);
            $this->assertArrayHasKey('multiple', $port);
        }
    }

    // ── configSchema root shape ────────────────────────────────────────────

    #[Test]
    public function config_schema_has_draft07_envelope(): void
    {
        $response = $this->getJson('/api/nodes/manifest');
        $schema = $response->json('nodes.storyWriter.configSchema');

        $this->assertSame('http://json-schema.org/draft-07/schema#', $schema['$schema']);
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('required', $schema);
        $this->assertArrayHasKey('additionalProperties', $schema);
        $this->assertFalse($schema['additionalProperties']);
    }
}
