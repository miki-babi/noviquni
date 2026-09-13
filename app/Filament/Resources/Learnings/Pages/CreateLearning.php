<?php

namespace App\Filament\Resources\Learnings\Pages;

use App\Filament\Resources\Learnings\LearningResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLearning extends CreateRecord
{
    protected static string $resource = LearningResource::class;
}
