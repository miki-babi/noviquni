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
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
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
                    ->dehydrated()
                    ->visible(fn (?LearningResource $record): bool => filled($record?->generation_kind)),
                Select::make('stream_id')
                    ->relationship('stream', 'name')
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('course_id', null);
                        $set('existing_files', null);
                    })
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
                        $set('existing_files', null);

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
                    ->helperText('Optional. Groups this resource under a parent module.'),
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
                ...static::fileFields(required: false),
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
                        ->native(false)
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            if ($state === ResourceType::Flashcards->value) {
                                $set('is_premium', true);
                                $set('is_bait', false);
                            }
                        }),
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
                    Select::make('stream_id')
                        ->relationship('stream', 'name')
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('course_id', null);
                            $set('existing_files', null);
                        })
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
                            $set('existing_files', null);

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
                        ->helperText('Optional. Groups this resource under a parent module.'),
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
                        ->disabled(fn (Get $get): bool => $get('type') === ResourceType::Flashcards->value
                            || (bool) $get('is_premium')),
                    Toggle::make('is_published')->default(false),
                    ...static::fileFields(required: true),
                    ...SeoFields::make(),
                ]),
        ];
    }

    protected static function contentJsonField(bool $required = false): CodeEditor
    {
        $field = CodeEditor::make('content')
            ->label('Content JSON')
            ->language(Language::Json)
            ->helperText('Optional interactive payload (quiz, notes, flashcards, etc.). Leave empty for file-only resources.')
            ->columnSpanFull()
            ->visible(fn (?LearningResource $record): bool => filled($record?->content) || filled($record?->generation_kind))
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

        return $field;
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
                        ->required()
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
                        ->required()
                        ->live()
                        ->grouped()
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => in_array($get('file_source') ?? 'upload', ['upload', 'existing', 'telegram'], true))
                        ->afterStateHydrated(function (ToggleButtons $component, mixed $state, ?LearningResource $record): void {
                            $files = filled($record?->telegram_files)
                                ? ($record->telegram_files ?? [])
                                : ($record?->files ?? []);
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
