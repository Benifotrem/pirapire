<x-filament-panels::page.simple>
    @if (filament()->hasRegistration())
        <x-slot name="subheading">
            {{ __('filament-panels::pages/auth/login.actions.register.before') }}

            {{ $this->registerAction }}
        </x-slot>
    @endif

    <div class="flex flex-col gap-3">
        <x-filament::button tag="a" href="/staff-login-telegram" icon="heroicon-o-paper-airplane" color="info" size="lg" class="w-full justify-center">
            Iniciar sesión con Telegram
        </x-filament::button>

        <x-filament::button tag="a" href="/staff-login" icon="heroicon-o-bolt" color="warning" size="lg" class="w-full justify-center">
            Iniciar sesión con billetera Lightning
        </x-filament::button>
    </div>

    <div class="my-6 flex items-center gap-3 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
        <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
        o
        <span class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
    </div>

    <details class="group">
        <summary class="cursor-pointer text-center text-xs font-medium text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">
            Acceso de emergencia con usuario y contraseña
        </summary>

        <div class="mt-4">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

            <x-filament-panels::form id="form" wire:submit="authenticate">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                />
            </x-filament-panels::form>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
        </div>
    </details>
</x-filament-panels::page.simple>
