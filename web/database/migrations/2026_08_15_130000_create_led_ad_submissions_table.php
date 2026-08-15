<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('led_ad_submissions', function (Blueprint $table) {
            $table->id();

            // Comercio
            $table->string('business_name');
            $table->string('category');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('business_hours')->nullable();
            $table->boolean('accepts_lightning')->default(true);
            $table->boolean('accepts_onchain')->default(true);

            // Lo que se ve en el cartel LED
            $table->string('message');
            $table->string('url');

            // Contacto (para que el admin pueda comunicarse, no se publica)
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            // Moderación
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('admin_notes')->nullable();
            $table->foreignId('led_ad_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('led_ad_submissions');
    }
};
