<?php

namespace App\Services\College;

use App\Enums\CollegeResourceKind;
use App\Enums\ResourceType;
use Illuminate\Support\Str;

class LearningResourceMapper
{
    /**
     * @param  array<string, mixed>  $response
     * @param  array{sectionId?: string, unitId?: string, moduleId?: string}  $scope
     * @param  array{scope_label?: string|null}  $context
     * @return array{
     *     title: string,
     *     slug: string,
     *     description: string|null,
     *     topics: list<string>,
     *     type: ResourceType,
     *     generation_kind: CollegeResourceKind,
     *     content: array<string, mixed>
     * }
     */
    public function map(CollegeResourceKind $kind, array $response, array $scope, array $context = []): array
    {
        $payload = $response['data'] ?? [];
        if (! is_array($payload)) {
            $payload = [];
        }

        $scopeType = $this->resolveScopeType($scope);
        $scopeId = $scope[$scopeType] ?? reset($scope) ?: null;
        $scopeLabel = $context['scope_label'] ?? null;

        $title = $this->resolveTitle($kind, $payload, $scopeLabel);
        $description = $this->resolveDescription($kind, $payload);
        $topics = $this->resolveTopics($kind, $payload);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'description' => $description,
            'topics' => $topics,
            'type' => $kind->resourceType(),
            'generation_kind' => $kind,
            'content' => [
                'kind' => $kind->value,
                'scope_type' => match ($scopeType) {
                    'sectionId' => 'section',
                    'unitId' => 'unit',
                    default => 'module',
                },
                'scope_id' => $scopeId,
                'from_cache' => (bool) ($response['fromCache'] ?? false),
                'generated_at' => $response['generatedAt'] ?? now()->toIso8601String(),
                'payload' => $payload,
            ],
        ];
    }

    /**
     * @param  array{sectionId?: string, unitId?: string, moduleId?: string}  $scope
     */
    protected function resolveScopeType(array $scope): string
    {
        return match (true) {
            isset($scope['sectionId']) => 'sectionId',
            isset($scope['unitId']) => 'unitId',
            default => 'moduleId',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveTitle(CollegeResourceKind $kind, array $payload, ?string $scopeLabel): string
    {
        if (filled($payload['title'] ?? null)) {
            return (string) $payload['title'];
        }

        $label = filled($scopeLabel) ? $scopeLabel : $kind->label();

        return $kind->label().': '.$label;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveDescription(CollegeResourceKind $kind, array $payload): ?string
    {
        if (filled($payload['summary'] ?? null)) {
            return (string) $payload['summary'];
        }

        return match ($kind) {
            CollegeResourceKind::Quiz => $this->countLabel($payload['questions'] ?? null, 'question'),
            CollegeResourceKind::Exam => 'Formal exam with Part A and Part B.',
            CollegeResourceKind::Flashcards => $this->countLabel($payload['cards'] ?? $payload, 'flashcard'),
            CollegeResourceKind::Notes => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    protected function resolveTopics(CollegeResourceKind $kind, array $payload): array
    {
        return match ($kind) {
            CollegeResourceKind::Notes => collect($payload['corePrinciples'] ?? [])
                ->filter(fn ($item) => is_string($item) && filled($item))
                ->take(8)
                ->values()
                ->all(),
            CollegeResourceKind::Quiz => collect($payload['questions'] ?? [])
                ->pluck('difficulty')
                ->filter()
                ->unique()
                ->map(fn ($difficulty) => ucfirst((string) $difficulty))
                ->values()
                ->all(),
            CollegeResourceKind::Flashcards => collect($payload['cards'] ?? (array_is_list($payload) ? $payload : []))
                ->pluck('category')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            CollegeResourceKind::Exam => ['Part A', 'Part B'],
        };
    }

    protected function countLabel(mixed $items, string $singular): ?string
    {
        if (! is_array($items)) {
            return null;
        }

        $count = count($items);

        return $count.' '.$singular.($count === 1 ? '' : 's');
    }
}
