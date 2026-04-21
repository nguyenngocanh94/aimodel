<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class SkillsMakeCommand extends Command
{
    protected $signature = 'skills:make {slug : The skill slug (e.g. list-workflows)}';

    protected $description = 'Scaffold a new skill directory and SKILL.md file in resources/skills/';

    public function handle(): int
    {
        $slug = $this->argument('slug');

        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            $this->error('Slug must contain only lowercase letters, numbers, and hyphens.');

            return self::FAILURE;
        }

        $basePath = resource_path("skills/{$slug}");
        if (is_dir($basePath)) {
            $this->error("Skill '{$slug}' already exists at resources/skills/{$slug}/.");

            return self::FAILURE;
        }

        mkdir($basePath, 0755, true);

        $skeleton = <<<MD
---
name: {$slug}
description: Describe what this skill does here.
mode: lite
tools: []
---

# {$slug}

Write the full skill instructions here. These are shown to the agent when the skill is loaded in Full mode.
MD;

        file_put_contents("{$basePath}/SKILL.md", $skeleton);

        $this->info("Skill '{$slug}' created at resources/skills/{$slug}/SKILL.md");

        return self::SUCCESS;
    }
}
