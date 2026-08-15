<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Customer;
use App\Models\VipSubscription;
use Illuminate\Database\Seeder;

/**
 * Demo customers covering both subscription tiers, so a fresh local
 * environment has something to look at in the admin panel's VIP list and
 * platform-stats widgets without manually paying a real invoice.
 */
class VipDemoSeeder extends Seeder
{
    public function run(): void
    {
        $vip = Customer::query()->firstOrCreate(
            ['telegram_chat_id' => '100000001'],
            ['linking_key' => bin2hex(random_bytes(33)), 'display_name' => 'satoshi_py'],
        );
        VipSubscription::query()->firstOrCreate(
            ['customer_id' => $vip->id, 'status' => 'active'],
            ['amount_sats' => 5000, 'payment_hash' => bin2hex(random_bytes(32)), 'starts_at' => now()->subDays(5), 'expires_at' => now()->addDays(25)],
        );
        Alert::query()->firstOrCreate(
            ['customer_id' => $vip->id, 'currency' => 'PYG', 'order_type' => 'ANY'],
            ['is_active' => true],
        );

        $expiredVip = Customer::query()->firstOrCreate(
            ['telegram_chat_id' => '100000002'],
            ['linking_key' => bin2hex(random_bytes(33)), 'display_name' => 'hodler_asuncion'],
        );
        VipSubscription::query()->firstOrCreate(
            ['customer_id' => $expiredVip->id, 'status' => 'expired'],
            ['amount_sats' => 5000, 'payment_hash' => bin2hex(random_bytes(32)), 'starts_at' => now()->subMonths(2), 'expires_at' => now()->subMonth()],
        );

        $free = Customer::query()->firstOrCreate(
            ['telegram_chat_id' => '100000003'],
            ['linking_key' => bin2hex(random_bytes(33)), 'display_name' => 'lightning_cde'],
        );
        Alert::query()->firstOrCreate(
            ['customer_id' => $free->id, 'currency' => 'USD', 'order_type' => 'BUY'],
            ['is_active' => true, 'min_amount' => 50, 'max_amount' => 500],
        );
    }
}
