<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // LNURL-auth linking key (LUD-04) — lets an admin/support user log
            // into the Filament panel with a Lightning wallet instead of (or
            // alongside) a password. Nullable: only set once the user links a
            // wallet from the admin panel's user menu.
            $table->string('linking_key')->nullable()->unique()->after('password');

            // WhatsApp JID (e.g. 595981111111@s.whatsapp.net) used to deliver
            // a one-time login code via the Pirapire WhatsApp bot.
            $table->string('whatsapp_number')->nullable()->unique()->after('linking_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['linking_key', 'whatsapp_number']);
        });
    }
};
