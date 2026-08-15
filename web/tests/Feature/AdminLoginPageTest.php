<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLoginPageTest extends TestCase
{
    public function test_telegram_and_wallet_login_are_the_prominent_options(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Iniciar sesión con Telegram')
            ->assertSee('/staff-login-telegram', false)
            ->assertSee('Iniciar sesión con billetera Lightning')
            ->assertSee('/staff-login', false);
    }

    public function test_traditional_password_login_is_tucked_behind_a_collapsed_disclosure(): void
    {
        $response = $this->get('/admin/login')->assertOk();

        $response->assertSee('<details', false);
        $response->assertSee('Acceso de emergencia con usuario y contraseña');
    }
}
