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
            // Optional evidence the freelancer attaches at delivery — a
            // screenshot proving a "share this tweet" style micro-task was
            // actually done, or any other deliverable that isn't just a
            // payout invoice. Stored on the private 'local' disk (not
            // 'public') and only ever served back through an authenticated
            // route that checks the requester is a party to the job — see
            // EscrowDashboardController::proof() / MiniApp\CustomerController::proof().
            $table->string('proof_path')->nullable()->after('freelancer_payout_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('escrow_jobs', function (Blueprint $table) {
            $table->dropColumn('proof_path');
        });
    }
};
