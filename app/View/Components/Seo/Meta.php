<?php

namespace App\View\Components\Seo;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Meta extends Component
{
    /**
     * @param  array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     og_title: string,
     *     og_description: string,
     *     og_image: string|null,
     *     og_type: string,
     *     robots: string,
     *     twitter_card: string
     * }  $meta
     */
    public function __construct(public array $meta) {}

    public function render(): View|Closure|string
    {
        return view('components.seo.meta');
    }
}
