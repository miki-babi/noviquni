<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case Start = 'start';
    case Year = 'year';
    case Stream = 'stream';
    case Department = 'department';
    case University = 'university';
    case Semester = 'semester';
    case Courses = 'courses';
    case Complete = 'complete';
}
