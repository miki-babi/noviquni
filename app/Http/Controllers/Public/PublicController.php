<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Seo\JsonLdBuilder;
use App\Services\Seo\SeoMetaBuilder;
use Illuminate\Contracts\View\View;

abstract class PublicController extends Controller
{
    public function __construct(
        protected SeoMetaBuilder $seoMeta,
        protected JsonLdBuilder $jsonLd,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     canonical?: string|null,
     *     ogImage?: string|null,
     *     indexable?: bool,
     *     type?: string
     * }  $meta
     * @param  list<array{name: string, url: string|null}>  $breadcrumbs
     * @param  list<array<string, mixed>>  $jsonLd
     * @param  array<string, mixed>  $data
     */
    protected function page(
        string $view,
        array $meta,
        array $breadcrumbs = [],
        array $jsonLd = [],
        array $data = [],
    ): View {
        return view($view, array_merge($data, [
            'seo' => $this->seoMeta->build($meta),
            'breadcrumbs' => $breadcrumbs,
            'jsonLd' => $jsonLd,
        ]));
    }
}
