<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

/**
 * Overrides Filament's default login page so Telegram and Lightning-wallet
 * login (the two passwordless options) are the primary, visible actions on
 * /admin/login, with the traditional email/password form tucked behind a
 * collapsed disclosure for emergencies. See resources/views/filament/pages/auth/login.blade.php.
 */
class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.login';
}
