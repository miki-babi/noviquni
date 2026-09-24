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

    public function module(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::Module,
            'module_id' => null,
            'generation_kind' => null,
            'content' => null,
        ]);
    }

    public function worksheet(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::Worksheet,
            'generation_kind' => null,
            'content' => [
                'body' => fake()->paragraphs(2, true),
            ],
        ]);
    }

    public function notes(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::Notes,
            'generation_kind' => CollegeResourceKind::Notes,
            'content' => [
                'kind' => CollegeResourceKind::Notes->value,
                'scope_type' => 'section',
                'scope_id' => 'section-1',
                'from_cache' => false,
                'generated_at' => now()->toIso8601String(),
                'payload' => [
                    'title' => 'Anthropology Notes',
                    'summary' => 'Anthropology studies humankind across time and space.',
                    'keyDefinitions' => [
                        [
                            'term' => 'Anthropology',
                            'definition' => 'The study of human beings.',
                        ],
                    ],
                    'corePrinciples' => [
                        'Culture and biology are inseparable.',
                    ],
                    'commonMistakes' => [
                        'Confusing anthropology with sociology alone.',
                    ],
                    'keyFormulas' => [],
                ],
            ],
        ]);
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

    public function flashcards(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ResourceType::Flashcards,
            'is_premium' => true,
            'generation_kind' => CollegeResourceKind::Flashcards,
            'content' => [
                'kind' => CollegeResourceKind::Flashcards->value,
                'scope_type' => 'section',
                'scope_id' => 'section-1',
                'from_cache' => false,
                'generated_at' => now()->toIso8601String(),
                'payload' => [
                    'cards' => [
                        [
                            'front' => 'What does anthropos mean?',
                            'back' => 'Human being or mankind',
                            'hint' => 'Think of the Greek root.',
                            'category' => 'Etymology',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
