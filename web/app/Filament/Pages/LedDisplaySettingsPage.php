<?php

namespace App\Filament\Pages;

use App\Models\LedDisplaySetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

/** Global on/off + LED color for the header ticker. See App\Models\LedDisplaySetting. */
class LedDisplaySettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-tv';

    protected static ?string $navigationGroup = 'Publicidad';

    protected static ?string $navigationLabel = 'Configuración del cartel';

    protected static ?string $title = 'Cartel LED';

    protected static string $view = 'filament.pages.led-display-settings-page';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(LedDisplaySetting::current()->only(['enabled', 'color']));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Toggle::make('enabled')
                    ->label('Cartel activo')
                    ->helperText('Apagalo para ocultar el cartel del header en todo el sitio.'),
                Forms\Components\Select::make('color')
                    ->label('Color de LED')
                    ->options([
                        'red' => 'Rojo',
                        'green' => 'Verde',
                        'blue' => 'Azul eléctrico',
                        'mixed' => 'Mixto (aleatorio por anuncio)',
                    ])
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        LedDisplaySetting::current()->update($this->form->getState());
        Cache::forget('led-display:data');

        Notification::make()->title('Configuración guardada')->success()->send();
    }
}
