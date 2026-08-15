<?php

namespace Tests\Feature;

use App\Filament\Resources\LedAdSubmissionResource;
use App\Filament\Resources\LedAdSubmissionResource\Pages\ListLedAdSubmissions;
use App\Models\LedAdSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LedAdSubmissionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'web');
    }

    public function test_approving_a_submission_creates_a_live_led_ad(): void
    {
        $submission = LedAdSubmission::factory()->create([
            'status' => 'pending',
            'message' => 'Mensaje original',
            'url' => 'https://example.com/original',
        ]);

        Livewire::test(ListLedAdSubmissions::class)
            ->callTableAction('approve', $submission, data: [
                'message' => 'Mensaje editado por el admin',
                'url' => 'https://example.com/original',
                'sort_order' => 5,
            ])
            ->assertHasNoTableActionErrors();

        $submission->refresh();
        $this->assertSame('approved', $submission->status);
        $this->assertNotNull($submission->led_ad_id);

        $this->assertDatabaseHas('led_ads', [
            'id' => $submission->led_ad_id,
            'message' => 'Mensaje editado por el admin',
            'is_active' => true,
            'sort_order' => 5,
        ]);
    }

    public function test_rejecting_a_submission_does_not_create_an_ad(): void
    {
        $submission = LedAdSubmission::factory()->create(['status' => 'pending']);

        Livewire::test(ListLedAdSubmissions::class)
            ->callTableAction('reject', $submission, data: ['admin_notes' => 'No pudimos verificar el negocio.'])
            ->assertHasNoTableActionErrors();

        $submission->refresh();
        $this->assertSame('rejected', $submission->status);
        $this->assertNull($submission->led_ad_id);
        $this->assertSame('No pudimos verificar el negocio.', $submission->admin_notes);
        $this->assertDatabaseCount('led_ads', 0);
    }

    public function test_pending_count_shows_in_the_navigation_badge(): void
    {
        LedAdSubmission::factory()->count(3)->create(['status' => 'pending']);
        LedAdSubmission::factory()->create(['status' => 'approved']);

        $this->assertSame('3', LedAdSubmissionResource::getNavigationBadge());
    }
}
