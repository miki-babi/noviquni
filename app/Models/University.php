<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Database\Factories\UniversityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'location',
    'website',
    'logo_path',
    'is_active',
    'sort_order',
    'seo_title',
    'seo_description',
    'seo_content',
    'og_image',
    'is_indexable',
])]
class University extends Model
{
    /** @use HasFactory<UniversityFactory> */
    use HasFactory;

    use HasSeo;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
        'is_indexable' => true,
    ];

    public function learningResources(): HasMany
    {
        return $this->hasMany(LearningResource::class);
    }

    /**
     * @param  Builder<University>  $query
     * @return Builder<University>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'is_indexable' => 'boolean',
        ];
    }
}
