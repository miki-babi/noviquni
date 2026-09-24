<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Learnings\Pages\CreateLearning;
use App\Models\Course;
use App\Support\LearningResourceFiles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageCourseFiles extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Course files';

    protected static ?string $title = 'Course files';

    protected string $view = 'filament.pages.manage-course-files';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'course_id' => null,
            'files' => [],
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Upload files')
                                ->submit('save'),
                        ]),
                    ]),
                EmbeddedTable::make(),
            ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Batch upload')
                    ->description('Upload study files into a course library. When creating a learning resource for that course, you can choose from these files.')
                    ->schema([
                        Select::make('course_id')
                            ->label('Course')
                            ->options(fn (): array => Course::query()
                                ->active()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->live()
                            ->searchable()
                            ->helperText(fn (Get $get): string => filled($get('course_id'))
                                ? LearningResourceFiles::countForCourse($get('course_id')).' file(s) already in this course library.'
                                : 'Select a course to upload into its library.')
                            ->columnSpanFull(),
                        FileUpload::make('files')
                            ->label('Study files')
                            ->disk(LearningResourceFiles::diskName())
                            ->directory(fn (Get $get): string => filled($get('course_id'))
                                ? LearningResourceFiles::directoryForCourse($get('course_id'))
                                : LearningResourceFiles::DirectoryPrefix)
                            ->preserveFilenames()
                            ->multiple()
                            ->maxSize(LearningResourceFiles::MaxSizeKilobytes)
                            ->acceptedFileTypes(LearningResourceFiles::acceptedMimeTypes())
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('course_id')))
                            ->helperText('PDF, Word, PowerPoint, or images. Original filenames are kept.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Course library')
            ->description('Files already uploaded for the selected course.')
            ->records(fn (): array => LearningResourceFiles::listForCourse($this->data['course_id'] ?? null))
            ->columns([
                TextColumn::make('name')
                    ->label('File')
                    ->searchable(),
                TextColumn::make('size_label')
                    ->label('Size'),
                TextColumn::make('path')
                    ->label('Path')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
            ])
            ->recordActions([
                Action::make('createResource')
                    ->label('Create resource')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->url(function (array $record): ?string {
                        $courseId = $this->data['course_id'] ?? null;
                        $path = $record['path'] ?? null;

                        if (! filled($courseId) || ! is_string($path)) {
                            return null;
                        }

                        $course = Course::query()->find($courseId);
                        $title = pathinfo((string) ($record['name'] ?? ''), PATHINFO_FILENAME);

                        return CreateLearning::getCreateUrl([
                            'course_id' => $courseId,
                            'stream_id' => $course?->stream_id,
                            'existing_files' => [$path],
                            'title' => filled($title) ? $title : null,
                        ]);
                    }),
                Action::make('delete')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (array $record): void {
                        $courseId = $this->data['course_id'] ?? null;
                        $path = $record['path'] ?? null;

                        if (! filled($courseId) || ! is_string($path) || ! LearningResourceFiles::isValidPathForCourse($path, $courseId)) {
                            Notification::make()
                                ->title('File could not be deleted')
                                ->danger()
                                ->send();

                            return;
                        }

                        Storage::disk(LearningResourceFiles::diskName())->delete($path);

                        Notification::make()
                            ->title('File deleted')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('createResource')
                        ->label('Create resource')
                        ->icon(Heroicon::OutlinedDocumentPlus)
                        ->action(function (Collection $records) {
                            $courseId = $this->data['course_id'] ?? null;

                            if (! filled($courseId)) {
                                return null;
                            }

                            $paths = $records
                                ->pluck('path')
                                ->filter(fn ($path): bool => is_string($path) && filled($path))
                                ->unique()
                                ->take(10)
                                ->values()
                                ->all();

                            if ($paths === []) {
                                return null;
                            }

                            $course = Course::query()->find($courseId);

                            return Redirect::to(CreateLearning::getCreateUrl([
                                'course_id' => $courseId,
                                'stream_id' => $course?->stream_id,
                                'existing_files' => $paths,
                            ]));
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->paginated(false)
            ->emptyStateHeading(fn (): string => filled($this->data['course_id'] ?? null)
                ? 'No files in this course library'
                : 'Select a course')
            ->emptyStateDescription(fn (): string => filled($this->data['course_id'] ?? null)
                ? 'Upload files above to add them to this course library.'
                : 'Choose a course to see its uploaded study files.')
            ->emptyStateIcon(Heroicon::OutlinedFolderOpen);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $files = $data['files'] ?? [];

        if (is_string($files) && filled($files)) {
            $files = [$files];
        }

        if (! is_array($files)) {
            $files = [];
        }

        $count = count(array_values(array_filter($files, fn ($path): bool => filled($path))));

        Notification::make()
            ->title($count === 1 ? '1 file uploaded' : "{$count} files uploaded")
            ->success()
            ->send();

        $this->form->fill([
            'course_id' => $data['course_id'] ?? null,
            'files' => [],
        ]);
    }
}
