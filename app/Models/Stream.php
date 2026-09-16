<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Database\Factories\StreamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'is_active',
    'seo_title',
    'seo_description',
    'seo_content',
    'og_image',
    'is_indexable',
])]
class Stream extends Model
{
    /** @use HasFactory<StreamFactory> */
    use HasFactory;

    use HasSeo;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_indexable' => true,
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function learningResources(): HasMany
    {
        return $this->hasMany(LearningResource::class);
    }

    /**
     * @param  Builder<Stream>  $query
     * @return Builder<Stream>
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
            'is_indexable' => 'boolean',
        ];
    }
}
