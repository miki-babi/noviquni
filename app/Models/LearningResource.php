<?php

namespace App\Models;

use App\Enums\CollegeResourceKind;
use App\Enums\ResourceType;
use App\Models\Concerns\HasSeo;
use Database\Factories\LearningResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title',
    'slug',
    'description',
    'topics',
    'type',
    'stream_id',
    'course_id',
    'university_id',
    'semester_id',
    'module_id',
    'sort_order',
    'is_premium',
    'is_bait',
    'is_published',
    'content',
    'generation_kind',
    'files',
    'seo_title',
    'seo_description',
    'seo_content',
    'og_image',
    'is_indexable',
])]
class LearningResource extends Model
{
    /** @use HasFactory<LearningResourceFactory> */
    use HasFactory, HasSeo, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_premium' => false,
        'is_bait' => false,
        'is_published' => false,
        'is_indexable' => true,
        'sort_order' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (LearningResource $resource): void {
            if ($resource->type === ResourceType::Flashcards) {
                $resource->is_premium = true;
                $resource->is_bait = false;
            }

            if ($resource->is_bait) {
                $resource->is_premium = false;
            }
        });
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(self::class, 'module_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'module_id')->orderBy('sort_order');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class);
    }

    /**
     * Public web is CTA-only — never an in-browser study surface.
     */
    public function canStudyOnWeb(): bool
    {
        return false;
    }

    public function hasFiles(): bool
    {
        return filled($this->files);
    }

    public function studyKind(): ?CollegeResourceKind
    {
        if ($this->generation_kind instanceof CollegeResourceKind) {
            return $this->generation_kind;
        }

        $content = $this->content;

        if (! is_array($content)) {
            return null;
        }

        return CollegeResourceKind::tryFrom((string) ($content['kind'] ?? ''));
    }

    /**
     * Study payload for web viewers. Always null — web is CTA only.
     *
     * @return array<string, mixed>|null
     */
    public function studyPayload(): ?array
    {
        return null;
    }

    /**
     * Payload for Telegram Mini App players. Not gated by free-web rules.
     *
     * @return array<string, mixed>|null
     */
    public function playerPayload(): ?array
    {
        if ($this->studyKind() === null) {
            return null;
        }

        return $this->rawStudyPayload();
    }

    public function miniAppRouteName(): string
    {
        return $this->type->miniAppRouteName();
    }

    public function miniAppUrl(): string
    {
        return route($this->miniAppRouteName(), $this);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function flashcards(): array
    {
        $payload = $this->playerPayload();

        if ($payload === null) {
            return [];
        }

        return $this->normalizeFlashcards($payload);
    }

    /**
     * @param  Builder<LearningResource>  $query
     * @return Builder<LearningResource>
     */
    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<LearningResource>  $query
     * @return Builder<LearningResource>
     */
    #[Scope]
    protected function bait(Builder $query): Builder
    {
        return $query->where('is_bait', true);
    }

    /**
     * @param  Builder<LearningResource>  $query
     * @return Builder<LearningResource>
     */
    #[Scope]
    protected function modules(Builder $query): Builder
    {
        return $query->where('type', ResourceType::Module);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function rawStudyPayload(): ?array
    {
        $content = $this->content;

        if (! is_array($content)) {
            return null;
        }

        $payload = $content['payload'] ?? null;

        if (! is_array($payload) || $payload === []) {
            return null;
        }

        if ($this->studyKind() === CollegeResourceKind::Flashcards) {
            $cards = $this->normalizeFlashcards($payload);

            return $cards === [] ? null : ['cards' => $cards];
        }

        if ($this->studyKind() === CollegeResourceKind::Exam) {
            $exam = $this->normalizeExam($payload);

            return ($exam['questions'] ?? []) === [] ? null : $exam;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{title: ?string, instructions: ?string, questions: list<array<string, mixed>>}
     */
    public function normalizeExam(array $payload): array
    {
        $instructions = filled($payload['instructions'] ?? null)
            ? (string) $payload['instructions']
            : null;

        $title = filled($payload['title'] ?? null)
            ? (string) $payload['title']
            : null;

        $rawQuestions = $payload['questions'] ?? null;
        $partA = $payload['partA'] ?? null;

        if (! is_array($rawQuestions) || $rawQuestions === []) {
            $rawQuestions = $this->unwrapExamPart($partA);

            if ($title === null && is_array($partA) && ! array_is_list($partA) && filled($partA['title'] ?? null)) {
                $title = (string) $partA['title'];
            }
        }

        $questions = [];

        foreach (array_values(is_array($rawQuestions) ? $rawQuestions : []) as $question) {
            if (! is_array($question)) {
                continue;
            }

            $options = $question['options'] ?? null;

            if (! is_array($options) || $options === [] || blank($question['question'] ?? null)) {
                continue;
            }

            $questions[] = [
                'question' => (string) $question['question'],
                'options' => array_values($options),
                'answerIndex' => (int) ($question['answerIndex'] ?? 0),
                'explanation' => filled($question['explanation'] ?? null)
                    ? (string) $question['explanation']
                    : (filled($question['rationale'] ?? null) ? (string) $question['rationale'] : null),
                'difficulty' => filled($question['difficulty'] ?? null)
                    ? (string) $question['difficulty']
                    : null,
            ];
        }

        return [
            'title' => $title,
            'instructions' => $instructions,
            'questions' => $questions,
        ];
    }

    /**
     * @return list<mixed>
     */
    protected function unwrapExamPart(mixed $part): array
    {
        if (! is_array($part) || $part === []) {
            return [];
        }

        if (array_is_list($part)) {
            return $part;
        }

        $questions = $part['questions'] ?? null;

        return is_array($questions) ? array_values($questions) : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    protected function normalizeFlashcards(array $payload): array
    {
        $cards = $payload['cards'] ?? (array_is_list($payload) ? $payload : []);

        if (! is_array($cards)) {
            return [];
        }

        return array_values(array_filter(
            $cards,
            fn ($card): bool => is_array($card) && (filled($card['front'] ?? null) || filled($card['back'] ?? null)),
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ResourceType::class,
            'generation_kind' => CollegeResourceKind::class,
            'topics' => 'array',
            'content' => 'array',
            'files' => 'array',
            'is_premium' => 'boolean',
            'is_bait' => 'boolean',
            'is_published' => 'boolean',
            'is_indexable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function defaultSeoTitle(): string
    {
        return (string) $this->title;
    }
}
