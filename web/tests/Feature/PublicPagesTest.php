<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_landing_page_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('Pirapire.pro');
    }

    public function test_login_page_renders_with_qr_svg(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('<svg', false);
        $response->assertViewHas('lnurl');
        $response->assertViewHas('sessionId');
    }
}
