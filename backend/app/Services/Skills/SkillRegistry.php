<?php

declare(strict_types=1);

namespace App\Services\Skills;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Discovers and caches skill definitions from resources/skills/{slug}/SKILL.md.
 */
final class SkillRegistry
{
    /** @var array<string, Skill>|null */
    private ?array $skills = null;

    public function all(): array
    {
        $this->load();

        return $this->skills;
    }

    public function get(string $slug): ?Skill
    {
        $this->load();

        return $this->skills[$slug] ?? null;
    }

    /**
     * @return list<class-string>
     */
    public function toolClassesForSkill(string $slug): array
    {
        $skill = $this->get($slug);

        return $skill?->toolClasses ?? [];
    }

    /**
     * List all skill slugs.
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        $this->load();

        return array_keys($this->skills);
    }

    /**
     * Force a reload from disk.
     */
    public function invalidate(): void
    {
        $this->skills = null;
    }

    private function load(): void
    {
        if ($this->skills !== null) {
            return;
        }

        $cacheKey = 'skills:registry';
        $cacheEnabled = config('skills.cache_enabled', false);

        if ($cacheEnabled) {
            $cached = Cache::store(config('skills.cache_store', 'file'))->get($cacheKey);
            if (is_array($cached)) {
                $this->skills = $cached;

                return;
            }
        }

        $this->skills = $this->discoverFromDisk();

        if ($cacheEnabled) {
            try {
                Cache::store(config('skills.cache_store', 'file'))
                    ->put($cacheKey, $this->skills, config('skills.cache_ttl', 3600));
            } catch (\Throwable $e) {
                Log::warning('Skills: cache write failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * @return array<string, Skill>
     */
    private function discoverFromDisk(): array
    {
        $skills = [];
        $paths = config('skills.discovery_paths', []);

        foreach ($paths as $basePath) {
            if (! is_dir($basePath)) {
                continue;
            }

            $entries = scandir($basePath);
            if ($entries === false) {
                continue;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $skillPath = $basePath.DIRECTORY_SEPARATOR.$entry.DIRECTORY_SEPARATOR.'SKILL.md';
                if (! is_file($skillPath)) {
                    continue;
                }

                try {
                    $skill = Skill::fromFile($entry, $skillPath);
                    $skills[$entry] = $skill;
                } catch (RuntimeException $e) {
                    Log::warning('Skills: failed to load skill', [
                        'slug' => $entry,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $skills;
    }
}
