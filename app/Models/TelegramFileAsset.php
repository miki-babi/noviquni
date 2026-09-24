<?php

namespace App\Models;

use Database\Factories\TelegramFileAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'file_id',
    'file_unique_id',
    'file_name',
    'mime_type',
    'file_size',
    'uploaded_by_username',
])]
class TelegramFileAsset extends Model
{
    /** @use HasFactory<TelegramFileAssetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function label(): string
    {
        $name = filled($this->file_name) ? $this->file_name : 'Untitled';

        return $name.' ('.$this->file_id.')';
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForSelect(int $limit = 200): array
    {
        return static::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (self $asset): array => [$asset->file_id => $asset->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function searchOptions(string $search, int $limit = 50): array
    {
        $query = static::query()->orderByDesc('id');

        if (filled($search)) {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('file_name', 'like', $like)
                    ->orWhere('file_id', 'like', $like)
                    ->orWhere('uploaded_by_username', 'like', $like);
            });
        }

        return $query
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (self $asset): array => [$asset->file_id => $asset->label()])
            ->all();
    }

    /**
     * @param  list<string>  $fileIds
     * @return array<string, string>
     */
    public static function labelsForFileIds(array $fileIds): array
    {
        if ($fileIds === []) {
            return [];
        }

        return static::query()
            ->whereIn('file_id', $fileIds)
            ->get()
            ->mapWithKeys(fn (self $asset): array => [$asset->file_id => $asset->label()])
            ->all();
    }

    public static function vaultCount(): int
    {
        return static::query()->count();
    }
}
