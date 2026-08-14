<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Whatsapp\WhatsappBotClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StaffWhatsappAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_code_for_an_unlinked_number_is_rejected(): void
    {
        $this->mock(WhatsappBotClient::class, function ($mock) {
            $mock->shouldNotReceive('sendMessage');
        });

        $this->post('/staff-whatsapp-auth/request', ['whatsapp_number' => '+595981111111'])
            ->assertSessionHasErrors('whatsapp_number');
    }

    public function test_requesting_a_code_for_a_linked_admin_number_sends_it_and_shows_verify_form(): void
    {
        User::factory()->create(['role' => 'admin', 'whatsapp_number' => '595981111111@s.whatsapp.net']);

        $this->mock(WhatsappBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with('595981111111@s.whatsapp.net', \Mockery::type('string'));
        });

        $this->post('/staff-whatsapp-auth/request', ['whatsapp_number' => '+595 981 111 111'])
            ->assertRedirect(route('staff-whatsapp-auth.verify-form'));
    }

    public function test_correct_code_logs_the_matched_admin_in(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'whatsapp_number' => '595981111111@s.whatsapp.net']);

        Cache::put('staff-whatsapp-auth:test-token', [
            'jid' => '595981111111@s.whatsapp.net',
            'code' => '654321',
            'mode' => 'login',
            'user_id' => $admin->id,
            'attempts' => 0,
        ], 300);

        $this->withSession(['staff_whatsapp_auth_token' => 'test-token'])
            ->post('/staff-whatsapp-auth/verify', ['code' => '654321'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_wrong_code_is_rejected_without_logging_in(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'whatsapp_number' => '595981111111@s.whatsapp.net']);

        Cache::put('staff-whatsapp-auth:test-token', [
            'jid' => '595981111111@s.whatsapp.net',
            'code' => '654321',
            'mode' => 'login',
            'user_id' => $admin->id,
            'attempts' => 0,
        ], 300);

        $this->withSession(['staff_whatsapp_auth_token' => 'test-token'])
            ->post('/staff-whatsapp-auth/verify', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest('web');
    }

    public function test_authenticated_admin_can_link_a_new_whatsapp_number(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'whatsapp_number' => null]);

        Cache::put('staff-whatsapp-auth:link-token', [
            'jid' => '595982222222@s.whatsapp.net',
            'code' => '111222',
            'mode' => 'link',
            'user_id' => $admin->id,
            'attempts' => 0,
        ], 300);

        $this->actingAs($admin, 'web')
            ->withSession(['staff_whatsapp_auth_token' => 'link-token'])
            ->post('/staff-whatsapp-auth/verify', ['code' => '111222'])
            ->assertRedirect('/admin');

        $this->assertSame('595982222222@s.whatsapp.net', $admin->fresh()->whatsapp_number);
    }
}
