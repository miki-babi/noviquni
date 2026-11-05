<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case Start = 'start';
    case Year = 'year';
    case Department = 'department';
    case Courses = 'courses';
    case Complete = 'complete';
}
