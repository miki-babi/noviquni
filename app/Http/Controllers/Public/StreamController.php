<?php

namespace App\Http\Controllers\Public;

use App\Models\Stream;
use Illuminate\Contracts\View\View;

class StreamController extends PublicController
{
    public function show(Stream $stream): View
    {
        abort_unless($stream->is_active, 404);

        $stream->load([
            'courses' => fn ($query) => $query->active()->orderBy('name'),
        ]);

        $title = $stream->seoTitle($stream->name.' stream courses');
        $description = $stream->seoDescription(
            'Freshman courses and resources for the '.$stream->name.' stream in Ethiopian universities.'
        );
        $url = route('streams.show', $stream);

        return $this->page(
            'public.streams.show',
            [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
                'ogImage' => $stream->og_image,
                'indexable' => $stream->shouldIndex(),
            ],
            [
                ['name' => 'Home', 'url' => route('home')],
                ['name' => $stream->name, 'url' => $url],
            ],
            $this->jsonLd->forPage(
                'stream',
                $title,
                $description,
                $url,
                [
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => $stream->name, 'url' => $url],
                ],
            ),
            ['stream' => $stream],
        );
    }
}
