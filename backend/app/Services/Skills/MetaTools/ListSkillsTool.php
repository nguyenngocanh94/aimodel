<?php

declare(strict_types=1);

namespace App\Services\Skills\MetaTools;

use App\Services\Skills\SkillRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ListSkillsTool implements Tool
{
    public function __construct(
        private readonly SkillRegistry $registry,
    ) {}

    public function description(): string
    {
        return 'List all available skills with their names and descriptions.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $skills = $this->registry->all();
        $list = [];

        foreach ($skills as $slug => $skill) {
            $list[] = [
                'slug' => $slug,
                'name' => $skill->name,
                'description' => $skill->description,
                'mode' => $skill->mode->value,
                'has_tools' => count($skill->toolClasses) > 0,
            ];
        }

        return json_encode(['skills' => $list], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
