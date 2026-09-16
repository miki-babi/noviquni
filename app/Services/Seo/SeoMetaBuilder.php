<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Storage;

class SeoMetaBuilder
{
    /**
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     canonical?: string|null,
     *     ogImage?: string|null,
     *     indexable?: bool,
     *     type?: string
     * }  $meta
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     og_title: string,
     *     og_description: string,
     *     og_image: string|null,
     *     og_type: string,
     *     robots: string,
     *     twitter_card: string
     * }
     */
    public function build(array $meta): array
    {
        $title = $meta['title'];
        $description = (string) ($meta['description'] ?? '');
        $appName = (string) config('app.name');

        if (! str_contains($title, $appName)) {
            $title = $title.' | '.$appName;
        }

        $ogImage = $meta['ogImage'] ?? null;

        if (is_string($ogImage) && $ogImage !== '' && ! str_starts_with($ogImage, 'http')) {
            $ogImage = Storage::disk(config('filesystems.default'))->url($ogImage);
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $meta['canonical'] ?? url()->current(),
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $ogImage,
            'og_type' => $meta['type'] ?? 'website',
            'robots' => ($meta['indexable'] ?? true) ? 'index,follow' : 'noindex,follow',
            'twitter_card' => 'summary_large_image',
        ];
    }
}
