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
            self::Exams => 'Past Exams',
            self::Practice => 'Practice',
        };
    }

    public function singularLabel(): string
    {
        return match ($this) {
            self::Modules => 'module',
            self::Notes => 'note',
            self::Exams => 'past exam',
            self::Practice => 'practice set',
        };
    }

    /**
     * @return list<ResourceType>
     */
    public function types(): array
    {
        return match ($this) {
            self::Modules => [ResourceType::Module],
            self::Notes => [ResourceType::LectureNotes, ResourceType::Summary],
            self::Exams => [ResourceType::PastExam],
            self::Practice => [ResourceType::PracticeQuestion, ResourceType::Flashcards],
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
