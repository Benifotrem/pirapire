<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    public function test_default_locale_is_spanish(): void
    {
        $this->get('/')->assertOk()->assertSee('Iniciar sesión con Lightning');
    }

    public function test_switching_to_english_persists_across_requests(): void
    {
        $this->get('/lang/en')->assertRedirect();

        $this->get('/')->assertOk()->assertSee('Log in with Lightning');
    }

    public function test_switching_back_to_spanish_works(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/')->assertOk()->assertSee('Log in with Lightning');

        $this->get('/lang/es')->assertRedirect();

        $this->get('/')->assertOk()->assertSee('Iniciar sesión con Lightning');
    }

    public function test_unsupported_locale_is_ignored(): void
    {
        $this->get('/lang/fr')->assertRedirect();

        $this->get('/')->assertOk()->assertSee('Iniciar sesión con Lightning');
    }

    public function test_switcher_is_present_on_the_landing_page(): void
    {
        $this->get('/')->assertOk()->assertSee('ES')->assertSee('EN');
    }
}
