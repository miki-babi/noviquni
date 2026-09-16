<?php

namespace App\Enums;

enum ResourceType: string
{
    case Module = 'module';
    case LectureNotes = 'lecture_notes';
    case Summary = 'summary';
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
            self::PastExam => 'Past exam',
            self::Assignment => 'Assignment',
            self::PracticeQuestion => 'Practice questions',
            self::Flashcards => 'Flashcards',
            self::Other => 'Other',
        };
    }
}
