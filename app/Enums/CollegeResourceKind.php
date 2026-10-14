<?php

namespace App\Enums;

enum CollegeResourceKind: string
{
    case Notes = 'notes';
    case Quiz = 'quiz';
    case Exam = 'exam';
    case Flashcards = 'flashcards';

    public function label(): string
    {
        return match ($this) {
            self::Notes => 'Study notes',
            self::Quiz => 'Quiz',
            self::Exam => 'Exam',
            self::Flashcards => 'Flashcards',
        };
    }

    public function resourceType(): ResourceType
    {
        return match ($this) {
            self::Notes => ResourceType::ReferenceBooks,
            self::Quiz => ResourceType::Quiz,
            self::Exam => ResourceType::PracticeExams,
            self::Flashcards => ResourceType::Quiz,
        };
    }

    public function generatePath(): string
    {
        return 'generate/'.$this->value;
    }

    public function miniAppRouteName(): string
    {
        return match ($this) {
            self::Notes => 'tg.play.reference-books',
            self::Quiz, self::Flashcards => 'tg.play.quiz',
            self::Exam => 'tg.play.exam',
        };
    }
}
