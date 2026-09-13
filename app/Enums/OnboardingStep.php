<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case Start = 'start';
    case Stream = 'stream';
    case University = 'university';
    case Semester = 'semester';
    case Courses = 'courses';
    case Complete = 'complete';
}
