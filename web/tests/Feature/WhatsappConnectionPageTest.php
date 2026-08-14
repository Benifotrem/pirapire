<?php

namespace Tests\Feature;

use App\Filament\Pages\WhatsappConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsappConnectionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_the_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'web');

        $this->assertTrue(WhatsappConnection::canAccess());
    }

    public function test_support_cannot_access_the_page(): void
    {
        $support = User::factory()->create(['role' => 'support']);
        $this->actingAs($support, 'web');

        $this->assertFalse(WhatsappConnection::canAccess());
    }

    public function test_renders_the_qr_when_status_is_qr(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'web');

        Cache::forever('whatsapp:connection', [
            'status' => 'qr',
            'qr_png_base64' => 'ZmFrZS1wbmc=',
            'updated_at' => '2026-08-14T20:00:00+00:00',
        ]);

        Livewire::test(WhatsappConnection::class)
            ->assertSee('Escaneá este código')
            ->assertSeeHtml('ZmFrZS1wbmc=');
    }

    public function test_renders_connected_state(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'web');

        Cache::forever('whatsapp:connection', [
            'status' => 'connected',
            'qr_png_base64' => null,
            'updated_at' => '2026-08-14T20:00:00+00:00',
        ]);

        Livewire::test(WhatsappConnection::class)
            ->assertSee('WhatsApp conectado');
    }
}
