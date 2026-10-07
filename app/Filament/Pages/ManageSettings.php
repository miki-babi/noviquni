<?php

namespace App\Filament\Pages;

use App\Enums\BroadcastButtonType;
use App\Enums\TelegramButtonStyle;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected string $view = 'filament.pages.manage-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = app(SettingsService::class);

        $this->form->fill([
            'premium_price' => $settings->premiumPrice(),
            'required_referrals' => $settings->requiredReferrals(),
            'points_per_referral' => $settings->pointsPerReferral(),
            'premium_duration_days' => $settings->premiumDurationDays(),
            'payment_instructions' => $settings->paymentInstructions(),
            'telegram_start_image' => $settings->telegramStartImage(),
            'telegram_start_caption' => $settings->telegramStartCaption(),
            'telegram_start_buttons' => $settings->telegramStartButtons(),
            'cohort_urgency_copy' => $settings->cohortUrgencyCopy(),
            'opportunity_guidance_opening_message' => $settings->opportunityGuidanceOpeningMessage(),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Save settings')
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Premium & referrals')
                    ->schema([
                        TextInput::make('premium_price')->numeric()->required()->suffix('ETB'),
                        TextInput::make('required_referrals')->numeric()->required()->integer(),
                        TextInput::make('points_per_referral')
                            ->label('Points per referral')
                            ->numeric()
                            ->required()
                            ->integer()
                            ->minValue(1),
                        TextInput::make('premium_duration_days')->numeric()->required()->integer()->suffix('days'),
                        Textarea::make('payment_instructions')
                            ->rows(4)
                            ->helperText('Use {amount} and {reference} placeholders.')
                            ->columnSpanFull(),
                        Textarea::make('cohort_urgency_copy')
                            ->label('Cohort urgency copy')
                            ->rows(2)
                            ->helperText('Shown on the Mini App premium screen.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Opportunity guidance')
                    ->description('Default opening DM text sent to the student when a guide is assigned. Opportunities can override this. Admin notify still uses TELEGRAM_FILE_ADMIN_USERNAME.')
                    ->schema([
                        Textarea::make('opportunity_guidance_opening_message')
                            ->label('Default opening message')
                            ->rows(4)
                            ->helperText('Placeholders: {title}, {partner}, {student}')
                            ->columnSpanFull(),
                    ]),
                Section::make('Telegram /start message')
                    ->description('Shown when students tap /start, including new users before onboarding (stream picker follows). Leave empty to use the default welcome copy for onboarded students only.')
                    ->schema([
                        FileUpload::make('telegram_start_image')
                            ->label('Image')
                            ->image()
                            ->disk('public')
                            ->directory('telegram/start')
                            ->visibility('public')
                            ->preserveFilenames()
                            ->helperText('Optional. Sent as a Telegram photo with the caption below.')
                            ->columnSpanFull(),
                        RichEditor::make('telegram_start_caption')
                            ->label('Caption')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link', 'code'],
                                ['blockquote', 'bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ])
                            ->helperText('Formatted for Telegram. Variables: {{first_name}}, {{name}}, {{referral_cta}}. If {{referral_cta}} is omitted, a Start now link is appended automatically.')
                            ->columnSpanFull(),
                        Repeater::make('telegram_start_buttons')
                            ->label('Inline buttons')
                            ->schema([
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(64),
                                Select::make('type')
                                    ->options(collect(BroadcastButtonType::cases())->mapWithKeys(
                                        fn (BroadcastButtonType $type) => [$type->value => $type->label()]
                                    )->all())
                                    ->required()
                                    ->live()
                                    ->native(false),
                                Select::make('style')
                                    ->label('Color')
                                    ->options(
                                        collect(TelegramButtonStyle::cases())
                                            ->mapWithKeys(fn (TelegramButtonStyle $style) => [$style->value => $style->label()])
                                            ->prepend('Default', '')
                                            ->all()
                                    )
                                    ->placeholder('Default')
                                    ->native(false),
                                TextInput::make('command')
                                    ->label(fn (Get $get): string => $get('type') === BroadcastButtonType::Text->value
                                        ? 'Reply text'
                                        : 'Command / callback')
                                    ->helperText(fn (Get $get): string => $get('type') === BroadcastButtonType::Text->value
                                        ? 'Sent to the bot as if the student typed this. Leave empty to use the button label.'
                                        : 'Examples: start:continue, premium_pay, setup:courses')
                                    ->maxLength(64)
                                    ->required(fn (Get $get): bool => $get('type') === BroadcastButtonType::Command->value)
                                    ->visible(fn (Get $get): bool => in_array($get('type'), [
                                        BroadcastButtonType::Command->value,
                                        BroadcastButtonType::Text->value,
                                    ], true)),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->maxLength(2048)
                                    ->required(fn (Get $get): bool => in_array($get('type'), [
                                        BroadcastButtonType::Url->value,
                                        BroadcastButtonType::MiniApp->value,
                                    ], true))
                                    ->visible(fn (Get $get): bool => in_array($get('type'), [
                                        BroadcastButtonType::Url->value,
                                        BroadcastButtonType::MiniApp->value,
                                    ], true))
                                    ->helperText(fn (Get $get): string => $get('type') === BroadcastButtonType::MiniApp->value
                                        ? 'HTTPS Mini App URL (e.g. https://your-domain/tg/continue)'
                                        : 'Opens this URL'),
                            ])
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->addActionLabel('Add button')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        $settings->set(SettingsService::PREMIUM_PRICE, (string) $data['premium_price']);
        $settings->set(SettingsService::REQUIRED_REFERRALS, (string) $data['required_referrals']);
        $settings->set(SettingsService::POINTS_PER_REFERRAL, (string) $data['points_per_referral']);
        $settings->set(SettingsService::PREMIUM_DURATION_DAYS, (string) $data['premium_duration_days']);
        $settings->set(SettingsService::PAYMENT_INSTRUCTIONS, (string) $data['payment_instructions']);
        $settings->set(SettingsService::COHORT_URGENCY_COPY, (string) ($data['cohort_urgency_copy'] ?? ''));
        $settings->set(
            SettingsService::OPPORTUNITY_GUIDANCE_OPENING_MESSAGE,
            (string) ($data['opportunity_guidance_opening_message'] ?? ''),
        );

        $image = $data['telegram_start_image'] ?? null;
        if (is_array($image)) {
            $image = $image[0] ?? null;
        }

        $settings->set(SettingsService::TELEGRAM_START_IMAGE, (string) ($image ?? ''));
        $settings->set(SettingsService::TELEGRAM_START_CAPTION, (string) ($data['telegram_start_caption'] ?? ''));
        $settings->set(
            SettingsService::TELEGRAM_START_BUTTONS,
            json_encode(array_values($data['telegram_start_buttons'] ?? []), JSON_THROW_ON_ERROR),
        );

        Notification::make()->title('Settings saved')->success()->send();
    }
}
