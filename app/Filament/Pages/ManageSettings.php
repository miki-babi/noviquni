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
            'free_referral_reward' => $settings->freeReferralReward(),
            'premium_referral_reward' => $settings->premiumReferralReward(),
            'premium_duration_days' => $settings->premiumDurationDays(),
            'payment_instructions' => $settings->paymentInstructions(),
            'telegram_start_image' => $settings->telegramStartImage(),
            'telegram_start_caption' => $settings->telegramStartCaption(),
            'telegram_start_buttons' => $settings->telegramStartButtons(),
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
                        TextInput::make('free_referral_reward')->numeric()->required()->suffix('ETB'),
                        TextInput::make('premium_referral_reward')->numeric()->required()->suffix('ETB'),
                        TextInput::make('premium_duration_days')->numeric()->required()->integer()->suffix('days'),
                        Textarea::make('payment_instructions')
                            ->rows(4)
                            ->helperText('Use {amount} and {reference} placeholders.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Telegram /start message')
                    ->description('Shown to students who already finished onboarding when they tap /start. Leave empty to use the default welcome copy.')
                    ->schema([
                        FileUpload::make('telegram_start_image')
                            ->label('Image')
                            ->image()
                            ->disk('public')
                            ->directory('telegram/start')
                            ->visibility('public')
                            ->helperText('Optional. Sent as a Telegram photo with the caption below.')
                            ->columnSpanFull(),
                        RichEditor::make('telegram_start_caption')
                            ->label('Caption')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link', 'code'],
                                ['blockquote', 'bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ])
                            ->helperText('Formatted for Telegram. Variables: {{first_name}}, {{name}}')
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
                                    ->label('Command / callback')
                                    ->helperText('Examples: start:continue, premium_pay, setup:courses')
                                    ->maxLength(64)
                                    ->required(fn (Get $get): bool => $get('type') === BroadcastButtonType::Command->value)
                                    ->visible(fn (Get $get): bool => $get('type') === BroadcastButtonType::Command->value),
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
        $settings->set(SettingsService::FREE_REFERRAL_REWARD, (string) $data['free_referral_reward']);
        $settings->set(SettingsService::PREMIUM_REFERRAL_REWARD, (string) $data['premium_referral_reward']);
        $settings->set(SettingsService::PREMIUM_DURATION_DAYS, (string) $data['premium_duration_days']);
        $settings->set(SettingsService::PAYMENT_INSTRUCTIONS, (string) $data['payment_instructions']);

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
