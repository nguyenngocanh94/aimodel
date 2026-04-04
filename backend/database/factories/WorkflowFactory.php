<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'schema_version' => 1,
            'tags' => fake()->randomElements(['video', 'ai', 'audio', 'text', 'image'], 2),
            'document' => [
                'nodes' => [
                    ['id' => 'node-1', 'type' => 'script-writer', 'disabled' => false],
                ],
                'edges' => [],
            ],
        ];
    }
}
