<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Lnurl\LnurlAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class StaffLnurlAuthTest extends TestCase
{
    use RefreshDatabase;

    /** Drives a challenge to "authenticated" the way the wallet callback would, without a real signature. */
    private function authenticateChallenge(LnurlAuthService $service, string $linkingKey): string
    {
        $challenge = $service->generateChallenge();
        $service->markAuthenticated($challenge['k1'], $linkingKey);

        return $challenge['session_id'];
    }

    public function test_unknown_wallet_cannot_log_into_the_admin_panel(): void
    {
        $service = app(LnurlAuthService::class);
        $sessionId = $this->authenticateChallenge($service, 'unknown-linking-key');

        $this->withSession(['staff_lnurl_auth_session_id' => $sessionId])
            ->post('/staff-lnurl-auth/complete')
            ->assertRedirect(route('staff-login'))
            ->assertSessionHasErrors('lnurl');

        $this->assertGuest('web');
    }

    public function test_wallet_linked_to_an_admin_account_logs_them_in(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'linking_key' => 'admin-key-123']);

        $service = app(LnurlAuthService::class);
        $sessionId = $this->authenticateChallenge($service, 'admin-key-123');

        $this->withSession(['staff_lnurl_auth_session_id' => $sessionId])
            ->post('/staff-lnurl-auth/complete')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_wallet_linked_to_a_customer_only_cannot_log_into_the_admin_panel(): void
    {
        // A key that only exists on the customers table (never on users) must
        // not grant admin access — the two tables are deliberately unrelated.
        $service = app(LnurlAuthService::class);
        $sessionId = $this->authenticateChallenge($service, 'some-customer-key');

        $this->withSession(['staff_lnurl_auth_session_id' => $sessionId])
            ->post('/staff-lnurl-auth/complete')
            ->assertSessionHasErrors('lnurl');

        $this->assertGuest('web');
    }

    public function test_authenticated_admin_can_link_a_new_wallet_to_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'linking_key' => null]);

        $service = app(LnurlAuthService::class);
        $sessionId = $this->authenticateChallenge($service, 'fresh-linking-key');

        $this->actingAs($admin, 'web')
            ->withSession(['staff_lnurl_auth_session_id' => $sessionId])
            ->post('/staff-lnurl-auth/complete')
            ->assertRedirect('/admin');

        $this->assertSame('fresh-linking-key', $admin->fresh()->linking_key);
    }

    public function test_admin_cannot_link_a_wallet_already_linked_to_another_account(): void
    {
        User::factory()->create(['role' => 'admin', 'linking_key' => 'taken-key']);
        $secondAdmin = User::factory()->create(['role' => 'admin', 'linking_key' => null]);

        $service = app(LnurlAuthService::class);
        $sessionId = $this->authenticateChallenge($service, 'taken-key');

        $this->actingAs($secondAdmin, 'web')
            ->withSession(['staff_lnurl_auth_session_id' => $sessionId])
            ->post('/staff-lnurl-auth/complete')
            ->assertSessionHasErrors('lnurl');

        $this->assertNull($secondAdmin->fresh()->linking_key);
        Auth::guard('web')->logout();
    }
}
