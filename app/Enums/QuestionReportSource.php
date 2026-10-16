<?php

namespace App\Enums;

enum QuestionReportSource: string
{
    case Quiz = 'quiz';
    case Exam = 'exam';
    case DailyChallenge = 'daily_challenge';

    public function label(): string
    {
        return match ($this) {
            self::Quiz => 'Quiz',
            self::Exam => 'Exam',
            self::DailyChallenge => 'Daily challenge',
        };
    }
}
