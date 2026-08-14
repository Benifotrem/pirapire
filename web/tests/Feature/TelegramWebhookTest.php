<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Telegram\TelegramBotClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.webhook_secret' => 'whsecret']);
    }

    private function postUpdate(array $message, string $secret = 'whsecret')
    {
        return $this->postJson('/api/telegram/webhook', ['message' => $message], [
            'X-Telegram-Bot-Api-Secret-Token' => $secret,
        ]);
    }

    public function test_rejects_updates_with_the_wrong_secret_token(): void
    {
        $this->mock(TelegramBotClient::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

        $this->postUpdate(['chat' => ['id' => 1], 'text' => '/vincular ABC123'], 'wrong')
            ->assertStatus(401);
    }

    public function test_valid_link_code_attaches_chat_id_to_the_pending_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'telegram_chat_id' => null]);
        Cache::put('telegram-link:ABC123', ['user_id' => $admin->id, 'status' => 'pending'], 600);

        $this->mock(TelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once();
        });

        $this->postUpdate(['chat' => ['id' => 999888], 'text' => '/vincular abc123'])
            ->assertOk()
            ->assertJsonPath('status', 'linked');

        $this->assertSame('999888', $admin->fresh()->telegram_chat_id);
        $this->assertSame('confirmed', Cache::get('telegram-link:ABC123')['status']);
    }

    public function test_unknown_code_is_ignored_without_touching_any_user(): void
    {
        $this->mock(TelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once();
        });

        $this->postUpdate(['chat' => ['id' => 1], 'text' => '/vincular NOPE00'])
            ->assertOk()
            ->assertJsonPath('status', 'unknown_code');
    }

    public function test_chat_already_linked_to_another_admin_is_rejected(): void
    {
        $existing = User::factory()->create(['role' => 'admin', 'telegram_chat_id' => '555']);
        $pending = User::factory()->create(['role' => 'admin', 'telegram_chat_id' => null]);
        Cache::put('telegram-link:XYZ999', ['user_id' => $pending->id, 'status' => 'pending'], 600);

        $this->mock(TelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once();
        });

        $this->postUpdate(['chat' => ['id' => 555], 'text' => '/vincular xyz999'])
            ->assertOk()
            ->assertJsonPath('status', 'conflict');

        $this->assertNull($pending->fresh()->telegram_chat_id);
    }

    public function test_unrelated_messages_are_ignored(): void
    {
        $this->mock(TelegramBotClient::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

        $this->postUpdate(['chat' => ['id' => 1], 'text' => 'hola'])
            ->assertOk()
            ->assertJsonPath('status', 'ignored');
    }
}
