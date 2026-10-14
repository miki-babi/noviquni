<?php

namespace App\Filament\Resources\Learnings\Schemas;

use App\Enums\CollegeResourceKind;
use App\Enums\ResourceType;
use App\Filament\Forms\Components\SeoFields;
use App\Filament\Resources\TelegramFileAssets\TelegramFileAssetResource;
use App\Models\Course;
use App\Models\LearningResource;
use App\Models\TelegramFileAsset;
use App\Support\LearningResourceFiles;
use App\Support\StudyRichText;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Throwable;

class LearningForm
{
    /**
     * Clear library files when the course changes from a previously selected
     * value (not on initial prefill).
     */
    protected static function onCourseUpdated(): Closure
    {
        return function (Set $set, mixed $state, mixed $old): void {
            if ((string) ($state ?? '') === (string) ($old ?? '')) {
                return;
            }

            if (filled($old)) {
                $set('existing_files', null);
                $set('module_id', null);
            }
        };
    }

    public static function isInteractiveMcqType(mixed $type): bool
    {
        $resourceType = $type instanceof ResourceType
            ? $type
            : ResourceType::tryFrom(is_string($type) ? $type : '');

        return in_array($resourceType, [ResourceType::Quiz, ResourceType::PracticeExams], true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrateInteractiveFields(array $data): array
    {
        if (! static::isInteractiveMcqType($data['type'] ?? null)) {
            return $data;
        }

        $content = $data['content'] ?? null;

        if (! is_array($content)) {
            $data['quiz_questions'] = $data['quiz_questions'] ?? [];
            $data['exam_title'] = $data['exam_title'] ?? null;
            $data['exam_instructions'] = $data['exam_instructions'] ?? null;
            $data['exam_time_limit_minutes'] = $data['exam_time_limit_minutes'] ?? null;

            return $data;
        }

        $payload = is_array($content['payload'] ?? null) ? $content['payload'] : [];
        $type = ResourceType::tryFrom((string) ($data['type'] ?? ''));

        if ($type === ResourceType::PracticeExams) {
            $normalized = (new LearningResource)->normalizeExam($payload);
            $data['exam_title'] = $normalized['title'];
            $data['exam_instructions'] = $normalized['instructions'];
            $data['exam_time_limit_minutes'] = $normalized['timeLimitMinutes'];
            $data['quiz_questions'] = static::questionsForRepeater($normalized['questions']);
        } else {
            $questions = is_array($payload['questions'] ?? null) ? array_values($payload['questions']) : [];
            $data['quiz_questions'] = static::questionsForRepeater($questions);
            $data['exam_title'] = null;
            $data['exam_instructions'] = null;
            $data['exam_time_limit_minutes'] = null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function dehydrateInteractiveFields(array $data): array
    {
        $type = ResourceType::tryFrom(is_string($data['type'] ?? null) ? $data['type'] : '');

        if (! static::isInteractiveMcqType($type)) {
            unset(
                $data['quiz_questions'],
                $data['exam_title'],
                $data['exam_instructions'],
                $data['exam_time_limit_minutes'],
            );

            return $data;
        }

        $kind = $type === ResourceType::PracticeExams
            ? CollegeResourceKind::Exam
            : CollegeResourceKind::Quiz;

        $questions = static::questionsFromRepeater($data['quiz_questions'] ?? []);

        $payload = [
            'questions' => $questions,
        ];

        if ($type === ResourceType::PracticeExams) {
            if (filled($data['exam_title'] ?? null)) {
                $payload['title'] = (string) $data['exam_title'];
            }

            if (filled($data['exam_instructions'] ?? null)) {
                $payload['instructions'] = (string) $data['exam_instructions'];
            }

            $minutes = $data['exam_time_limit_minutes'] ?? null;

            if (is_numeric($minutes) && (int) $minutes > 0) {
                $payload['timeLimitMinutes'] = (int) $minutes;
            }
        }

        $existing = is_array($data['content'] ?? null) ? $data['content'] : [];

        $data['generation_kind'] = $kind;
        $data['content'] = [
            ...$existing,
            'kind' => $kind->value,
            'payload' => $payload,
        ];

        unset(
            $data['quiz_questions'],
            $data['exam_title'],
            $data['exam_instructions'],
            $data['exam_time_limit_minutes'],
        );

        return $data;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return list<array<string, mixed>>
     */
    protected static function questionsForRepeater(array $questions): array
    {
        $items = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }

            $options = is_array($question['options'] ?? null)
                ? array_values($question['options'])
                : [];

            $items[] = [
                'question' => StudyRichText::forEditor($question['question'] ?? ''),
                'options' => array_map(
                    function (mixed $option): array {
                        $body = is_array($option) && array_key_exists('body', $option)
                            ? $option['body']
                            : $option;

                        return ['body' => StudyRichText::forEditor($body)];
                    },
                    $options,
                ),
                'answerIndex' => (int) ($question['answerIndex'] ?? 0),
                'explanation' => StudyRichText::forEditor($question['explanation'] ?? null),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function questionsFromRepeater(mixed $rawQuestions): array
    {
        if (! is_array($rawQuestions)) {
            return [];
        }

        $questions = [];

        foreach (array_values($rawQuestions) as $question) {
            if (! is_array($question)) {
                continue;
            }

            $rawOptions = is_array($question['options'] ?? null)
                ? array_values($question['options'])
                : [];

            $options = [];

            foreach ($rawOptions as $option) {
                $body = is_array($option) && array_key_exists('body', $option)
                    ? $option['body']
                    : $option;

                if (StudyRichText::isEmpty($body)) {
                    continue;
                }

                $options[] = $body;
            }

            if (StudyRichText::isEmpty($question['question'] ?? null) || count($options) < 2) {
                continue;
            }

            $answerIndex = (int) ($question['answerIndex'] ?? 0);

            if ($answerIndex < 0 || $answerIndex >= count($options)) {
                $answerIndex = 0;
            }

            $item = [
                'question' => $question['question'],
                'options' => $options,
                'answerIndex' => $answerIndex,
            ];

            if (! StudyRichText::isEmpty($question['explanation'] ?? null)) {
                $item['explanation'] = $question['explanation'];
            }

            $questions[] = $item;
        }

        return $questions;
    }

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
                    ->native(false),
                Select::make('generation_kind')
                    ->label('Generation kind')
                    ->options(CollegeResourceKind::class)
                    ->disabled()
                    ->dehydrated()
                    ->visible(fn (?LearningResource $record): bool => filled($record?->generation_kind)),
                Select::make('course_id')
                    ->label('Course')
                    ->options(fn (): array => Course::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required()
                    ->live()
                    ->searchable()
                    ->afterStateUpdated(static::onCourseUpdated()),
                Select::make('module_id')
                    ->label('Parent resource')
                    ->options(fn (Get $get): array => filled($get('course_id'))
                        ? LearningResource::query()
                            ->where('course_id', $get('course_id'))
                            ->orderBy('sort_order')
                            ->orderBy('title')
                            ->pluck('title', 'id')
                            ->all()
                        : [])
                    ->searchable()
                    ->helperText('Optional. Groups this resource under another catalog item.'),
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
                    ->dehydrated()
                    ->helperText('Free students can open any published resource that is not premium.'),
                Toggle::make('is_published')->default(false),
                ...static::fileFields(required: false),
                ...static::quizExamFields(),
                static::contentJsonField(required: false),
                ...SeoFields::make(),
            ]);
    }

    /**
     * @return list<Step>
     */
    public static function createSteps(): array
    {
        return [
            Step::make('Details')
                ->description('Enter resource details and attach study files (Telegram vault preferred for fast bot delivery).')
                ->schema([
                    Select::make('type')
                        ->options(collect(ResourceType::creatableCases())->mapWithKeys(
                            fn (ResourceType $type) => [$type->value => $type->label()]
                        )->all())
                        ->required()
                        ->live()
                        ->native(false),
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
                    Select::make('course_id')
                        ->label('Course')
                        ->options(fn (): array => Course::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required()
                        ->live()
                        ->searchable()
                        ->afterStateUpdated(static::onCourseUpdated()),
                    Select::make('module_id')
                        ->label('Parent resource')
                        ->options(fn (Get $get): array => filled($get('course_id'))
                            ? LearningResource::query()
                                ->where('course_id', $get('course_id'))
                                ->orderBy('sort_order')
                                ->orderBy('title')
                                ->pluck('title', 'id')
                                ->all()
                            : [])
                        ->searchable()
                        ->helperText('Optional. Groups this resource under another catalog item.'),
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
                        ->dehydrated()
                        ->helperText('Free students can open any published resource that is not premium.'),
                    Toggle::make('is_published')->default(false),
                    ...static::fileFields(required: false),
                    ...static::quizExamFields(),
                    ...SeoFields::make(),
                ]),
        ];
    }

    /**
     * @return list<Component|Section>
     */
    protected static function quizExamFields(): array
    {
        return [
            Section::make('Questions')
                ->description('Multiple-choice questions with rich text, images, and an optional explanation for the correct answer.')
                ->visible(fn (Get $get): bool => static::isInteractiveMcqType($get('type')))
                ->schema([
                    TextInput::make('exam_title')
                        ->label('Exam title')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => ($get('type') ?? null) === ResourceType::PracticeExams->value)
                        ->dehydrated(fn (Get $get): bool => ($get('type') ?? null) === ResourceType::PracticeExams->value),
                    Textarea::make('exam_instructions')
                        ->label('Instructions')
                        ->rows(2)
                        ->visible(fn (Get $get): bool => ($get('type') ?? null) === ResourceType::PracticeExams->value)
                        ->dehydrated(fn (Get $get): bool => ($get('type') ?? null) === ResourceType::PracticeExams->value),
                    TextInput::make('exam_time_limit_minutes')
                        ->label('Time limit (minutes)')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(600)
                        ->helperText('Leave empty for no countdown. When set, the Mini App timer auto-submits when time runs out.')
                        ->visible(fn (Get $get): bool => ($get('type') ?? null) === ResourceType::PracticeExams->value)
                        ->dehydrated(fn (Get $get): bool => ($get('type') ?? null) === ResourceType::PracticeExams->value),
                    Repeater::make('quiz_questions')
                        ->label('Questions')
                        ->schema([
                            static::quizRichEditor('question')
                                ->label('Question')
                                ->required(),
                            Repeater::make('options')
                                ->label('Choices')
                                ->schema([
                                    static::quizRichEditor('body')
                                        ->label('Choice')
                                        ->required(),
                                ])
                                ->minItems(2)
                                ->maxItems(6)
                                ->defaultItems(4)
                                ->required()
                                ->live()
                                ->addActionLabel('Add choice')
                                ->reorderable()
                                ->columnSpanFull(),
                            Select::make('answerIndex')
                                ->label('Correct answer')
                                ->options(function (Get $get): array {
                                    $options = is_array($get('options') ?? null)
                                        ? array_values($get('options'))
                                        : [];

                                    $choices = [];

                                    foreach ($options as $index => $option) {
                                        $letter = chr(65 + $index);
                                        $body = is_array($option) ? ($option['body'] ?? null) : $option;

                                        try {
                                            $plain = StudyRichText::toPlain($body);
                                        } catch (Throwable) {
                                            $plain = is_string($body) ? strip_tags($body) : '';
                                        }

                                        $choices[$index] = filled($plain)
                                            ? $letter.' — '.Str::limit($plain, 48)
                                            : $letter;
                                    }

                                    return $choices !== [] ? $choices : [
                                        0 => 'A',
                                        1 => 'B',
                                    ];
                                })
                                ->required()
                                ->native(false),
                            static::quizRichEditor('explanation')
                                ->label('Explanation')
                                ->helperText('Optional rationale shown after the student submits.'),
                        ])
                        ->minItems(0)
                        ->defaultItems(0)
                        ->collapsible()
                        ->addActionLabel('Add question')
                        ->reorderable()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    protected static function quizRichEditor(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->json()
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'link'],
                ['bulletList', 'orderedList'],
                ['attachFiles'],
                ['undo', 'redo'],
            ])
            ->fileAttachmentsDisk(StudyRichText::AttachmentsDisk)
            ->fileAttachmentsDirectory(fn (Get $get): string => StudyRichText::attachmentsDirectory(
                $get('../../course_id') ?? $get('../course_id') ?? $get('course_id'),
            ))
            ->fileAttachmentsVisibility('public')
            ->preventFileAttachmentPathTampering(fn (?LearningResource $record): bool => $record !== null)
            ->afterStateHydrated(function (RichEditor $component, mixed $state): void {
                if (is_array($state) && ($state['type'] ?? null) === 'doc') {
                    return;
                }

                $component->state(StudyRichText::forEditor($state));
            })
            ->columnSpanFull();
    }

    protected static function contentJsonField(bool $required = false): CodeEditor|Section
    {
        $field = CodeEditor::make('content')
            ->label('Content JSON')
            ->language(Language::Json)
            ->helperText('Optional interactive payload (notes, flashcards, etc.). For quizzes and practice exams, prefer the Questions builder above.')
            ->columnSpanFull()
            ->dehydrated(fn (Get $get): bool => ! static::isInteractiveMcqType($get('type')))
            ->visible(function (Get $get, ?LearningResource $record): bool {
                if (static::isInteractiveMcqType($get('type'))) {
                    return filled($record?->content) || filled($record?->generation_kind);
                }

                return filled($record?->content) || filled($record?->generation_kind);
            })
            ->formatStateUsing(function (mixed $state): string {
                if (is_array($state)) {
                    return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '';
                }

                if (is_string($state)) {
                    return $state;
                }

                return '';
            })
            ->dehydrateStateUsing(function (mixed $state): ?array {
                if (! is_string($state) || blank($state)) {
                    return null;
                }

                $decoded = json_decode($state, true);

                return is_array($decoded) ? $decoded : null;
            })
            ->rules([
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || blank($value)) {
                        return;
                    }

                    json_decode($value, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $fail('Content must be valid JSON.');
                    }
                },
            ]);

        if ($required) {
            $field->required();
        }

        return Section::make('Advanced')
            ->description('Raw content JSON for College-generated or legacy payloads. Quizzes and practice exams are saved from the Questions builder.')
            ->collapsed()
            ->visible(fn (?LearningResource $record): bool => filled($record?->content) || filled($record?->generation_kind))
            ->schema([$field])
            ->columnSpanFull();
    }

    /**
     * @return list<Component|Section>
     */
    protected static function fileFields(bool $required = false): array
    {
        return [
            Section::make('Study files')
                ->description('Prefer Telegram for student delivery — the bot sends by file_id with no re-upload. Disk files remain available as a fallback library.')
                ->schema([
                    ToggleButtons::make('file_source')
                        ->label('File source')
                        ->options([
                            'telegram' => 'Telegram vault',
                            'upload' => 'Upload to disk',
                            'existing' => 'Course library',
                        ])
                        ->default(fn (): string => TelegramFileAsset::vaultCount() > 0 ? 'telegram' : 'upload')
                        ->live()
                        ->grouped()
                        ->dehydrated()
                        ->afterStateHydrated(function (ToggleButtons $component, mixed $state, ?LearningResource $record): void {
                            if ($record === null) {
                                return;
                            }

                            if (filled($record->telegram_files)) {
                                $component->state('telegram');
                            } elseif (filled($record->files)) {
                                $component->state('existing');
                            }
                        })
                        ->helperText(fn (): string => TelegramFileAsset::vaultCount() > 0
                            ? TelegramFileAsset::vaultCount().' file(s) in the Telegram vault. DM the bot as a file admin to add more.'
                            : 'Vault is empty. DM a document to the bot as TELEGRAM_FILE_ADMIN_USERNAME, then pick it here.'),
                    ToggleButtons::make('file_mode')
                        ->label('File count')
                        ->options([
                            'single' => 'One file',
                            'multiple' => 'Multiple files',
                        ])
                        ->default('multiple')
                        ->live()
                        ->grouped()
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => in_array($get('file_source') ?? 'upload', ['upload', 'existing', 'telegram'], true))
                        ->afterStateHydrated(function (ToggleButtons $component, mixed $state, ?LearningResource $record): void {
                            if ($record === null) {
                                return;
                            }

                            $files = filled($record->telegram_files)
                                ? ($record->telegram_files ?? [])
                                : ($record->files ?? []);
                            $component->state(count($files) === 1 ? 'single' : 'multiple');
                        }),
                    FileUpload::make('files')
                        ->label(fn (Get $get): string => $get('file_mode') === 'single' ? 'Study file' : 'Study files')
                        ->disk(LearningResourceFiles::diskName())
                        ->directory(fn (Get $get): string => filled($get('course_id'))
                            ? LearningResourceFiles::directoryForCourse($get('course_id'))
                            : LearningResourceFiles::DirectoryPrefix)
                        ->preserveFilenames()
                        ->multiple()
                        ->maxSize(LearningResourceFiles::MaxSizeKilobytes)
                        ->maxFiles(fn (Get $get): int => $get('file_mode') === 'single' ? 1 : 10)
                        ->acceptedFileTypes(LearningResourceFiles::acceptedMimeTypes())
                        ->required(fn (Get $get): bool => $required && ($get('file_source') ?? 'upload') === 'upload')
                        ->visible(fn (Get $get): bool => ($get('file_source') ?? 'upload') === 'upload')
                        ->dehydrated(fn (Get $get): bool => ($get('file_source') ?? 'upload') === 'upload')
                        ->disabled(fn (Get $get): bool => blank($get('course_id')))
                        ->helperText(fn (Get $get): string => filled($get('course_id'))
                            ? 'Uploads into this course’s file library. Students get a fresh multipart upload each time (slower than Telegram).'
                            : 'Select a course before uploading study files.')
                        ->columnSpanFull(),
                    Select::make('existing_files')
                        ->label(fn (Get $get): string => $get('file_mode') === 'single' ? 'Study file' : 'Study files')
                        ->options(fn (Get $get): array => LearningResourceFiles::optionsForCourse($get('course_id')))
                        ->getOptionLabelsUsing(fn (array $values): array => collect($values)
                            ->filter(fn ($path): bool => is_string($path) && filled($path))
                            ->mapWithKeys(fn (string $path): array => [$path => basename($path)])
                            ->all())
                        ->searchable()
                        ->multiple()
                        ->maxItems(fn (Get $get): int => $get('file_mode') === 'single' ? 1 : 10)
                        ->required(fn (Get $get): bool => $required && ($get('file_source') ?? 'upload') === 'existing')
                        ->visible(fn (Get $get): bool => ($get('file_source') ?? 'upload') === 'existing')
                        ->dehydrated(fn (Get $get): bool => ($get('file_source') ?? 'upload') === 'existing')
                        ->disabled(fn (Get $get): bool => blank($get('course_id')))
                        ->helperText(fn (Get $get): string => filled($get('course_id'))
                            ? (LearningResourceFiles::countForCourse($get('course_id')) > 0
                                ? 'Pick from files already uploaded for this course (Course files page).'
                                : 'No files in this course library yet. Upload some on Course files, or switch to Upload.')
                            : 'Select a course to choose from its file library.')
                        ->afterStateHydrated(function (Select $component, mixed $state, ?LearningResource $record): void {
                            if ($record?->files) {
                                $component->state($record->files);
                            }
                        })
                        ->columnSpanFull(),
                    Select::make('telegram_file_ids')
                        ->label(fn (Get $get): string => $get('file_mode') === 'single' ? 'Telegram file' : 'Telegram files')
                        ->options(fn (): array => TelegramFileAsset::optionsForSelect())
                        ->getSearchResultsUsing(fn (string $search): array => TelegramFileAsset::searchOptions($search))
                        ->getOptionLabelsUsing(fn (array $values): array => TelegramFileAsset::labelsForFileIds($values))
                        ->searchable()
                        ->multiple()
                        ->maxItems(fn (Get $get): int => $get('file_mode') === 'single' ? 1 : 10)
                        ->required(fn (Get $get): bool => $required && ($get('file_source') ?? 'upload') === 'telegram')
                        ->visible(fn (Get $get): bool => ($get('file_source') ?? 'upload') === 'telegram')
                        ->dehydrated(fn (Get $get): bool => ($get('file_source') ?? 'upload') === 'telegram')
                        ->helperText('Bot delivers these by Telegram file_id — no disk re-upload. Add files by DMing the bot as a configured file admin.')
                        ->hintAction(
                            Action::make('openTelegramVault')
                                ->label('Manage vault')
                                ->icon(Heroicon::OutlinedPaperClip)
                                ->url(TelegramFileAssetResource::getUrl())
                                ->openUrlInNewTab()
                        )
                        ->afterStateHydrated(function (Select $component, mixed $state, ?LearningResource $record): void {
                            if (! is_array($record?->telegram_files) || $record->telegram_files === []) {
                                return;
                            }

                            $component->state(collect($record->telegram_files)
                                ->pluck('file_id')
                                ->filter()
                                ->values()
                                ->all());
                        })
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }
}
