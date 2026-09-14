<?php

namespace App\Filament\Pages;

use App\Models\ActivityLog;
use App\Services\ArtisanCommandRunner;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class RunArtisanCommands extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Artisan';

    protected static ?string $title = 'Run Artisan commands';

    protected string $view = 'filament.pages.run-artisan-commands';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public string $output = '';

    public ?int $lastExitCode = null;

    public function mount(): void
    {
        $this->form->fill([
            'command' => 'about',
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('run')
                    ->footer([
                        Actions::make([
                            Action::make('run')
                                ->label('Run command')
                                ->submit('run'),
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
                Section::make('Allowed commands')
                    ->description('Only whitelisted Artisan commands can be run from this page.')
                    ->schema([
                        Select::make('command')
                            ->label('Command')
                            ->options(fn (): array => app(ArtisanCommandRunner::class)->options())
                            ->required()
                            ->searchable()
                            ->native(false),
                    ]),
            ]);
    }

    public function run(): void
    {
        $command = (string) ($this->form->getState()['command'] ?? '');

        try {
            $result = app(ArtisanCommandRunner::class)->run($command);
        } catch (Throwable $exception) {
            $this->output = $exception->getMessage();
            $this->lastExitCode = 1;

            Notification::make()
                ->title('Command failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->output = $result['output'] !== '' ? $result['output'] : '(no output)';
        $this->lastExitCode = $result['exit_code'];

        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'action' => 'artisan.run',
            'meta' => [
                'command' => $result['command'],
                'exit_code' => $result['exit_code'],
            ],
        ]);

        Notification::make()
            ->title($result['exit_code'] === 0 ? 'Command finished' : 'Command exited with errors')
            ->body('php artisan '.$result['command'])
            ->color($result['exit_code'] === 0 ? 'success' : 'warning')
            ->send();
    }
}
