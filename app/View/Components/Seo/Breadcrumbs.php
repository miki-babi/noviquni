<?php

namespace App\View\Components\Seo;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Breadcrumbs extends Component
{
    /**
     * @param  list<array{name: string, url: string|null}>  $items
     */
    public function __construct(public array $items) {}

    public function render(): View|Closure|string
    {
        return view('components.seo.breadcrumbs');
    }
}
