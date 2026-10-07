<?php

namespace App\Enums;

enum OpportunityType: string
{
    case Scholarship = 'scholarship';
    case Internship = 'internship';
    case Job = 'job';
    case Mentorship = 'mentorship';

    public function label(): string
    {
        return match ($this) {
            self::Scholarship => 'Scholarship',
            self::Internship => 'Internship',
            self::Job => 'Job',
            self::Mentorship => 'Mentorship',
        };
    }

    public function copyKey(): string
    {
        return match ($this) {
            self::Scholarship => 'scholarship',
            self::Internship => 'internship',
            self::Job => 'job',
            self::Mentorship => 'mentorship',
        };
    }

    public function savedTabKey(): string
    {
        return match ($this) {
            self::Scholarship => 'scholarships',
            self::Internship => 'internships',
            self::Job => 'jobs',
            self::Mentorship => 'mentorship',
        };
    }

    public static function fromSavedTabKey(string $key): ?self
    {
        return match ($key) {
            'scholarships' => self::Scholarship,
            'internships' => self::Internship,
            'jobs' => self::Job,
            'mentorship' => self::Mentorship,
            default => null,
        };
    }

    public static function fromMenuAction(string $action): ?self
    {
        return match ($action) {
            'scholarships' => self::Scholarship,
            'internships' => self::Internship,
            'opportunities' => self::Job,
            'mentorship' => self::Mentorship,
            default => null,
        };
    }
}
