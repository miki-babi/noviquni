<?php

namespace App\Enums;

enum QuestionReportStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }
}
