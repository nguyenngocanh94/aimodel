<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Services\Skills\MetaTools\ListSkillsTool;
use App\Services\Skills\MetaTools\SkillReadTool;
use App\Services\Skills\MetaTools\SkillTool;

/**
 * Adds skill discovery and tool-loading capability to an Agent.
 *
 * Usage in your Agent class:
 *
 *   use Skillable;
 *
 *   public function skills(): iterable
 *   {
 *       return ['list-workflows', 'run-workflow', 'catalog' => SkillInclusionMode::Lite];
 *   }
 *
 * Then in tools():
 *   return $this->skillTools();
 *
 * And in instructions():
 *   return $this->withSkillInstructions(SystemPrompt::build(...));
 */
trait Skillable
{
    private ?SkillRegistry $_skillRegistry = null;

    /**
     * Return the list of skill slugs the agent may use.
     * Values can be:
     *   - string slug (defaults to Lite mode from config)
     *   - 'slug' => SkillInclusionMode::Lite|::Full (explicit mode)
     *
     * @return iterable<string, SkillInclusionMode|string>
     */
    abstract public function skills(): iterable;

    /**
     * Returns the full set of tools: meta-tools + skill tools + any extras.
     *
     * @return array<int, object>
     */
    protected function skillTools(): array
    {
        $registry = $this->getSkillRegistry();
        $tools = [
            new ListSkillsTool($registry),
            new SkillTool($registry),
            new SkillReadTool($registry),
        ];

        // Allow subclasses to provide manually-constructed tool instances
        // (e.g. when a tool needs constructor args that app() can't resolve).
        foreach ($this->getSkillToolOverrides() as $tool) {
            $tools[] = $tool;
        }

        // Also resolve tools from the registry for Full-mode skills.
        foreach ($this->resolveSkillTools($registry) as $tool) {
            $tools[] = $tool;
        }

        return $tools;
    }

    /**
     * Build a skill-augmented system prompt.
     *
     * @param  string  $staticPrompt  The base system prompt (e.g. SystemPrompt::build(...)).
     * @param  string  $dynamicPrompt  Optional dynamic additions (e.g. catalog preview).
     */
    protected function withSkillInstructions(string $staticPrompt, string $dynamicPrompt = ''): string
    {
        $skillLines = [];
        $fullSkillLines = [];
        $discoveryMode = config('skills.discovery_mode', 'lite');

        foreach ($this->skills() as $slug => $modeOrSlug) {
            if (is_string($modeOrSlug) && $modeOrSlug !== '') {
                $slug = $modeOrSlug;
                $mode = $discoveryMode === 'full' ? SkillInclusionMode::Full : SkillInclusionMode::Lite;
            } elseif ($modeOrSlug instanceof SkillInclusionMode) {
                $mode = $modeOrSlug;
            } else {
                continue;
            }

            $skill = $this->getSkillRegistry()->get($slug);
            if ($skill === null) {
                continue;
            }

            $line = "- **{$skill->name}**: {$skill->description}";
            $skillLines[] = $line;

            if ($mode === SkillInclusionMode::Full) {
                $fullSkillLines[] = "## {$skill->name}\n\n".$skill->body;
            }
        }

        $skillSection = $skillLines !== []
            ? "\n\n# Available Skills\n".implode("\n", $skillLines)
            : '';

        $fullSection = $fullSkillLines !== []
            ? "\n\n# Skill Instructions\n".implode("\n\n---\n\n", $fullSkillLines)
            : '';

        return $staticPrompt.$skillSection.$fullSection."\n\n".$dynamicPrompt;
    }

    /**
     * Override this in subclasses to provide manually-constructed tool instances
     * that need constructor arguments (e.g. chatId, botToken) that cannot be
     * resolved via app()->make().
     *
     * @return array<int, object>
     */
    protected function getSkillToolOverrides(): array
    {
        return [];
    }

    protected function getSkillRegistry(): SkillRegistry
    {
        if ($this->_skillRegistry === null) {
            $this->_skillRegistry = new SkillRegistry();
        }

        return $this->_skillRegistry;
    }

    /**
     * @return array<int, object>
     */
    private function resolveSkillTools(SkillRegistry $registry): array
    {
        $tools = [];
        $discoveryMode = config('skills.discovery_mode', 'lite');

        foreach ($this->skills() as $slug => $modeOrSlug) {
            if (is_string($modeOrSlug) && $modeOrSlug !== '') {
                $slug = $modeOrSlug;
            }

            if ($modeOrSlug instanceof SkillInclusionMode && $modeOrSlug !== SkillInclusionMode::Full) {
                continue; // Lite mode: no tools in tools() list
            }

            foreach ($registry->toolClassesForSkill($slug) as $toolClass) {
                try {
                    $tools[] = app($toolClass);
                } catch (\Throwable $e) {
                    // Tool class not instantiable via container — skip
                }
            }
        }

        return $tools;
    }
}
