<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'bookmarkable_type', 'bookmarkable_id'])]
class Bookmark extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookmarkable(): MorphTo
    {
        return $this->morphTo();
    }

    public function learningResource(): ?LearningResource
    {
        $bookmarkable = $this->bookmarkable;

        return $bookmarkable instanceof LearningResource ? $bookmarkable : null;
    }

    public function opportunity(): ?Opportunity
    {
        $bookmarkable = $this->bookmarkable;

        return $bookmarkable instanceof Opportunity ? $bookmarkable : null;
    }
}
