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
        // LNbits has no "hold invoice" extension (see LnbitsClient) —
        // escrow now uses regular invoices, so `hold_invoice` /
        // `preimage` no longer describe what these columns hold.
        Schema::table('escrow_jobs', function (Blueprint $table) {
            $table->renameColumn('hold_invoice', 'funding_invoice');
        });

        Schema::table('escrow_jobs', function (Blueprint $table) {
            $table->dropColumn('preimage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('escrow_jobs', function (Blueprint $table) {
            $table->renameColumn('funding_invoice', 'hold_invoice');
            $table->string('preimage')->nullable();
        });
    }
};
