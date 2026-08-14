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
        Schema::create('escrow_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('creator_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('counterparty_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->text('description');
            $table->unsignedBigInteger('amount_sats');
            $table->unsignedBigInteger('fee_sats');
            $table->enum('status', [
                'created',      // hold invoice generated, awaiting funding
                'funded',       // hold invoice paid (HTLC accepted, not settled)
                'in_progress',  // both parties confirmed work has started
                'completed',    // released to the freelancer, hold invoice settled
                'disputed',     // an admin must resolve
                'refunded',     // hold invoice cancelled, payer refunded
                'cancelled',    // expired or cancelled before funding
            ])->default('created');
            $table->text('hold_invoice');
            $table->string('payment_hash')->unique();
            $table->string('preimage')->nullable();
            $table->text('payout_destination')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_jobs');
    }
};
