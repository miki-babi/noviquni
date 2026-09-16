<?php

namespace App\View\Components\Seo;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class JsonLd extends Component
{
    /**
     * @param  list<array<string, mixed>>  $graph
     */
    public function __construct(public array $graph) {}

    public function render(): View|Closure|string
    {
        return view('components.seo.json-ld');
    }
}
