<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

/**
 * Builds a per-template manifest array (and the full registry manifest)
 * that is suitable for JSON-encoding and serving to the frontend.
 */
final class NodeManifestBuilder
{
    public function __construct(private ConfigSchemaTranspiler $transpiler) {}

    /**
     * Build a single template's manifest.
     *
     * @return array<string, mixed>
     */
    public function build(NodeTemplate $template): array
    {
        $ports = $template->ports();

        $inputs = array_map(
            fn ($p) => $p->toArray(),
            $ports->inputs,
        );
        $outputs = array_map(
            fn ($p) => $p->toArray(),
            $ports->outputs,
        );

        $configRules = $template->configRules();
        $defaultConfig = $template->defaultConfig();

        $configSchema = $this->transpiler->transpile($configRules, $defaultConfig);

        return [
            'type' => $template->type,
            'version' => $template->version,
            'title' => $template->title,
            'description' => $template->description,
            'category' => $template->category->value,
            'ports' => [
                'inputs' => array_values($inputs),
                'outputs' => array_values($outputs),
            ],
            'configSchema' => $configSchema,
            'defaultConfig' => $defaultConfig,
            'humanGateEnabled' => method_exists($template, 'humanGateDefaultConfig'),
            'executable' => true,
        ];
    }

    /**
     * Build the full registry manifest.
     *
     * @return array{version: string, nodes: array<string, array<string, mixed>>}
     */
    public function buildAll(NodeTemplateRegistry $registry): array
    {
        $manifests = [];
        $versionParts = [];

        foreach ($registry->all() as $type => $template) {
            $manifests[$type] = $this->build($template);
            $versionParts[] = $type . '@' . $template->version;
        }

        sort($versionParts); // stable regardless of registration order
        $hash = hash('sha256', implode(',', $versionParts));

        return [
            'version' => $hash,
            'nodes' => $manifests,
        ];
    }
}
