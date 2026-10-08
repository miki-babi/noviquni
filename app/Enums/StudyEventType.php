<?php

namespace App\Enums;

enum StudyEventType: string
{
    case Note = 'note';
    case Quiz = 'quiz';
    case Flashcards = 'flashcards';
    case PastExam = 'past exam';

    public static function fromResourceType(ResourceType $type): self
    {
        return match ($type) {
            ResourceType::Quiz => self::Quiz,
            ResourceType::Flashcards => self::Flashcards,
            ResourceType::PracticeExams, ResourceType::MidExam, ResourceType::FinalExam => self::PastExam,
            default => self::Note,
        };
    }
}
