<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\NoteVersion;
use App\Services\NoteVersionService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NoteVersion>
 */
class NoteVersionFactory extends Factory
{
    protected $model = NoteVersion::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);
        $content = '<p>'.fake()->paragraph().'</p>';

        return [
            'note_id' => Note::factory(),
            'title' => $title,
            'content' => $content,
            'content_hash' => NoteVersionService::hash($title, $content),
            'reason' => 'manual',
            'label' => null,
            'pinned' => false,
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['pinned' => true]);
    }

    public function reason(string $reason): static
    {
        return $this->state(fn () => ['reason' => $reason]);
    }
}
