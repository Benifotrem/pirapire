<?php

namespace Tests\Feature;

use Tests\TestCase;

class MiniAppLocaleTest extends TestCase
{
    public function test_customer_miniapp_embeds_spanish_translations_by_default(): void
    {
        $this->get('/miniapp/customer')->assertOk()->assertSee('Alertas P2P');
    }

    public function test_customer_miniapp_embeds_english_translations_after_switching(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/miniapp/customer')
            ->assertOk()
            ->assertSee('P2P Alerts')
            ->assertDontSee('Alertas P2P');
    }
}
