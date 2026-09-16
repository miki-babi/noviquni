<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait HasSeo
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function seoTitle(?string $fallback = null): string
    {
        return filled($this->seo_title)
            ? (string) $this->seo_title
            : (string) ($fallback ?? $this->defaultSeoTitle());
    }

    public function seoDescription(?string $fallback = null): string
    {
        return filled($this->seo_description)
            ? (string) $this->seo_description
            : (string) ($fallback ?? $this->defaultSeoDescription());
    }

    public function shouldIndex(): bool
    {
        return (bool) ($this->is_indexable ?? true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    #[Scope]
    protected function indexable(Builder $query): Builder
    {
        return $query->where('is_indexable', true);
    }

    protected function defaultSeoTitle(): string
    {
        return (string) ($this->name ?? $this->title ?? config('app.name'));
    }

    protected function defaultSeoDescription(): string
    {
        $source = $this->description ?? $this->seo_content ?? '';

        if (! filled($source)) {
            return '';
        }

        return str((string) $source)->stripTags()->limit(160)->toString();
    }
}
