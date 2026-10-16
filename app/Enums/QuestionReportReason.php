<?php

namespace App\Enums;

enum QuestionReportReason: string
{
    case WrongAnswer = 'wrong_answer';
    case Typo = 'typo';
    case Unclear = 'unclear';
    case ImageIssue = 'image_issue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WrongAnswer => 'Wrong answer',
            self::Typo => 'Typo',
            self::Unclear => 'Unclear',
            self::ImageIssue => 'Image issue',
            self::Other => 'Other',
        };
    }
}
