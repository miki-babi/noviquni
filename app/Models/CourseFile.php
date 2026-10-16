<?php

namespace App\Models;

use Database\Factories\CourseFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'course_id',
    'title',
    'description',
    'path',
    'disk',
    'size',
    'mime',
    'is_published',
    'external_id',
    'telegram_file_id',
])]
class CourseFile extends Model
{
    /** @use HasFactory<CourseFileFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_published' => false,
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function telegramFileAssets(): HasMany
    {
        return $this->hasMany(TelegramFileAsset::class);
    }

    public function url(): ?string
    {
        if (blank($this->path)) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_published' => 'boolean',
        ];
    }
}
