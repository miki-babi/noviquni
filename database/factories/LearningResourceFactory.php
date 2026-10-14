<?php

namespace Database\Factories;

use App\Enums\CollegeResourceKind;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LearningResource>
 */
class LearningResourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(5),
            'description' => fake()->paragraph(),
            'topics' => fake()->words(4),
            'type' => fake()->randomElement(ResourceType::creatableCases()),
            'course_id' => Course::factory(),
            'stream_id' => fn (array $attributes) => Course::query()->find($attributes['course_id'])?->stream_id,
            'module_id' => null,
            'sort_order' => 0,
            'is_premium' => false,
            'is_published' => false,
            'is_indexable' => true,
            'content' => null,
            'generation_kind' => null,
            'files' => null,
            'telegram_files' => null,
        ];
    }

    public function withFiles(array $paths = ['learning-resources/sample.pdf']): static
    {
        return $this->state(fn (array $attributes) => [
            'files' => $paths,
            'telegram_files' => null,
            'generation_kind' => null,
            'content' => null,
        ]);
    }

    /**
     * @param  list<array{file_id: string, file_name?: string|null}>  $files
     */
    public function withTelegramFiles(array $files): static
    {
        return $this->state(fn (array $attributes) => [
            'telegram_files' => $files,
            'generation_kind' => null,
            'content' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_premium' => true,
        ]);
    }

    public function referenceBooks(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::ReferenceBooks,
            'generation_kind' => null,
            'content' => null,
        ]);
    }

    /**
     * @deprecated Use referenceBooks() — kept for existing tests that still call notes()/module()/worksheet().
     */
    public function notes(): static
    {
        return $this->referenceBooks();
    }

    /**
     * @deprecated Use referenceBooks().
     */
    public function module(): static
    {
        return $this->referenceBooks();
    }

    /**
     * @deprecated Use referenceBooks().
     */
    public function worksheet(): static
    {
        return $this->referenceBooks();
    }

    /**
     * @deprecated Use quiz().
     */
    public function flashcards(): static
    {
        return $this->quiz()->premium();
    }

    public function quiz(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::Quiz,
            'generation_kind' => CollegeResourceKind::Quiz,
            'content' => [
                'kind' => CollegeResourceKind::Quiz->value,
                'scope_type' => 'section',
                'scope_id' => 'section-1',
                'from_cache' => false,
                'generated_at' => now()->toIso8601String(),
                'payload' => [
                    'questions' => [
                        [
                            'question' => 'What are the Greek roots of anthropology?',
                            'options' => [
                                'Wrong A',
                                'Anthropos and logos',
                                'Wrong C',
                                'Wrong D',
                            ],
                            'answerIndex' => 1,
                            'explanation' => 'Anthropos means human being.',
                            'difficulty' => 'easy',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function exam(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::PracticeExams,
            'generation_kind' => CollegeResourceKind::Exam,
            'content' => [
                'kind' => CollegeResourceKind::Exam->value,
                'scope_type' => 'section',
                'scope_id' => 'section-1',
                'from_cache' => false,
                'generated_at' => now()->toIso8601String(),
                'payload' => [
                    'title' => 'Multiple Choice',
                    'instructions' => 'Answer every question. Choose the best option.',
                    'questions' => [
                        [
                            'question' => 'Which statement best describes anthropology?',
                            'options' => [
                                'It studies only fossils',
                                'It studies humankind across time and space',
                                'It is limited to genetics',
                                'It ignores culture',
                            ],
                            'answerIndex' => 1,
                            'explanation' => 'Anthropology aims for an integrated picture of humankind.',
                            'difficulty' => 'medium',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function pastExams(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::PastExams,
            'generation_kind' => null,
            'content' => null,
        ]);
    }
}
