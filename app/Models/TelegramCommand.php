<?php

namespace App\Models;

use Database\Factories\TelegramCommandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'command',
    'message',
    'learning_resource_id',
    'is_active',
])]
class TelegramCommand extends Model
{
    /** @use HasFactory<TelegramCommandFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function learningResource(): BelongsTo
    {
        return $this->belongsTo(LearningResource::class);
    }

    public function fileAssets(): BelongsToMany
    {
        return $this->belongsToMany(TelegramFileAsset::class, 'telegram_command_file')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function normalizeCommand(string $value): string
    {
        return strtolower(ltrim(trim($value), '/'));
    }

    public static function isReservedCommand(string $command): bool
    {
        return $command === 'start';
    }

    public function slashCommand(): string
    {
        return '/'.$this->command;
    }

    /**
     * @param  list<int|string>  $fileAssetIds
     */
    public function syncFileAssets(array $fileAssetIds): void
    {
        $sync = [];

        foreach (array_values($fileAssetIds) as $order => $id) {
            $sync[(int) $id] = ['sort_order' => $order];
        }

        $this->fileAssets()->sync($sync);
    }
}
