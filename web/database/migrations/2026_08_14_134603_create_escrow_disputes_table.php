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
        Schema::create('escrow_disputes', function (Blueprint $table) {
            $table->id();
            $table->uuid('escrow_job_id');
            $table->foreign('escrow_job_id')->references('id')->on('escrow_jobs')->cascadeOnDelete();
            $table->foreignId('opened_by_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->text('reason');
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->enum('resolution', ['released_to_freelancer', 'refunded_to_client'])->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrow_disputes');
    }
};
