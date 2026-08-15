<?php

namespace Tests\Feature\MiniApp;

use App\Models\Customer;
use App\Models\EscrowDispute;
use App\Models\EscrowJob;
use App\Models\User;
use App\Services\Lightning\LnbitsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMiniAppTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'admin-bot-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.bot_token' => self::BOT_TOKEN]);
    }

    private function initDataFor(int $userId, string $botToken = self::BOT_TOKEN): string
    {
        $fields = [
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => $userId, 'first_name' => 'Admin']),
        ];
        ksort($fields);
        $checkString = collect($fields)->map(fn ($v, $k) => "{$k}={$v}")->implode("\n");
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $fields['hash'] = hash_hmac('sha256', $checkString, $secretKey);

        return http_build_query($fields);
    }

    private function as(User $user, string $method, string $uri, array $data = [])
    {
        $headers = ['X-Telegram-Init-Data' => $this->initDataFor((int) $user->telegram_chat_id)];

        return $method === 'GET'
            ? $this->withHeaders($headers)->getJson($uri)
            : $this->withHeaders($headers)->postJson($uri, $data);
    }

    public function test_unlinked_telegram_chat_is_forbidden(): void
    {
        $this->withHeaders(['X-Telegram-Init-Data' => $this->initDataFor(1)])
            ->getJson('/api/miniapp/admin/me')
            ->assertStatus(403);
    }

    public function test_customer_bot_signature_does_not_grant_admin_access(): void
    {
        User::factory()->create(['telegram_chat_id' => '42', 'role' => 'admin']);

        $this->withHeaders(['X-Telegram-Init-Data' => $this->initDataFor(42, 'a-different-bot-token')])
            ->getJson('/api/miniapp/admin/me')
            ->assertStatus(401);
    }

    public function test_linked_support_user_can_view_stats_but_not_wallet(): void
    {
        $support = User::factory()->create(['telegram_chat_id' => '42', 'role' => 'support']);

        $this->as($support, 'GET', '/api/miniapp/admin/stats')->assertOk();
        $this->as($support, 'GET', '/api/miniapp/admin/wallet')->assertStatus(403);
    }

    public function test_linked_admin_user_can_view_wallet(): void
    {
        $admin = User::factory()->create(['telegram_chat_id' => '42', 'role' => 'admin']);

        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldReceive('getWalletDetails')
            ->once()->andReturn(['balance' => 5_000_000, 'name' => 'Pirapire Wallet']));

        $this->as($admin, 'GET', '/api/miniapp/admin/wallet')
            ->assertOk()
            ->assertJsonPath('balance_sats', 5000);
    }

    public function test_escrow_jobs_lists_and_filters_by_status(): void
    {
        $admin = User::factory()->create(['telegram_chat_id' => '42', 'role' => 'admin']);
        EscrowJob::factory()->create(['status' => 'funded']);
        EscrowJob::factory()->create(['status' => 'disputed']);

        $this->as($admin, 'GET', '/api/miniapp/admin/escrow-jobs')->assertOk()->assertJsonCount(2);
        $this->as($admin, 'GET', '/api/miniapp/admin/escrow-jobs?status=disputed')->assertOk()->assertJsonCount(1);
    }

    public function test_resolve_dispute_releases_funds_and_records_the_resolving_admin(): void
    {
        $admin = User::factory()->create(['telegram_chat_id' => '42', 'role' => 'admin']);
        $customer = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'disputed']);
        $dispute = EscrowDispute::factory()->create([
            'escrow_job_id' => $job->id,
            'opened_by_customer_id' => $customer->id,
            'status' => 'open',
        ]);

        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldReceive('payInvoice')->once()->andReturn(['payment_hash' => 'x']));

        $this->as($admin, 'POST', "/api/miniapp/admin/disputes/{$dispute->id}/resolve", [
            'action' => 'release',
            'payout_bolt11' => 'lnbc1payout',
            'resolution_notes' => 'Freelancer entregó el trabajo.',
        ])->assertOk()->assertJsonPath('status', 'resolved');

        $this->assertSame('completed', $job->fresh()->status);
        $this->assertSame($admin->id, $dispute->fresh()->resolved_by_user_id);
    }

    public function test_show_dispute_returns_a_single_dispute_even_beyond_the_list_limit(): void
    {
        $admin = User::factory()->create(['telegram_chat_id' => '42', 'role' => 'admin']);
        $customer = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'disputed']);
        $dispute = EscrowDispute::factory()->create([
            'escrow_job_id' => $job->id,
            'opened_by_customer_id' => $customer->id,
            'status' => 'open',
        ]);

        $this->as($admin, 'GET', "/api/miniapp/admin/disputes/{$dispute->id}")
            ->assertOk()
            ->assertJsonPath('id', $dispute->id);
    }
}
