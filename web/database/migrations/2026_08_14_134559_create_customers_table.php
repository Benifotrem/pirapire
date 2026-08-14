<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            // Sovereign identity: the LNURL-auth linking public key (compressed
            // secp256k1, hex) *is* the account. No email or password ever exists.
            // Nullable because a customer may first appear via a WhatsApp
            // command (e.g. !escrow) and only later link a wallet to log in
            // to the web dashboard.
            $table->string('linking_key', 66)->nullable()->unique();
            $table->string('display_name')->nullable();
            $table->string('whatsapp_number')->nullable()->unique();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
