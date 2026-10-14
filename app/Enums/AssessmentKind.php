<?php

namespace App\Enums;

enum AssessmentKind: string
{
    case Quiz = 'quiz';
    case Exam = 'exam';

    public function label(): string
    {
        return match ($this) {
            self::Quiz => 'Quiz',
            self::Exam => 'Exam',
        };
    }
}
