<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin WhatsApp OTP login (StaffWhatsappAuthController) is removed
 * along with the whole WhatsApp bot — it depended on the same unstable
 * Baileys session. Wallet (LNURL-auth) and Telegram login remain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['whatsapp_number']);
            $table->dropColumn('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_number')->nullable()->unique()->after('linking_key');
        });
    }
};
