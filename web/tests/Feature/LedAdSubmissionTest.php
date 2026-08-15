<?php

namespace Tests\Feature;

use App\Models\LedAdSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedAdSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Café Satoshi',
            'category' => 'cafeteria',
            'city' => 'Asunción',
            'message' => 'Café Satoshi acepta Bitcoin ⚡ — Asunción centro',
            'url' => 'https://cafesatoshi.com.py',
            'accepts_lightning' => '1',
            'accepts_onchain' => '1',
            'contact_email' => 'dueno@cafesatoshi.com.py',
        ], $overrides);
    }

    public function test_form_page_renders(): void
    {
        // Explicit locale: assertions below check Spanish copy, independent
        // of whatever APP_LOCALE the running environment happens to have.
        app()->setLocale('es');

        $this->get('/anunciar')->assertOk()->assertSee('¿Tu comercio acepta Bitcoin?');
    }

    public function test_valid_submission_is_stored_as_pending(): void
    {
        $this->post('/anunciar', $this->validPayload())
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('led_ad_submissions', [
            'business_name' => 'Café Satoshi',
            'status' => 'pending',
        ]);
    }

    public function test_business_name_message_url_and_category_are_required(): void
    {
        $this->post('/anunciar', $this->validPayload([
            'business_name' => '',
            'message' => '',
            'url' => '',
            'category' => '',
        ]))->assertSessionHasErrors(['business_name', 'message', 'url', 'category']);

        $this->assertDatabaseCount('led_ad_submissions', 0);
    }

    public function test_url_must_be_a_valid_url(): void
    {
        $this->post('/anunciar', $this->validPayload(['url' => 'not-a-url']))
            ->assertSessionHasErrors(['url']);
    }

    public function test_category_must_be_one_of_the_known_options(): void
    {
        $this->post('/anunciar', $this->validPayload(['category' => 'casino']))
            ->assertSessionHasErrors(['category']);
    }

    public function test_submission_does_not_show_up_on_the_public_ticker_until_approved(): void
    {
        LedAdSubmission::factory()->create(['status' => 'pending', 'message' => 'Todavía no aprobado']);

        $this->get('/')->assertOk()->assertDontSee('Todavía no aprobado');
    }
}
