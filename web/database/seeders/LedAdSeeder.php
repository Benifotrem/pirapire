<?php

namespace Database\Seeders;

use App\Models\LedAd;
use App\Models\LedAdSubmission;
use App\Models\LedDisplaySetting;
use Illuminate\Database\Seeder;

/**
 * Demo data for the header's LED ticker: a handful of published ads (as if
 * already approved by an admin) plus a couple still pending, so a fresh
 * local environment shows both the public cartel and a non-empty
 * moderation queue without manually filling out /anunciar a bunch of times.
 */
class LedAdSeeder extends Seeder
{
    public function run(): void
    {
        LedDisplaySetting::current()->update(['enabled' => true, 'color' => 'red']);

        LedAd::query()->firstOrCreate(
            ['message' => 'Café Satoshi acepta Bitcoin ⚡ — Asunción centro'],
            ['url' => 'https://cafesatoshi.com.py', 'is_active' => true, 'sort_order' => 1],
        );

        LedAd::query()->firstOrCreate(
            ['message' => 'Ferretería Lightning — pagá con BTC en Ciudad del Este'],
            ['url' => 'https://ferreterialightning.com.py', 'is_active' => true, 'sort_order' => 2],
        );

        LedAd::query()->firstOrCreate(
            ['message' => 'Hostal Encarnación BTC — reservá con sats'],
            ['url' => 'https://hostalencarnacionbtc.com.py', 'is_active' => true, 'sort_order' => 3],
        );

        // Inactive on purpose — exercises the "apagado no se muestra" path
        // (App\View\Composers\LedDisplayComposer) without deleting the row.
        LedAd::query()->firstOrCreate(
            ['message' => 'Estudio Jurídico Sats & Ley — consultá con Bitcoin'],
            ['url' => 'https://satsyley.com.py', 'is_active' => false, 'sort_order' => 4],
        );

        LedAdSubmission::query()->firstOrCreate(
            ['business_name' => 'Panadería Halving'],
            [
                'category' => 'tienda',
                'description' => 'Panadería de barrio que empezó a aceptar Lightning este mes.',
                'address' => 'Av. España 1234',
                'city' => 'Asunción',
                'business_hours' => 'Lun a Sáb 6:00–20:00',
                'accepts_lightning' => true,
                'accepts_onchain' => false,
                'message' => 'Panadería Halving — pan fresco, pagá con sats ⚡',
                'url' => 'https://instagram.com/panaderiahalving',
                'contact_name' => 'Rosa Benítez',
                'contact_email' => 'rosa@panaderiahalving.com.py',
                'contact_phone' => '+595981123456',
                'status' => 'pending',
            ],
        );

        LedAdSubmission::query()->firstOrCreate(
            ['business_name' => 'Taller Mecánico Nakamoto'],
            [
                'category' => 'servicios',
                'description' => 'Taller mecánico, quiere sumarse al directorio Bitcoin Paraguay.',
                'address' => 'Ruta 2 km 15',
                'city' => 'Ciudad del Este',
                'business_hours' => 'Lun a Vie 8:00–17:00',
                'accepts_lightning' => true,
                'accepts_onchain' => true,
                'message' => 'Taller Nakamoto — reparación de autos, aceptamos Bitcoin',
                'url' => 'https://tallernakamoto.com.py',
                'contact_name' => 'Julio Ramírez',
                'contact_email' => 'julio@tallernakamoto.com.py',
                'contact_phone' => '+595984654321',
                'status' => 'pending',
            ],
        );
    }
}
