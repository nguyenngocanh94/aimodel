<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Workflow;
use Illuminate\Database\Seeder;

class DemoWorkflowSeeder extends Seeder
{
    /**
     * Seed the database with a demo M1 pipeline workflow.
     *
     * Pipeline: UserPrompt → ScriptWriter → SceneSplitter → PromptRefiner → ImageGenerator → ReviewCheckpoint
     */
    public function run(): void
    {
        Workflow::updateOrCreate(
            ['name' => 'M1 Demo – AI Video Pipeline'],
            [
                'description'    => 'End-to-end M1 content pipeline: takes a text prompt, writes a structured script, '
                    . 'splits it into visual scenes, refines each scene into image generation prompts, '
                    . 'generates images, and pauses at a review checkpoint for human approval.',
                'schema_version' => 1,
                'tags'           => ['demo', 'video', 'ai', 'image', 'pipeline'],
                'document'       => self::buildDocument(),
                // Catalog metadata — agent-triggerable
                'slug'           => 'tvc-pipeline',
                'triggerable'    => true,
                'nl_description' => 'Pipeline đầy đủ: prompt → script → scenes → refined prompts → images → review checkpoint.',
                'param_schema'   => ['prompt' => ['required', 'string', 'min:10']],
            ],
        );

        $this->command->info('Demo M1 workflow seeded.');
    }

    private static function buildDocument(): array
    {
        return [
            'nodes' => self::buildNodes(),
            'edges' => self::buildEdges(),
        ];
    }

    // ------------------------------------------------------------------
    // Nodes
    // ------------------------------------------------------------------

    private static function buildNodes(): array
    {
        return [
            [
                'id' => 'user-prompt',
                'type' => 'userPrompt',
                'config' => [
                    'prompt' => 'Create a 60-second explainer video about how large language models work. '
                        . 'Cover tokenization, attention mechanisms, and text generation in a way '
                        . 'that is accessible to a non-technical audience.',
                ],
                'position' => ['x' => 0, 'y' => 200],
            ],
            [
                'id' => 'script-writer',
                'type' => 'scriptWriter',
                'config' => [
                    'style' => 'Friendly, conversational narration with vivid analogies',
                    'structure' => 'three_act',
                    'includeHook' => true,
                    'includeCTA' => true,
                    'targetDurationSeconds' => 60,
                    'provider' => 'stub',
                    'model' => 'gpt-4o',
                ],
                'position' => ['x' => 350, 'y' => 200],
            ],
            [
                'id' => 'scene-splitter',
                'type' => 'sceneSplitter',
                'config' => [
                    'maxScenes' => 6,
                    'includeVisualDescriptions' => true,
                    'provider' => 'stub',
                    'model' => 'gpt-4o',
                ],
                'position' => ['x' => 700, 'y' => 200],
            ],
            [
                'id' => 'prompt-refiner',
                'type' => 'promptRefiner',
                'config' => [
                    'imageStyle' => 'cinematic, high quality, photorealistic, soft studio lighting',
                    'aspectRatio' => '16:9',
                    'detailLevel' => 'detailed',
                    'provider' => 'stub',
                    'model' => 'gpt-4o',
                ],
                'position' => ['x' => 1050, 'y' => 200],
            ],
            [
                'id' => 'image-generator',
                'type' => 'imageGenerator',
                'config' => [
                    'provider' => 'stub',
                    'inputMode' => 'prompt',
                    'outputMode' => 'single',
                ],
                'position' => ['x' => 1400, 'y' => 200],
            ],
            [
                'id' => 'review-checkpoint',
                'type' => 'reviewCheckpoint',
                'config' => [
                    'approved' => false,
                ],
                'position' => ['x' => 1750, 'y' => 200],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Edges
    // ------------------------------------------------------------------

    private static function buildEdges(): array
    {
        return [
            [
                'id' => 'edge-prompt-to-script',
                'source' => 'user-prompt',
                'sourceHandle' => 'prompt',
                'target' => 'script-writer',
                'targetHandle' => 'prompt',
            ],
            [
                'id' => 'edge-script-to-scenes',
                'source' => 'script-writer',
                'sourceHandle' => 'script',
                'target' => 'scene-splitter',
                'targetHandle' => 'script',
            ],
            [
                'id' => 'edge-scenes-to-refiner',
                'source' => 'scene-splitter',
                'sourceHandle' => 'scenes',
                'target' => 'prompt-refiner',
                'targetHandle' => 'scenes',
            ],
            [
                'id' => 'edge-refiner-to-imagegen',
                'source' => 'prompt-refiner',
                'sourceHandle' => 'prompts',
                'target' => 'image-generator',
                'targetHandle' => 'prompt',
            ],
            [
                'id' => 'edge-imagegen-to-review',
                'source' => 'image-generator',
                'sourceHandle' => 'image',
                'target' => 'review-checkpoint',
                'targetHandle' => 'data',
            ],
        ];
    }
}
