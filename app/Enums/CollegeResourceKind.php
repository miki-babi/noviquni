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
            self::Notes => ResourceType::LectureNotes,
            self::Quiz => ResourceType::PracticeQuestion,
            self::Exam => ResourceType::PastExam,
            self::Flashcards => ResourceType::Flashcards,
        };
    }

    public function generatePath(): string
    {
        return 'generate/'.$this->value;
    }
}
