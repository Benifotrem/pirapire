<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Telegram\TelegramBotClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StaffTelegramAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_requesting_a_code_for_an_email_without_telegram_linked_is_rejected(): void
    {
        User::factory()->create(['role' => 'admin', 'email' => 'admin@pirapire.pro', 'telegram_chat_id' => null]);

        $this->mock(TelegramBotClient::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

        $this->post('/staff-telegram-auth/request', ['email' => 'admin@pirapire.pro'])
            ->assertSessionHasErrors('email');
    }

    public function test_requesting_a_code_for_a_linked_admin_sends_it_and_shows_verify_form(): void
    {
        User::factory()->create(['role' => 'admin', 'email' => 'admin@pirapire.pro', 'telegram_chat_id' => '454660685']);

        $this->mock(TelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->with('454660685', \Mockery::type('string'));
        });

        $this->post('/staff-telegram-auth/request', ['email' => 'admin@pirapire.pro'])
            ->assertRedirect(route('staff-telegram-auth.verify-form'));
    }

    public function test_correct_code_logs_the_matched_admin_in(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'telegram_chat_id' => '454660685']);

        Cache::put('staff-telegram-auth:test-token', [
            'code' => '654321',
            'user_id' => $admin->id,
            'attempts' => 0,
        ], 300);

        $this->withSession(['staff_telegram_auth_token' => 'test-token'])
            ->post('/staff-telegram-auth/verify', ['code' => '654321'])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_wrong_code_is_rejected_without_logging_in(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'telegram_chat_id' => '454660685']);

        Cache::put('staff-telegram-auth:test-token', [
            'code' => '654321',
            'user_id' => $admin->id,
            'attempts' => 0,
        ], 300);

        $this->withSession(['staff_telegram_auth_token' => 'test-token'])
            ->post('/staff-telegram-auth/verify', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest('web');
    }
}
