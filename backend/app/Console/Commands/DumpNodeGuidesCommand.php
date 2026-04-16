<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Nodes\NodeTemplateRegistry;
use Illuminate\Console\Command;

class DumpNodeGuidesCommand extends Command
{
    protected $signature = 'node:guides
        {--type= : Dump guide for a single node type}
        {--format=yaml : Output format: yaml or json}';

    protected $description = 'Dump planner-readable node guides';

    public function handle(NodeTemplateRegistry $registry): int
    {
        $type = $this->option('type');
        $format = $this->option('format');

        if ($type) {
            $template = $registry->get($type);
            if (!$template) {
                $this->error("Node type '{$type}' not found.");
                return self::FAILURE;
            }
            $guide = $template->plannerGuide();
            $this->output->write(
                $format === 'json'
                    ? json_encode($guide->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    : $guide->toYaml()
            );
            return self::SUCCESS;
        }

        if ($format === 'json') {
            $all = array_map(fn ($g) => $g->toArray(), $registry->guides());
            $this->output->write(json_encode(array_values($all), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->output->write($registry->guidesYaml());
        }

        return self::SUCCESS;
    }
}
