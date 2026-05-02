<?php

namespace Fluxio\Tasks\Database\Factories;

use Fluxio\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'status' => 'pending',
            'priority' => 'normal',
            'due_at' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'lead_id' => null,
        ];
    }
}
