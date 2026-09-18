<?php

namespace App\Enums;

enum ResourceType: string
{
    case Module = 'module';
    case Notes = 'notes';
    case Flashcards = 'flashcards';
    case Quiz = 'quiz';
    case PracticeExams = 'practice_exams';
    case Slides = 'slides';
    case Worksheet = 'worksheet';
    case Assignment = 'assignment';
    case MidExam = 'mid_exam';
    case FinalExam = 'final_exam';
    case ReferenceBooks = 'reference_books';

    public function label(): string
    {
        return match ($this) {
            self::Module => 'Module',
            self::Notes => 'Notes',
            self::Flashcards => 'Flashcards',
            self::Quiz => 'Quiz',
            self::PracticeExams => 'Practice exams',
            self::Slides => 'Slides (PPT)',
            self::Worksheet => 'Worksheet',
            self::Assignment => 'Assignment',
            self::MidExam => 'Mid exam',
            self::FinalExam => 'Final exam',
            self::ReferenceBooks => 'Reference books',
        };
    }

    public function miniAppRouteName(): string
    {
        return match ($this) {
            self::Module => 'tg.play.module',
            self::Notes => 'tg.play.notes',
            self::Flashcards => 'tg.play.flashcards',
            self::Quiz => 'tg.play.quiz',
            self::PracticeExams, self::MidExam, self::FinalExam => 'tg.play.exam',
            self::Slides => 'tg.play.slides',
            self::Worksheet => 'tg.play.worksheet',
            self::Assignment => 'tg.play.assignment',
            self::ReferenceBooks => 'tg.play.reference-books',
        };
    }

    /**
     * Types admins may create for the catalog.
     *
     * @return list<self>
     */
    public static function creatableCases(): array
    {
        return [
            self::Module,
            self::Notes,
            self::Flashcards,
            self::Quiz,
            self::PracticeExams,
            self::Slides,
            self::Worksheet,
            self::Assignment,
            self::MidExam,
            self::FinalExam,
            self::ReferenceBooks,
        ];
    }
}
