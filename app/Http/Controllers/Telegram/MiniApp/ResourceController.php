<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\LearningResource;
use Illuminate\Http\RedirectResponse;

class ResourceController extends Controller
{
    public function show(LearningResource $resource): RedirectResponse
    {
        abort_unless($resource->is_published, 404);

        return redirect()->route($resource->miniAppRouteName(), $resource);
    }
}
