<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WhatsappStatusApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp_bot.api_token' => 'test-bot-token']);
    }

    private function withBotToken()
    {
        return $this->withHeader('Authorization', 'Bearer test-bot-token');
    }

    public function test_requires_the_bot_token(): void
    {
        $this->postJson('/api/whatsapp/status', ['status' => 'connected'])
            ->assertStatus(401);
    }

    public function test_stores_a_qr_push(): void
    {
        $this->withBotToken()
            ->postJson('/api/whatsapp/status', ['status' => 'qr', 'qr_png_base64' => 'ZmFrZS1wbmc='])
            ->assertOk();

        $state = Cache::get('whatsapp:connection');
        $this->assertSame('qr', $state['status']);
        $this->assertSame('ZmFrZS1wbmc=', $state['qr_png_base64']);
        $this->assertNotNull($state['updated_at']);
    }

    public function test_a_connected_push_overwrites_a_previous_qr(): void
    {
        Cache::forever('whatsapp:connection', ['status' => 'qr', 'qr_png_base64' => 'old', 'updated_at' => 'x']);

        $this->withBotToken()
            ->postJson('/api/whatsapp/status', ['status' => 'connected'])
            ->assertOk();

        $state = Cache::get('whatsapp:connection');
        $this->assertSame('connected', $state['status']);
        $this->assertNull($state['qr_png_base64']);
    }

    public function test_rejects_an_invalid_status(): void
    {
        $this->withBotToken()
            ->postJson('/api/whatsapp/status', ['status' => 'bogus'])
            ->assertStatus(422);
    }
}
