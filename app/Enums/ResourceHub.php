<?php

namespace App\Enums;

enum ResourceHub: string
{
    case Modules = 'modules';
    case Notes = 'notes';
    case Exams = 'exams';
    case Practice = 'practice';

    public function label(): string
    {
        return match ($this) {
            self::Modules => 'Modules',
            self::Notes => 'Notes',
            self::Exams => 'Exams',
            self::Practice => 'Practice',
        };
    }

    public function singularLabel(): string
    {
        return match ($this) {
            self::Modules => 'module',
            self::Notes => 'note',
            self::Exams => 'exam',
            self::Practice => 'practice set',
        };
    }

    /**
     * @return list<ResourceType>
     */
    public function types(): array
    {
        return match ($this) {
            self::Modules => [ResourceType::Module, ResourceType::Slides],
            self::Notes => [
                ResourceType::Notes,
                ResourceType::Worksheet,
                ResourceType::ReferenceBooks,
                ResourceType::Assignment,
            ],
            self::Exams => [ResourceType::MidExam, ResourceType::FinalExam],
            self::Practice => [
                ResourceType::Quiz,
                ResourceType::Flashcards,
                ResourceType::PracticeExams,
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function typeValues(): array
    {
        return array_map(
            fn (ResourceType $type): string => $type->value,
            $this->types(),
        );
    }
}
