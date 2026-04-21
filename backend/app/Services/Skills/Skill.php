<?php

declare(strict_types=1);

namespace App\Services\Skills;

use RuntimeException;

/**
 * Represents a discovered skill from resources/skills/{slug}/SKILL.md.
 *
 * Frontmatter shape:
 * ---
 * name: list-workflows
 * description: List the catalog of triggerable workflows...
 * tools:
 *   - App\Services\TelegramAgent\Tools\ListWorkflowsTool
 * ---
 */
final class Skill
{
    /**
     * @param  list<class-string>  $toolClasses
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $description,
        public readonly string $body,
        public readonly SkillInclusionMode $mode,
        public readonly array $toolClasses,
    ) {}

    /**
     * @throws RuntimeException
     */
    public static function fromFile(string $slug, string $path): self
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Cannot read skill file: {$path}");
        }

        $frontmatter = self::parseFrontmatter($content);
        $body = self::stripFrontmatter($content);

        $toolClasses = [];
        if (isset($frontmatter['tools']) && is_array($frontmatter['tools'])) {
            foreach ($frontmatter['tools'] as $toolClass) {
                if (is_string($toolClass) && class_exists($toolClass)) {
                    $toolClasses[] = $toolClass;
                }
            }
        }

        $mode = SkillInclusionMode::Lite;
        if (isset($frontmatter['mode'])) {
            $mode = SkillInclusionMode::tryFrom($frontmatter['mode']) ?? SkillInclusionMode::Lite;
        }

        return new self(
            slug: $slug,
            name: $frontmatter['name'] ?? $slug,
            description: $frontmatter['description'] ?? '',
            body: trim($body),
            mode: $mode,
            toolClasses: $toolClasses,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseFrontmatter(string $content): array
    {
        if (! str_starts_with(trim($content), '---')) {
            return [];
        }

        $parts = preg_split('/^---$/m', $content);
        if ($parts === false || count($parts) < 2) {
            return [];
        }

        $yaml = trim($parts[1]);
        $parsed = [];
        foreach (explode("\n", $yaml) as $line) {
            if (preg_match('/^(\w+):\s*(.*)$/', $line, $m)) {
                $value = trim($m[2], '\'" ');
                if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                    $value = array_filter(array_map('trim', explode(',', substr($value, 1, -1))));
                }
                $parsed[$m[1]] = $value;
            }
        }

        return $parsed;
    }

    private static function stripFrontmatter(string $content): string
    {
        if (! str_starts_with(trim($content), '---')) {
            return $content;
        }

        $parts = preg_split('/^---$/m', $content, 2);
        if ($parts === false || count($parts) < 2) {
            return $content;
        }

        return trim($parts[2]);
    }
}
