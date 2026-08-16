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
        Schema::table('escrow_jobs', function (Blueprint $table) {
            // The freelancer's own invoice, submitted when they mark the
            // job "delivered" — release() pays this out, so the client
            // never has to relay a bolt11 they got from an outside chat.
            $table->text('freelancer_payout_invoice')->nullable()->after('payout_destination');

            // A freshly posted ('open') job doesn't have a funding invoice
            // or freelancer yet — createInvoice() only runs once a
            // freelancer is picked (see acceptApplication() in
            // EscrowService). Both were NOT NULL from the original
            // hold-invoice-era design, which assumed a funding invoice
            // existed the moment a job was created.
            $table->text('funding_invoice')->nullable()->change();
            $table->string('payment_hash')->nullable()->change();
            // Likewise only set once acceptApplication() generates the
            // funding invoice — an 'open' posting has nothing to expire yet.
            $table->timestamp('expires_at')->nullable()->change();
        });

        // 'created' meant "funding invoice generated, awaiting payment" —
        // that now happens once a freelancer is picked (see 'assigned'
        // below), not the moment a client posts a job. Adds 'open'
        // (posted, taking applications, no freelancer yet), keeps
        // 'assigned' as the old 'created' slot, and adds 'delivered'
        // (freelancer submitted their payout invoice, awaiting release).
        Schema::table('escrow_jobs', function (Blueprint $table) {
            $table->enum('status', [
                'open',         // posted, taking applications from freelancers
                'assigned',     // freelancer picked, funding invoice generated, awaiting payment
                'funded',       // funding invoice paid
                'in_progress',  // freelancer marked work as started (optional step)
                'delivered',    // freelancer marked work done and submitted their payout invoice
                'completed',    // released to the freelancer
                'disputed',     // creator or freelancer flagged it; an admin must resolve
                'refunded',     // paid back to the client
                'cancelled',    // expired or cancelled before assignment/funding
            ])->default('open')->change();
        });

        Schema::create('escrow_job_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('escrow_job_id');
            $table->foreign('escrow_job_id')->references('id')->on('escrow_jobs')->cascadeOnDelete();
            $table->foreignId('freelancer_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->text('message');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            // A freelancer can only have one active application per job —
            // re-applying should update the existing row, not stack another.
            $table->unique(['escrow_job_id', 'freelancer_customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_job_applications');

        Schema::table('escrow_jobs', function (Blueprint $table) {
            $table->enum('status', [
                'created', 'funded', 'in_progress', 'completed', 'disputed', 'refunded', 'cancelled',
            ])->default('created')->change();
        });

        Schema::table('escrow_jobs', function (Blueprint $table) {
            $table->dropColumn('freelancer_payout_invoice');
            $table->text('funding_invoice')->nullable(false)->change();
            $table->string('payment_hash')->nullable(false)->change();
            $table->timestamp('expires_at')->nullable(false)->change();
        });
    }
};
