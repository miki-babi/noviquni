<?php

namespace App\Models;

use App\Enums\OpportunityType;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'title',
    'slug',
    'type',
    'description',
    'url',
    'deadline',
    'is_published',
    'is_verified_partner',
    'partner_name',
    'guidance_contact_username',
    'guidance_opening_message',
    'sort_order',
])]
class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_published' => false,
        'is_verified_partner' => false,
        'sort_order' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (Opportunity $opportunity): void {
            if (blank($opportunity->slug) && filled($opportunity->title)) {
                $opportunity->slug = static::uniqueSlugFromTitle($opportunity->title, $opportunity->id);
            }
        });
    }

    /**
     * @return MorphMany<Bookmark, $this>
     */
    public function bookmarks(): MorphMany
    {
        return $this->morphMany(Bookmark::class, 'bookmarkable');
    }

    /**
     * @param  Builder<Opportunity>  $query
     * @return Builder<Opportunity>
     */
    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<Opportunity>  $query
     * @return Builder<Opportunity>
     */
    #[Scope]
    protected function ofType(Builder $query, OpportunityType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OpportunityType::class,
            'deadline' => 'date',
            'is_published' => 'boolean',
            'is_verified_partner' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function hasVerifiedPartner(): bool
    {
        return $this->is_verified_partner && filled($this->partner_name);
    }

    public static function uniqueSlugFromTitle(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'opportunity';
        $slug = $base;
        $suffix = 1;

        while (static::query()
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->withTrashed()
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
