<?php

namespace App\Enums;

enum StudyPlanType: string
{
    case Week = 'week';
    case ExamSprint = 'exam_sprint';
    case BaitChecklist = 'bait_checklist';

    public function label(): string
    {
        return match ($this) {
            self::Week => 'Week plan',
            self::ExamSprint => 'Exam sprint',
            self::BaitChecklist => 'Week-1 checklist',
        };
    }

    public function requiresPremium(): bool
    {
        return false;
    }
}
