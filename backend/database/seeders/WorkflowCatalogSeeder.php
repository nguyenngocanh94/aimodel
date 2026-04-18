<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Workflow;
use Illuminate\Database\Seeder;

class WorkflowCatalogSeeder extends Seeder
{
    /**
     * Catalog metadata for demo workflows.
     *
     * Keyed by workflow name. Each entry contains only the catalog fields
     * (slug, triggerable, nl_description, param_schema). Row creation is
     * owned by DemoWorkflowSeeder and HumanGateDemoSeeder; this seeder
     * only backfills metadata onto existing rows.
     */
    private const CATALOG = [
        'StoryWriter (per-node gate) – Telegram' => [
            'slug'           => 'story-writer-gated',
            'triggerable'    => true,
            'nl_description' => 'Viết kịch bản video TVC ngắn tiếng Việt (GenZ). Dùng khi người dùng yêu cầu tạo kịch bản / ý tưởng video / story cho một sản phẩm.',
            'param_schema'   => ['productBrief' => ['required', 'string', 'min:5']],
        ],
        'HumanGate Demo – UI' => [
            'slug'           => null,
            'triggerable'    => false,
            'nl_description' => null,
            'param_schema'   => null,
        ],
        'HumanGate Demo – Telegram' => [
            'slug'           => null,
            'triggerable'    => false,
            'nl_description' => null,
            'param_schema'   => null,
        ],
        'M1 Demo – AI Video Pipeline' => [
            'slug'           => 'tvc-pipeline',
            'triggerable'    => true,
            'nl_description' => 'Pipeline đầy đủ: prompt → script → scenes → refined prompts → images → review checkpoint.',
            'param_schema'   => ['prompt' => ['required', 'string', 'min:10']],
        ],
    ];

    /**
     * Backfill catalog metadata onto existing workflow rows.
     *
     * Row creation is NOT performed here; if a named workflow does not yet
     * exist in the database a warning is printed and the loop continues.
     */
    public function run(): void
    {
        $updated = 0;
        $missing = 0;

        foreach (self::CATALOG as $name => $meta) {
            $workflow = Workflow::where('name', $name)->first();

            if ($workflow === null) {
                $this->command->warn("WorkflowCatalogSeeder: workflow not found — \"{$name}\" (skipped)");
                $missing++;
                continue;
            }

            $workflow->update($meta);
            $updated++;
        }

        $this->command->info(
            "WorkflowCatalogSeeder: {$updated} workflow(s) updated"
            . ($missing > 0 ? ", {$missing} not found (run document seeders first)" : '.')
        );
    }
}
