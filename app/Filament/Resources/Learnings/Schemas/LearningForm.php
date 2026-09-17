<?php

namespace App\Filament\Resources\Learnings\Schemas;

use App\Enums\CollegeResourceKind;
use App\Enums\ResourceType;
use App\Filament\Forms\Components\SeoFields;
use App\Models\Course;
use App\Models\LearningResource;
use App\Services\College\CollegeApiClient;
use App\Services\College\CollegeApiException;
use App\Services\College\CollegeCurriculumOptions;
use App\Services\College\LearningResourceMapper;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Throwable;

class LearningForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? '')))
                    ->columnSpanFull(),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->columnSpanFull(),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                TagsInput::make('topics')
                    ->helperText('Topics covered, shown as a list on the public page.')
                    ->columnSpanFull(),
                Select::make('type')
                    ->options(collect(ResourceType::creatableCases())->mapWithKeys(
                        fn (ResourceType $type) => [$type->value => $type->label()]
                    )->all())
                    ->required()
                    ->live()
                    ->native(false)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if ($state === ResourceType::Flashcards->value) {
                            $set('is_premium', true);
                            $set('is_bait', false);
                        }
                    }),
                Select::make('generation_kind')
                    ->label('Generation kind')
                    ->options(CollegeResourceKind::class)
                    ->disabled()
                    ->dehydrated(),
                Select::make('stream_id')
                    ->relationship('stream', 'name')
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('course_id', null))
                    ->searchable()
                    ->preload()
                    ->helperText('Optional. Leave blank to browse all courses; stream is set from the selected course.'),
                Select::make('course_id')
                    ->label('Course')
                    ->options(fn (Get $get): array => Course::query()
                        ->when($get('stream_id'), fn ($q, $streamId) => $q->where('stream_id', $streamId))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->live()
                    ->searchable()
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        if (! filled($state)) {
                            return;
                        }

                        $streamId = Course::query()->whereKey($state)->value('stream_id');
                        if ($streamId !== null) {
                            $set('stream_id', $streamId);
                        }

                        $set('module_id', null);
                    }),
                Select::make('module_id')
                    ->label('Parent module')
                    ->options(fn (Get $get): array => filled($get('course_id'))
                        ? LearningResource::query()
                            ->where('course_id', $get('course_id'))
                            ->where('type', ResourceType::Module)
                            ->orderBy('sort_order')
                            ->orderBy('title')
                            ->pluck('title', 'id')
                            ->all()
                        : [])
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('type') !== ResourceType::Module->value)
                    ->required(fn (Get $get): bool => $get('type') === ResourceType::Flashcards->value)
                    ->helperText('Required for flashcards. Links notes, worksheets, and quizzes to the module spine.'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->integer()
                    ->default(0)
                    ->required(),
                Select::make('university_id')
                    ->relationship('university', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('semester_id')
                    ->relationship('semester', 'name')
                    ->searchable()
                    ->preload(),
                Toggle::make('is_premium')
                    ->default(false)
                    ->live()
                    ->disabled(fn (Get $get): bool => $get('type') === ResourceType::Flashcards->value)
                    ->dehydrated(),
                Toggle::make('is_bait')
                    ->label('Free Week-1 bait')
                    ->default(false)
                    ->helperText('Free students only open bait resources (notes / sample quiz / sample exam).')
                    ->disabled(fn (Get $get): bool => $get('type') === ResourceType::Flashcards->value
                        || (bool) $get('is_premium')),
                Toggle::make('is_published')->default(false),
                Textarea::make('content_preview')
                    ->label('Generated content')
                    ->rows(12)
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Textarea $component, mixed $state, ?LearningResource $record): void {
                        $content = $record?->content;
                        $component->state(
                            is_array($content)
                                ? (json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '')
                                : ''
                        );
                    })
                    ->columnSpanFull(),
                ...SeoFields::make(),
            ]);
    }

    /**
     * @return list<Step>
     */
    public static function createSteps(): array
    {
        return [
            Step::make('Courses')
                ->description('1. Fetch all Noviq college courses, then modules for the selected course.')
                ->schema([
                    Select::make('api_course_id')
                        ->label('College course')
                        ->options(fn (): array => app(CollegeCurriculumOptions::class)->courseOptions())
                        ->required()
                        ->live()
                        ->searchable()
                        ->helperText('GET /courses — all subjects from the Noviq college API.')
                        ->dehydrated(false)
                        ->afterStateUpdated(function (Set $set): void {
                            $set('api_module_id', null);
                            $set('api_unit_id', null);
                            $set('api_section_id', null);
                            $set('api_scope_type', 'sectionId');
                        }),
                    Select::make('api_module_id')
                        ->label('Module')
                        ->options(fn (Get $get): array => filled($get('api_course_id'))
                            ? app(CollegeCurriculumOptions::class)->moduleOptions((string) $get('api_course_id'))
                            : [])
                        ->required()
                        ->live()
                        ->searchable()
                        ->helperText('GET /courses/{courseId}/modules')
                        ->dehydrated(false)
                        ->afterStateUpdated(function (Set $set): void {
                            $set('api_unit_id', null);
                            $set('api_section_id', null);
                        }),
                ]),
            Step::make('Curriculum')
                ->description('2. Load the syllabus tree and pick a section (or unit / whole module).')
                ->schema([
                    Select::make('api_scope_type')
                        ->label('Generate for')
                        ->options([
                            'sectionId' => 'Section (recommended)',
                            'unitId' => 'Whole unit',
                            'moduleId' => 'Whole module',
                        ])
                        ->required()
                        ->live()
                        ->default('sectionId')
                        ->dehydrated(false)
                        ->afterStateUpdated(function (Set $set): void {
                            $set('api_unit_id', null);
                            $set('api_section_id', null);
                        }),
                    Select::make('api_unit_id')
                        ->label('Unit')
                        ->options(fn (Get $get): array => filled($get('api_module_id'))
                            ? app(CollegeCurriculumOptions::class)->unitOptions((string) $get('api_module_id'))
                            : [])
                        ->required(fn (Get $get): bool => $get('api_scope_type') === 'unitId')
                        ->visible(fn (Get $get): bool => in_array($get('api_scope_type'), ['sectionId', 'unitId'], true))
                        ->live()
                        ->searchable()
                        ->helperText('GET /modules/{moduleId}/curriculum — units list. Optional when generating a section.')
                        ->dehydrated(false)
                        ->afterStateUpdated(fn (Set $set) => $set('api_section_id', null)),
                    Select::make('api_section_id')
                        ->label('Section')
                        ->options(fn (Get $get): array => filled($get('api_module_id'))
                            ? app(CollegeCurriculumOptions::class)->sectionOptions(
                                (string) $get('api_module_id'),
                                $get('api_unit_id') ? (string) $get('api_unit_id') : null,
                            )
                            : [])
                        ->required(fn (Get $get): bool => $get('api_scope_type') === 'sectionId')
                        ->visible(fn (Get $get): bool => $get('api_scope_type') === 'sectionId')
                        ->searchable()
                        ->helperText('Pass this section _id to generate quiz, notes, exam, or flashcards.')
                        ->dehydrated(false),
                ]),
            Step::make('Generate')
                ->description('3. Create an activity from the selected sectionId / unitId / moduleId.')
                ->schema([
                    Select::make('generation_kind')
                        ->label('Resource kind')
                        ->options([
                            CollegeResourceKind::Quiz->value => 'Quiz — POST /generate/quiz',
                            CollegeResourceKind::Notes->value => 'Short notes — POST /generate/notes',
                            CollegeResourceKind::Exam->value => 'Full exam — POST /generate/exam',
                            CollegeResourceKind::Flashcards->value => 'Flashcards — POST /generate/flashcards',
                        ])
                        ->required()
                        ->live()
                        ->native(false),
                    TextInput::make('question_count')
                        ->label('Question count')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->default(5)
                        ->required()
                        ->visible(fn (Get $get): bool => $get('generation_kind') === CollegeResourceKind::Quiz->value)
                        ->dehydrated(false),
                    TextInput::make('part_b_count')
                        ->label('Part B question count')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(10)
                        ->default(3)
                        ->required()
                        ->visible(fn (Get $get): bool => $get('generation_kind') === CollegeResourceKind::Exam->value)
                        ->dehydrated(false),
                    TextInput::make('card_count')
                        ->label('Card count')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(15)
                        ->required()
                        ->visible(fn (Get $get): bool => $get('generation_kind') === CollegeResourceKind::Flashcards->value)
                        ->dehydrated(false),
                    Toggle::make('refresh')
                        ->label('Force refresh (ignore API cache)')
                        ->default(false)
                        ->dehydrated(false),
                ])
                ->afterValidation(function (Get $get, Set $set): void {
                    static::generateAndFill($get, $set);
                }),
            Step::make('Publish')
                ->description('4. Attach the generated resource to a local course and review details.')
                ->schema([
                    Select::make('stream_id')
                        ->relationship('stream', 'name')
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('course_id', null))
                        ->searchable()
                        ->preload()
                        ->helperText('Optional. Leave blank to browse all local courses.'),
                    Select::make('course_id')
                        ->label('Local course')
                        ->options(fn (Get $get): array => Course::query()
                            ->when($get('stream_id'), fn ($q, $streamId) => $q->where('stream_id', $streamId))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required()
                        ->live()
                        ->searchable()
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if (! filled($state)) {
                                return;
                            }

                            $streamId = Course::query()->whereKey($state)->value('stream_id');
                            if ($streamId !== null) {
                                $set('stream_id', $streamId);
                            }
                        }),
                    Select::make('university_id')
                        ->relationship('university', 'name')
                        ->searchable()
                        ->preload(),
                    Select::make('semester_id')
                        ->relationship('semester', 'name')
                        ->searchable()
                        ->preload(),
                    Select::make('module_id')
                        ->label('Parent module')
                        ->options(fn (Get $get): array => filled($get('course_id'))
                            ? LearningResource::query()
                                ->where('course_id', $get('course_id'))
                                ->where('type', ResourceType::Module)
                                ->orderBy('sort_order')
                                ->orderBy('title')
                                ->pluck('title', 'id')
                                ->all()
                            : [])
                        ->searchable()
                        ->required(fn (Get $get): bool => $get('generation_kind') === CollegeResourceKind::Flashcards->value
                            || $get('type') === ResourceType::Flashcards->value)
                        ->helperText('Required for flashcards. Attach notes/quiz/exam to a module when possible.'),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->integer()
                        ->default(0)
                        ->required(),
                    Toggle::make('is_premium')
                        ->default(fn (Get $get): bool => $get('generation_kind') === CollegeResourceKind::Flashcards->value)
                        ->live()
                        ->disabled(fn (Get $get): bool => $get('generation_kind') === CollegeResourceKind::Flashcards->value)
                        ->dehydrated(),
                    Toggle::make('is_bait')
                        ->label('Free Week-1 bait')
                        ->default(false)
                        ->disabled(fn (Get $get): bool => $get('generation_kind') === CollegeResourceKind::Flashcards->value
                            || (bool) $get('is_premium')),
                    Toggle::make('is_published')->default(false),
                    TextInput::make('title')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? '')))
                        ->columnSpanFull(),
                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->columnSpanFull(),
                    Textarea::make('description')->rows(3)->columnSpanFull(),
                    TagsInput::make('topics')->columnSpanFull(),
                    Select::make('type')
                        ->options(ResourceType::class)
                        ->disabled()
                        ->dehydrated()
                        ->required(),
                    Hidden::make('content')
                        ->required()
                        ->dehydrateStateUsing(fn ($state) => is_string($state)
                            ? (json_decode($state, true) ?: null)
                            : $state),
                    Hidden::make('generation_kind'),
                    Textarea::make('content_preview')
                        ->label('Generated content preview')
                        ->rows(10)
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    ...SeoFields::make(),
                ]),
        ];
    }

    protected static function generateAndFill(Get $get, Set $set): void
    {
        $kindValue = $get('generation_kind');
        $kind = $kindValue instanceof CollegeResourceKind
            ? $kindValue
            : CollegeResourceKind::tryFrom((string) $kindValue);

        if ($kind === null) {
            Notification::make()
                ->title('Choose a resource kind')
                ->danger()
                ->send();

            throw new CollegeApiException('Generation kind is required.');
        }

        $moduleId = (string) $get('api_module_id');
        $scopeType = (string) $get('api_scope_type');
        $scope = match ($scopeType) {
            'sectionId' => ['sectionId' => (string) $get('api_section_id')],
            'unitId' => ['unitId' => (string) $get('api_unit_id')],
            default => ['moduleId' => $moduleId],
        };

        $options = ['refresh' => (bool) $get('refresh')];

        if ($kind === CollegeResourceKind::Quiz) {
            $options['questionCount'] = (int) ($get('question_count') ?: 5);
        }

        if ($kind === CollegeResourceKind::Exam) {
            $options['partBCount'] = (int) ($get('part_b_count') ?: 3);
        }

        if ($kind === CollegeResourceKind::Flashcards) {
            $options['cardCount'] = (int) ($get('card_count') ?: 15);
        }

        try {
            $response = app(CollegeApiClient::class)->generate($kind, $scope, $options);
            $scopeId = (string) reset($scope);
            $scopeLabel = app(CollegeCurriculumOptions::class)->labelFor($moduleId, $scopeType, $scopeId);
            $mapped = app(LearningResourceMapper::class)->map($kind, $response, $scope, [
                'scope_label' => $scopeLabel,
            ]);
        } catch (CollegeApiException $exception) {
            Notification::make()
                ->title('Generation failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            throw $exception;
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Generation failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            throw new CollegeApiException($exception->getMessage(), previous: $exception);
        }

        $encoded = json_encode($mapped['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        $set('title', $mapped['title']);
        $set('slug', $mapped['slug']);
        $set('description', $mapped['description']);
        $set('topics', $mapped['topics']);
        $set('type', $mapped['type']->value);
        $set('generation_kind', $mapped['generation_kind']->value);
        $set('content', $encoded);
        $set('content_preview', $encoded);

        if ($kind === CollegeResourceKind::Flashcards) {
            $set('is_premium', true);
            $set('is_bait', false);
        }
    }
}
