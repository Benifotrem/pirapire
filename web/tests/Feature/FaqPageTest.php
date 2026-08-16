<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_faq_page_loads_in_spanish_by_default(): void
    {
        $this->get('/faq')
            ->assertOk()
            ->assertSee('Manual paso a paso')
            ->assertSee('Preguntas frecuentes')
            ->assertSee('¿Necesito email o contraseña para usar Pirapire?');
    }

    public function test_faq_page_loads_in_english_when_switched(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/faq')
            ->assertOk()
            ->assertSee('Step-by-step manual')
            ->assertSee('Frequently asked questions')
            ->assertSee('Do I need an email or password to use Pirapire?');
    }

    public function test_faq_page_covers_every_manual_topic(): void
    {
        $response = $this->get('/faq')->assertOk();

        $response->assertSee('Iniciar sesión con tu billetera Lightning');
        $response->assertSee('Configurar alertas P2P');
        $response->assertSee('Vincular tu cuenta con Telegram');
        $response->assertSee('Contratar un trabajo (como cliente)');
        $response->assertSee('Trabajar y cobrar (como freelancer)');
        $response->assertSee('Comandos del bot BØLT en Telegram');
    }

    public function test_faq_page_lists_every_escrow_bot_command(): void
    {
        $response = $this->get('/faq')->assertOk();

        $response->assertSee('/escrow create', false);
        $response->assertSee('/escrow browse', false);
        $response->assertSee('/escrow apply', false);
        $response->assertSee('/escrow applications', false);
        $response->assertSee('/escrow accept', false);
        $response->assertSee('/escrow deliver', false);
        $response->assertSee('/escrow release', false);
        $response->assertSee('/escrow dispute', false);
        $response->assertSee('/escrow status', false);
        $response->assertSee('/escrow cancel', false);
    }

    public function test_faq_page_does_not_require_authentication(): void
    {
        $this->get('/faq')->assertOk();
    }

    public function test_faq_link_is_present_in_the_header(): void
    {
        $this->get('/')->assertOk()->assertSee(route('faq'), false);
    }

    public function test_landing_page_how_it_works_button_links_to_faq(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSeeInOrder([
            'Cómo funciona',
        ]);
        $response->assertSee('href="'.route('faq').'"', false);
    }

    public function test_faq_link_is_present_for_logged_in_customers_too(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('faq'), false);
    }
}
