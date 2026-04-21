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
        $lines = explode("\n", $yaml);
        $parsed = [];
        $currentKey = null;
        $currentArray = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Multi-line array item: starts with "  - " or "    - "
            if (preg_match('/^(-)\s+(.+)$/', $trimmed, $m)) {
                if ($currentKey !== null) {
                    $currentArray[] = trim($m[2]);
                }
                continue;
            }

            // Flush any pending multi-line array
            if ($currentKey !== null && $currentArray !== []) {
                $parsed[$currentKey] = $currentArray;
                $currentKey = null;
                $currentArray = [];
            }

            // Key-value pair
            if (preg_match('/^(\w+):\s*(.*)$/', $trimmed, $m)) {
                $key = $m[1];
                $rawValue = trim($m[2]);

                // Flush any pending multi-line array before processing new key
                if ($currentKey !== null && $currentArray !== []) {
                    $parsed[$currentKey] = $currentArray;
                    $currentKey = null;
                    $currentArray = [];
                }

                if ($rawValue === '') {
                    // Empty value — could be followed by multi-line values
                    $currentKey = $key;
                    $currentArray = [];
                    $parsed[$key] = [];
                } elseif (str_starts_with($rawValue, '[') && str_ends_with(rtrim($rawValue, ' '), ']')) {
                    // Inline array: [Foo, Bar] or [Foo]
                    $inner = substr(rtrim($rawValue, ' '), 1, -1);
                    if ($inner === '') {
                        $parsed[$key] = [];
                    } else {
                        $items = array_filter(array_map('trim', explode(',', $inner)));
                        $parsed[$key] = $items;
                    }
                } else {
                    $parsed[$key] = trim($rawValue, '\'" ');
                }
            }
        }

        // Flush any remaining multi-line array at end of file
        if ($currentKey !== null && $currentArray !== []) {
            $parsed[$currentKey] = $currentArray;
        }

        return $parsed;
    }

    private static function stripFrontmatter(string $content): string
    {
        if (! str_starts_with(trim($content), '---')) {
            return $content;
        }

        $parts = preg_split('/^---$/m', $content, 3);
        if ($parts === false || count($parts) < 2) {
            return $content;
        }

        // parts[0] = '' (before first ---), parts[1] = yaml, parts[2] = body
        return trim($parts[2] ?? $parts[1] ?? '');
    }
}
