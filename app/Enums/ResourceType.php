<?php

namespace App\Enums;

enum ResourceType: string
{
    case Module = 'module';
    case LectureNotes = 'lecture_notes';
    case Summary = 'summary';
    case Worksheet = 'worksheet';
    case PastExam = 'past_exam';
    case Assignment = 'assignment';
    case PracticeQuestion = 'practice_question';
    case Flashcards = 'flashcards';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Module => 'Module',
            self::LectureNotes => 'Lecture notes',
            self::Summary => 'Summary',
            self::Worksheet => 'Worksheet',
            self::PastExam => 'Past exam',
            self::Assignment => 'Assignment',
            self::PracticeQuestion => 'Practice questions',
            self::Flashcards => 'Flashcards',
            self::Other => 'Other',
        };
    }

    public function miniAppRouteName(): string
    {
        return match ($this) {
            self::Module => 'tg.play.module',
            self::LectureNotes => 'tg.play.notes',
            self::Summary => 'tg.play.summary',
            self::Worksheet => 'tg.play.worksheet',
            self::PastExam => 'tg.play.exam',
            self::Assignment => 'tg.play.assignment',
            self::PracticeQuestion => 'tg.play.quiz',
            self::Flashcards => 'tg.play.flashcards',
            self::Other => 'tg.play.other',
        };
    }

    /**
     * Types admins may create for the V1 study path.
     *
     * @return list<self>
     */
    public static function creatableCases(): array
    {
        return [
            self::Module,
            self::LectureNotes,
            self::Summary,
            self::Worksheet,
            self::PastExam,
            self::PracticeQuestion,
            self::Flashcards,
            self::Other,
        ];
    }

    /**
     * Path order within a module spine.
     */
    public function pathRank(): int
    {
        return match ($this) {
            self::Module => 0,
            self::LectureNotes, self::Summary => 1,
            self::Worksheet => 2,
            self::PracticeQuestion => 3,
            self::Flashcards => 4,
            self::PastExam => 5,
            default => 99,
        };
    }
}
