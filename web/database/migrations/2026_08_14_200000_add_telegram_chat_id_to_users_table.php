<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Telegram chat id, learned via the /telegram/webhook handshake
            // (a bot can't message a chat it hasn't received a message from
            // first — see TelegramWebhookController) and used to deliver a
            // one-time login code via the Telegram Bot API.
            $table->string('telegram_chat_id')->nullable()->unique()->after('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('telegram_chat_id');
        });
    }
};
