<?php

namespace Database\Factories;

use App\Models\Notebook;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportJob>
 */
class ImportJobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'notebook_id' => fn (array $attributes) => Notebook::factory()->create(['owner' => $attributes['user_id']])->id,
            'status' => 'pending',
            'total_files' => null,
            'processed_files' => null,
            'file_path' => 'import/'.Str::random(40).'.zip',
        ];
    }

    public function processing(int $total = 10, int $processed = 3): static
    {
        return $this->state(fn () => ['status' => 'processing', 'total_files' => $total, 'processed_files' => $processed]);
    }

    public function finished(int $total = 10): static
    {
        return $this->state(fn () => ['status' => 'finished', 'total_files' => $total, 'processed_files' => $total]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed']);
    }
}
