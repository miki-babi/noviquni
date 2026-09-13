<?php

namespace App\Filament\Pages;

use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
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

        Notification::make()->title('Settings saved')->success()->send();
    }
}
