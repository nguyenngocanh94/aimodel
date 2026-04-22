<?php

declare(strict_types=1);

namespace App\Services\Skills\MetaTools;

use App\Services\Skills\SkillRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class SkillTool implements Tool
{
    public function __construct(
        private readonly SkillRegistry $registry,
    ) {}

    public function description(): string
    {
        return 'Get the full instructions and tool list for a specific skill. Pass the skill slug as the "slug" argument.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string('The skill slug to look up.'),
        ];
    }

    public function handle(Request $request): string
    {
        $slug = $request->string('slug');
        if ($slug === '') {
            return json_encode(['error' => 'slug is required'], JSON_UNESCAPED_UNICODE);
        }

        $skill = $this->registry->get($slug);
        if ($skill === null) {
            return json_encode(['error' => "Skill '{$slug}' not found"], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'slug' => $slug,
            'name' => $skill->name,
            'description' => $skill->description,
            'instructions' => $skill->body,
            'tools' => $skill->toolClasses,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
