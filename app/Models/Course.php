<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'stream_id',
    'name',
    'slug',
    'is_active',
    'seo_title',
    'seo_description',
    'seo_content',
    'og_image',
    'is_indexable',
])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    use HasSeo;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'is_indexable' => true,
    ];

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_courses')->withTimestamps();
    }

    public function learningResources(): HasMany
    {
        return $this->hasMany(LearningResource::class);
    }

    public function studyPlans(): HasMany
    {
        return $this->hasMany(StudyPlan::class);
    }

    /**
     * @param  Builder<Course>  $query
     * @return Builder<Course>
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
