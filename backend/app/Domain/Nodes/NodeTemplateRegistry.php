<?php

declare(strict_types=1);

namespace App\Domain\Nodes;

class NodeTemplateRegistry
{
    /** @var array<string, NodeTemplate> */
    private array $templates = [];

    public function register(NodeTemplate $template): void
    {
        $this->templates[$template->type] = $template;
    }

    public function get(string $type): ?NodeTemplate
    {
        return $this->templates[$type] ?? null;
    }

    /**
     * @return array<string, NodeTemplate>
     */
    public function all(): array
    {
        return $this->templates;
    }

    /**
     * @return array<int, array{type: string, version: string, title: string, category: string, description: string, inputs: array, outputs: array}>
     */
    public function metadata(): array
    {
        return array_values(array_map(function (NodeTemplate $t): array {
            $ports = $t->ports();

            return [
                'type' => $t->type,
                'version' => $t->version,
                'title' => $t->title,
                'category' => $t->category->value,
                'description' => $t->description,
                'inputs' => array_map(fn ($p) => $p->toArray(), $ports->inputs),
                'outputs' => array_map(fn ($p) => $p->toArray(), $ports->outputs),
            ];
        }, $this->templates));
    }
}
