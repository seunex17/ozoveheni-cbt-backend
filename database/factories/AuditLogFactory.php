<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_id' => 1, 'actor_name' => fake()->name(), 'action' => 'updated',
            'subject_type' => 'exam', 'subject_id' => fake()->numberBetween(1, 10000),
            'subject_label' => 'Semester examination',
            'before_values' => ['title' => 'Previous title'], 'after_values' => ['title' => 'Updated title'],
            'metadata' => [],
        ];
    }
}
