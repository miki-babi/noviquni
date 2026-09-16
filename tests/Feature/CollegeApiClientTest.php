<?php

use App\Enums\CollegeResourceKind;
use App\Services\College\CollegeApiClient;
use App\Services\College\CollegeApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config([
        'services.noviq_college.base_url' => 'https://api.noviq.et/api/public/college',
        'services.noviq_college.api_key' => 'test-college-api-key',
        'services.noviq_college.cache_ttl' => 300,
    ]);
});

it('lists courses with the API key header', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.noviq.et/api/public/college/courses' => Http::response([
            'success' => true,
            'data' => [
                [
                    '_id' => 'course-1',
                    'courseCode' => 'ANTH101',
                    'name' => 'Anthropology',
                    'stream' => 'social-science',
                    'totalModules' => 1,
                ],
            ],
        ]),
    ]);

    $courses = app(CollegeApiClient::class)->listCourses();

    expect($courses)->toHaveCount(1)
        ->and($courses[0]['_id'])->toBe('course-1')
        ->and($courses[0]['courseCode'])->toBe('ANTH101');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.noviq.et/api/public/college/courses'
            && $request->hasHeader('X-College-API-Key', 'test-college-api-key')
            && $request->hasHeader('Authorization', 'Bearer test-college-api-key');
    });
});

it('lists modules for a course', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.noviq.et/api/public/college/courses/course-1/modules' => Http::response([
            'success' => true,
            'data' => [
                [
                    '_id' => 'module-1',
                    'name' => 'Anthropology',
                    'moduleNumber' => 1,
                    'totalUnits' => 1,
                    'totalSections' => 8,
                ],
            ],
        ]),
    ]);

    $modules = app(CollegeApiClient::class)->listModules('course-1');

    expect($modules)->toHaveCount(1)
        ->and($modules[0]['_id'])->toBe('module-1');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.noviq.et/api/public/college/courses/course-1/modules'
            && $request->hasHeader('X-College-API-Key', 'test-college-api-key')
            && $request->hasHeader('Authorization', 'Bearer test-college-api-key');
    });
});

it('fetches module curriculum and unwraps the data envelope', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.noviq.et/api/public/college/modules/module-1/curriculum' => Http::response([
            'success' => true,
            'data' => [
                'module' => [
                    '_id' => 'module-1',
                    'name' => 'Anthropology',
                ],
                'units' => [
                    [
                        '_id' => 'unit-1',
                        'unitNumber' => 1,
                        'name' => 'Introduction',
                        'sections' => [
                            [
                                '_id' => 'section-1',
                                'sectionNumber' => 1,
                                'name' => 'Definition',
                                'vectorEmbedded' => true,
                            ],
                        ],
                    ],
                ],
                'standaloneSections' => [],
            ],
        ]),
    ]);

    $curriculum = app(CollegeApiClient::class)->curriculum('module-1');

    expect($curriculum['module']['name'])->toBe('Anthropology')
        ->and($curriculum['units'][0]['sections'][0]['_id'])->toBe('section-1')
        ->and($curriculum['standaloneSections'])->toBe([]);
});

it('throws when curriculum response is unsuccessful', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.noviq.et/api/public/college/modules/module-1/curriculum' => Http::response([
            'success' => false,
            'error' => 'Module not found',
        ], 200),
    ]);

    app(CollegeApiClient::class)->curriculum('module-1');
})->throws(CollegeApiException::class);

it('generates a quiz for a section scope', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.noviq.et/api/public/college/generate/quiz' => Http::response([
            'success' => true,
            'fromCache' => false,
            'generatedAt' => '2026-09-15T12:46:32.167Z',
            'data' => [
                'questions' => [
                    [
                        'question' => 'What is anthropology?',
                        'options' => ['A', 'B', 'C', 'D'],
                        'answerIndex' => 1,
                        'explanation' => 'Because.',
                        'difficulty' => 'easy',
                    ],
                ],
            ],
        ]),
    ]);

    $response = app(CollegeApiClient::class)->generate(
        CollegeResourceKind::Quiz,
        ['sectionId' => 'section-1'],
        ['questionCount' => 3, 'refresh' => false],
    );

    expect($response['success'])->toBeTrue()
        ->and($response['data']['questions'])->toHaveCount(1);

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.noviq.et/api/public/college/generate/quiz'
            && $request['sectionId'] === 'section-1'
            && $request['questionCount'] === 3
            && $request->hasHeader('X-College-API-Key', 'test-college-api-key')
            && $request->hasHeader('Authorization', 'Bearer test-college-api-key');
    });
});

it('throws when generate response is unsuccessful', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.noviq.et/api/public/college/generate/notes' => Http::response([
            'success' => false,
            'message' => 'Section not embedded',
        ], 200),
    ]);

    app(CollegeApiClient::class)->generate(
        CollegeResourceKind::Notes,
        ['sectionId' => 'missing'],
    );
})->throws(CollegeApiException::class);

it('throws when the API returns an error status', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.noviq.et/api/public/college/courses' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    app(CollegeApiClient::class)->listCourses();
})->throws(CollegeApiException::class);
