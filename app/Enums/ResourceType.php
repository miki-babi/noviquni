<?php

namespace App\Enums;

enum ResourceType: string
{
    case Quiz = 'quiz';
    case PracticeExams = 'practice_exams';
    case ReferenceBooks = 'reference_books';
    case PastExams = 'past_exams';

    public function label(): string
    {
        return match ($this) {
            self::Quiz => 'Quiz',
            self::PracticeExams => 'Practice exams',
            self::ReferenceBooks => 'Reference books',
            self::PastExams => 'Past exams',
        };
    }

    public function miniAppRouteName(): string
    {
        return match ($this) {
            self::Quiz => 'tg.play.quiz',
            self::PracticeExams, self::PastExams => 'tg.play.exam',
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
            self::PracticeExams,
            self::Quiz,
            self::ReferenceBooks,
            self::PastExams,
        ];
    }
}
