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
    'is_premium',
    'is_published',
    'content',
    'generation_kind',
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
        'is_published' => false,
        'is_indexable' => true,
    ];

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

    public function downloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class);
    }

    /**
     * Whether this published free resource can be studied in the browser.
     */
    public function canStudyOnWeb(): bool
    {
        return $this->is_published
            && ! $this->is_premium
            && $this->studyKind() !== null
            && $this->rawStudyPayload() !== null;
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
     * Study payload for web viewers. Null when the resource must not be studied on the web.
     *
     * @return array<string, mixed>|null
     */
    public function studyPayload(): ?array
    {
        if (! $this->canStudyOnWeb()) {
            return null;
        }

        return $this->rawStudyPayload();
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
        $kind = $this->studyKind();

        if ($kind !== null && $this->playerPayload() !== null) {
            return $kind->miniAppRouteName();
        }

        return 'tg.resources.show';
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
        $payload = $this->studyPayload();

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

        return $payload;
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
            'is_premium' => 'boolean',
            'is_published' => 'boolean',
            'is_indexable' => 'boolean',
        ];
    }

    protected function defaultSeoTitle(): string
    {
        return (string) $this->title;
    }
}
