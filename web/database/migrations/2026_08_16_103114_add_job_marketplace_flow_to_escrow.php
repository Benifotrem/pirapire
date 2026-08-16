<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        //
        // This does NOT use enum()->change(): on Postgres that generates
        // `ALTER COLUMN "status" TYPE varchar(255) CHECK (...)`, which
        // Postgres rejects outright — a CHECK can't ride along inside an
        // ALTER COLUMN TYPE clause, it needs its own ADD CONSTRAINT
        // statement. Confirmed the hard way against production. Dropping
        // the original enum's named CHECK constraint and switching to a
        // plain string column sidesteps that; the valid-status set is
        // enforced in EscrowService::assertStatus() (application-level),
        // same as every other status transition already was.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE escrow_jobs DROP CONSTRAINT IF EXISTS escrow_jobs_status_check');
        }
        Schema::table('escrow_jobs', function (Blueprint $table) {
            $table->string('status')->default('open')->change();
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

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE escrow_jobs DROP CONSTRAINT IF EXISTS escrow_jobs_status_check');
        }
        Schema::table('escrow_jobs', function (Blueprint $table) {
            $table->string('status')->default('created')->change();
        });

        Schema::table('escrow_jobs', function (Blueprint $table) {
            $table->dropColumn('freelancer_payout_invoice');
            $table->text('funding_invoice')->nullable(false)->change();
            $table->string('payment_hash')->nullable(false)->change();
            $table->timestamp('expires_at')->nullable(false)->change();
        });
    }
};
