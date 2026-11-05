<?php

namespace App\Http\Controllers\Public;

use App\Enums\ResourceHub;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use Illuminate\Contracts\View\View;

class HomeController extends PublicController
{
    public function __invoke(): View
    {
        $courses = Course::query()
            ->active()
            ->indexable()
            ->orderBy('name')
            ->limit(8)
            ->get();

        $resources = LearningResource::query()
            ->published()
            ->indexable()
            ->with(['course'])
            ->latest('id')
            ->limit(8)
            ->get();

        $stats = [
            'courses' => Course::query()->active()->count(),
            'resources' => LearningResource::query()->published()->count(),
            'modules' => 0,
            'notes' => LearningResource::query()->published()->where('type', ResourceType::ReferenceBooks)->count(),
            'exams' => LearningResource::query()->published()->whereIn('type', [
                ResourceType::PastExams,
                ResourceType::PracticeExams,
            ])->count(),
        ];

        $title = 'Freshman resources for Ethiopian university students';
        $description = 'Find modules, notes, past exams, and practice sets for Ethiopian freshman courses — then open everything in Telegram.';
        $url = route('home');

        return $this->page(
            'public.home',
            [
                'title' => $title,
                'description' => $description,
                'canonical' => $url,
                'indexable' => true,
            ],
            [
                ['name' => 'Home', 'url' => $url],
            ],
            $this->jsonLd->forPage('home', $title, $description, $url),
            [
                'courses' => $courses,
                'resources' => $resources,
                'hubs' => ResourceHub::cases(),
                'stats' => $stats,
            ],
        );
    }
}
