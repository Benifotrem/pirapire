<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->unique()->after('linking_key');
        });

        // Dropping a column that still carries a unique index fails on
        // SQLite ("no such column" during the post-drop index rebuild)
        // unless the index is dropped first — split into its own
        // Schema::table() call so it runs after the column above exists.
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['whatsapp_number']);
            $table->dropColumn('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('whatsapp_number')->nullable()->unique()->after('linking_key');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['telegram_chat_id']);
            $table->dropColumn('telegram_chat_id');
        });
    }
};
