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
            ResourceType::PracticeExams, ResourceType::PastExams => self::PastExam,
            default => self::Note,
        };
    }
}
