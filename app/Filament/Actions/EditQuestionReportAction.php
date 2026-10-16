<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Learnings\Schemas\LearningForm;
use App\Models\QuestionReport;
use App\Models\User;
use App\Services\QuestionReportService;
use App\Support\StudyRichText;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class EditQuestionReportAction
{
    public static function make(): Action
    {
        return Action::make('editQuestion')
            ->label('Edit question')
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->visible(fn (QuestionReport $record): bool => app(QuestionReportService::class)->liveQuestion($record) !== null)
            ->modalHeading('Edit reported question')
            ->modalDescription('Changes update the live quiz, exam, or daily challenge question students see.')
            ->modalWidth('4xl')
            ->fillForm(function (QuestionReport $record): array {
                $question = app(QuestionReportService::class)->liveQuestion($record);

                if ($question === null) {
                    return [];
                }

                $item = LearningForm::questionsForRepeater([$question])[0] ?? [];

                return [
                    ...$item,
                    'mark_resolved' => $record->isPending(),
                    'admin_notes' => $record->admin_notes,
                ];
            })
            ->schema(fn (QuestionReport $record): array => [
                static::richEditor('question', $record)
                    ->label('Question')
                    ->required(),
                Repeater::make('options')
                    ->label('Choices')
                    ->schema([
                        static::richEditor('body', $record)
                            ->label('Choice')
                            ->required(),
                    ])
                    ->minItems(2)
                    ->maxItems(6)
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
                static::richEditor('explanation', $record)
                    ->label('Explanation')
                    ->helperText('Optional rationale shown after the student submits.'),
                Checkbox::make('mark_resolved')
                    ->label('Mark report as resolved after saving')
                    ->visible(fn (): bool => $record->isPending())
                    ->default(true),
                Textarea::make('admin_notes')
                    ->label('Admin note')
                    ->rows(2)
                    ->maxLength(2000),
            ])
            ->action(function (QuestionReport $record, array $data): void {
                /** @var User $admin */
                $admin = Auth::user();
                $questions = LearningForm::questionsFromRepeater([
                    [
                        'question' => $data['question'] ?? null,
                        'options' => $data['options'] ?? [],
                        'answerIndex' => $data['answerIndex'] ?? 0,
                        'explanation' => $data['explanation'] ?? null,
                    ],
                ]);

                $question = $questions[0] ?? null;

                if ($question === null) {
                    Notification::make()
                        ->title('Could not save question')
                        ->body('Add a question stem and at least two choices.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    app(QuestionReportService::class)->updateQuestion(
                        $record,
                        $question,
                        $admin,
                        (bool) ($data['mark_resolved'] ?? false),
                        $data['admin_notes'] ?? null,
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title('Could not save question')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Question updated')
                    ->success()
                    ->send();
            });
    }

    protected static function richEditor(string $name, QuestionReport $report): RichEditor
    {
        $courseId = app(QuestionReportService::class)->attachmentCourseId($report);
        $resource = $report->learningResource;

        return RichEditor::make($name)
            ->json()
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'link'],
                ['bulletList', 'orderedList'],
                ['attachFiles'],
                ['undo', 'redo'],
            ])
            ->fileAttachmentsDisk(StudyRichText::AttachmentsDisk)
            ->fileAttachmentsDirectory(StudyRichText::attachmentsDirectory($courseId))
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
            ])
            ->resizableImages()
            ->preventFileAttachmentPathTampering(
                $resource !== null,
                allowFilePathUsing: function (string $file) use ($resource, $courseId): bool {
                    if ($resource === null) {
                        return StudyRichText::isStoredAttachmentPath($file, $courseId);
                    }

                    $originalIds = StudyRichText::attachmentIdsInContent(
                        $resource->getOriginal('content') ?? $resource->content,
                    );

                    return in_array($file, $originalIds, true)
                        || StudyRichText::isStoredAttachmentPath($file, $resource->course_id);
                },
            )
            ->afterStateHydrated(function (RichEditor $component, mixed $state): void {
                if (is_array($state) && ($state['type'] ?? null) === 'doc') {
                    return;
                }

                $component->state(StudyRichText::forEditor($state));
            })
            ->columnSpanFull();
    }
}
