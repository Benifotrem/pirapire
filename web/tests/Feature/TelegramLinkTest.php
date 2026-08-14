<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TelegramLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_generate_a_link_code(): void
    {
        $this->get('/staff-link-telegram')->assertRedirect();
    }

    public function test_authenticated_admin_gets_a_link_code_tied_to_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'web')->get('/staff-link-telegram');

        $response->assertOk();
        $code = $response->viewData('code');

        $this->assertNotEmpty($code);
        $this->assertSame($admin->id, Cache::get('telegram-link:'.$code)['user_id']);
    }

    public function test_status_reports_pending_then_confirmed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Cache::put('telegram-link:CODE01', ['user_id' => $admin->id, 'status' => 'pending'], 600);

        $this->actingAs($admin, 'web')
            ->getJson('/staff-link-telegram/status/CODE01')
            ->assertJsonPath('status', 'pending');

        Cache::put('telegram-link:CODE01', ['user_id' => $admin->id, 'status' => 'confirmed'], 600);

        $this->actingAs($admin, 'web')
            ->getJson('/staff-link-telegram/status/CODE01')
            ->assertJsonPath('status', 'confirmed');
    }

    public function test_status_does_not_leak_another_admins_pending_code(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $intruder = User::factory()->create(['role' => 'admin']);
        Cache::put('telegram-link:SECRET', ['user_id' => $owner->id, 'status' => 'pending'], 600);

        $this->actingAs($intruder, 'web')
            ->getJson('/staff-link-telegram/status/SECRET')
            ->assertJsonPath('status', 'expired');
    }
}
