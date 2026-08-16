<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\Lnurl\LnurlAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LnurlAuthTest extends TestCase
{
    use RefreshDatabase;

    /** Drives a challenge to "authenticated" the way the wallet callback would, without a real signature. */
    private function authenticateChallenge(LnurlAuthService $service, string $linkingKey): string
    {
        $challenge = $service->generateChallenge();
        $service->markAuthenticated($challenge['k1'], $linkingKey);

        return $challenge['session_id'];
    }

    public function test_completing_a_challenge_logs_the_customer_in_and_redirects_to_the_dashboard(): void
    {
        $service = app(LnurlAuthService::class);
        $sessionId = $this->authenticateChallenge($service, 'wallet-key-123');
        Customer::factory()->create(['linking_key' => 'wallet-key-123']);

        $this->withSession(['lnurl_auth_session_id' => $sessionId])
            ->post('/lnurl-auth/complete')
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated('customer');
    }

    /**
     * A guest who clicked an escrow job posting on the public LED ticker
     * (see App\View\Composers\LedDisplayComposer::openJobAds()) gets bounced
     * to /login by the auth:customer middleware, which stashes the URL they
     * were headed to. Logging in should land them back on the job board,
     * not on the plain dashboard — otherwise the click did nothing useful.
     */
    public function test_login_honors_an_intended_url_stashed_by_a_guest_redirect(): void
    {
        $customer = Customer::factory()->create(['linking_key' => 'wallet-key-456']);

        // Reproduces what the auth:customer middleware does when a guest
        // hits a protected route: redirect to /login with url.intended set.
        $this->get('/dashboard/escrow')->assertRedirect('/login');

        $service = app(LnurlAuthService::class);
        $sessionId = $this->authenticateChallenge($service, 'wallet-key-456');

        $this->withSession(['lnurl_auth_session_id' => $sessionId])
            ->post('/lnurl-auth/complete')
            ->assertRedirect('/dashboard/escrow');

        $this->assertAuthenticatedAs($customer, 'customer');
    }
}
