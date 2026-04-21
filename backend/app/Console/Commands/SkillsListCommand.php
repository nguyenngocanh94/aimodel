<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Skills\SkillRegistry;
use Illuminate\Console\Command;

final class SkillsListCommand extends Command
{
    protected $signature = 'skills:list';

    protected $description = 'List all discovered skills from resources/skills/';

    public function handle(SkillRegistry $registry): int
    {
        $skills = $registry->all();

        if ($skills === []) {
            $this->info('No skills discovered yet. Create SKILL.md files in resources/skills/{slug}/.');
            $this->table(
                ['Slug', 'Name', 'Mode', 'Tools'],
                [['—', '—', '—', '—']],
            );

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($skills as $slug => $skill) {
            $rows[] = [
                $slug,
                $skill->name,
                $skill->mode->value,
                count($skill->toolClasses) > 0 ? implode(', ', $skill->toolClasses) : '—',
            ];
        }

        $this->table(['Slug', 'Name', 'Mode', 'Tools'], $rows);

        $this->info(sprintf('%d skill(s) found.', count($skills)));

        return self::SUCCESS;
    }
}
