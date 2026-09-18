<?php

namespace App\Http\Controllers\Public;

use App\Enums\ResourceHub;
use App\Enums\ResourceType;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\Stream;
use App\Models\University;
use Illuminate\Contracts\View\View;

class HomeController extends PublicController
{
    public function __invoke(): View
    {
        $universities = University::query()
            ->active()
            ->indexable()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $courses = Course::query()
            ->active()
            ->indexable()
            ->with('stream')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $resources = LearningResource::query()
            ->published()
            ->indexable()
            ->with(['course', 'stream'])
            ->latest('id')
            ->limit(8)
            ->get();

        $streams = Stream::query()
            ->active()
            ->indexable()
            ->withCount(['courses' => fn ($query) => $query->active()])
            ->orderBy('name')
            ->get();

        $stats = [
            'universities' => University::query()->active()->count(),
            'courses' => Course::query()->active()->count(),
            'resources' => LearningResource::query()->published()->count(),
            'modules' => LearningResource::query()->published()->where('type', ResourceType::Module)->count(),
            'notes' => LearningResource::query()->published()->whereIn('type', [
                ResourceType::Notes,
                ResourceType::Worksheet,
                ResourceType::ReferenceBooks,
            ])->count(),
            'exams' => LearningResource::query()->published()->whereIn('type', [
                ResourceType::MidExam,
                ResourceType::FinalExam,
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
                'universities' => $universities,
                'courses' => $courses,
                'resources' => $resources,
                'streams' => $streams,
                'hubs' => ResourceHub::cases(),
                'stats' => $stats,
            ],
        );
    }
}
