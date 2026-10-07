<?php

namespace Database\Seeders;

use App\Enums\OpportunityType;
use App\Models\Opportunity;
use Illuminate\Database\Seeder;

class OpportunitySeeder extends Seeder
{
    public function run(): void
    {
        $posts = [
            [
                'slug' => 'mastercard-foundation-scholars-ethiopia',
                'title' => 'Mastercard Foundation Scholars — Ethiopia',
                'type' => OpportunityType::Scholarship,
                'description' => 'Fully funded undergraduate and graduate scholarships for academically strong Ethiopian students with leadership potential. Covers tuition, living stipend, and leadership training.',
                'url' => 'https://example.com/opportunities/mastercard-scholars',
                'deadline' => now()->addMonths(3)->toDateString(),
                'sort_order' => 1,
                'is_verified_partner' => true,
                'partner_name' => 'Mastercard Foundation',
            ],
            [
                'slug' => 'moe-need-based-tuition-grant',
                'title' => 'MoE Need-Based Tuition Grant',
                'type' => OpportunityType::Scholarship,
                'description' => 'Government support for freshman and continuing students who demonstrate financial need. Apply through your university student affairs office.',
                'url' => 'https://example.com/opportunities/moe-tuition-grant',
                'deadline' => null,
                'sort_order' => 2,
            ],
            [
                'slug' => 'ethio-telecom-summer-internship',
                'title' => 'Ethio Telecom Summer Internship',
                'type' => OpportunityType::Internship,
                'description' => '8-week paid internship for STEM and business students. Teams include network ops, customer experience, and digital products. Based in Addis Ababa.',
                'url' => 'https://example.com/opportunities/ethio-telecom-internship',
                'deadline' => now()->addMonths(2)->toDateString(),
                'sort_order' => 1,
            ],
            [
                'slug' => 'un-eca-youth-internship',
                'title' => 'UN ECA Youth Internship Programme',
                'type' => OpportunityType::Internship,
                'description' => 'Internship placements with the UN Economic Commission for Africa. Open to final-year and recent graduates interested in policy, economics, and development.',
                'url' => 'https://example.com/opportunities/un-eca-internship',
                'deadline' => now()->addWeeks(6)->toDateString(),
                'sort_order' => 2,
            ],
            [
                'slug' => 'safaricom-ethiopia-graduate-trainee',
                'title' => 'Safaricom Ethiopia Graduate Trainee',
                'type' => OpportunityType::Job,
                'description' => '12-month rotational graduate programme across commercial, tech, and operations tracks. Ideal for fresh graduates ready to build a telecom career.',
                'url' => 'https://example.com/opportunities/safaricom-graduate',
                'deadline' => now()->addMonth()->toDateString(),
                'sort_order' => 1,
                'is_verified_partner' => true,
                'partner_name' => 'Safaricom Ethiopia',
            ],
            [
                'slug' => 'addis-software-house-junior-dev',
                'title' => 'Junior Developer — Addis Software House',
                'type' => OpportunityType::Job,
                'description' => 'Entry-level full-stack role for students or recent grads who have shipped class or personal projects. Mentorship and remote-friendly hybrid schedule.',
                'url' => 'https://example.com/opportunities/addis-junior-dev',
                'deadline' => null,
                'sort_order' => 2,
            ],
            [
                'slug' => 'women-in-stem-peer-mentorship',
                'title' => 'Women in STEM Peer Mentorship',
                'type' => OpportunityType::Mentorship,
                'description' => 'Pair with a senior student or early-career engineer for monthly check-ins on courses, internships, and career planning. Open to women in Natural stream.',
                'url' => 'https://example.com/opportunities/women-stem-mentorship',
                'deadline' => now()->addMonths(4)->toDateString(),
                'sort_order' => 1,
            ],
            [
                'slug' => 'noviq-alumni-career-circles',
                'title' => 'NOViQ Alumni Career Circles',
                'type' => OpportunityType::Mentorship,
                'description' => 'Small-group mentorship with alumni working in tech, finance, and public service. Biweekly virtual sessions plus office hours for CV and interview prep.',
                'url' => 'https://example.com/opportunities/noviq-career-circles',
                'deadline' => null,
                'sort_order' => 2,
            ],
        ];

        foreach ($posts as $post) {
            Opportunity::query()->updateOrCreate(
                ['slug' => $post['slug']],
                [
                    ...$post,
                    'is_published' => true,
                ],
            );
        }
    }
}
