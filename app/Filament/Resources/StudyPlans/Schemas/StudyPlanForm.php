<?php

namespace App\Filament\Resources\StudyPlans\Schemas;

use App\Enums\StudyPlanType;
use App\Models\Course;
use App\Models\LearningResource;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class StudyPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_id')
                    ->label('Course')
                    ->options(fn (): array => Course::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->required()
                    ->live()
                    ->searchable(),
                Select::make('type')
                    ->options(collect(StudyPlanType::cases())->mapWithKeys(
                        fn (StudyPlanType $type) => [$type->value => $type->label()]
                    )->all())
                    ->required()
                    ->live()
                    ->native(false)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $type = StudyPlanType::tryFrom((string) $state);

                        if ($type === StudyPlanType::BaitChecklist) {
                            $set('is_premium', false);
                        } elseif ($type !== null) {
                            $set('is_premium', true);
                        }
                    }),
                TextInput::make('title')->required()->columnSpanFull(),
                TextInput::make('week_number')
                    ->numeric()
                    ->integer()
                    ->visible(fn (Get $get): bool => in_array($get('type'), [
                        StudyPlanType::Week->value,
                        StudyPlanType::BaitChecklist->value,
                    ], true)),
                Toggle::make('is_published')->default(false),
                Toggle::make('is_premium')
                    ->default(true)
                    ->disabled(fn (Get $get): bool => $get('type') === StudyPlanType::BaitChecklist->value)
                    ->dehydrated(),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                Repeater::make('items')
                    ->relationship()
                    ->schema([
                        TextInput::make('label')->required(),
                        Select::make('learning_resource_id')
                            ->label('Resource')
                            ->options(fn (Get $get): array => filled($get('../../course_id'))
                                ? LearningResource::query()
                                    ->where('course_id', $get('../../course_id'))
                                    ->orderBy('sort_order')
                                    ->orderBy('title')
                                    ->pluck('title', 'id')
                                    ->all()
                                : [])
                            ->searchable(),
                        TextInput::make('sort_order')->numeric()->integer()->default(0)->required(),
                    ])
                    ->orderColumn('sort_order')
                    ->columnSpanFull()
                    ->defaultItems(0),
            ]);
    }
}
