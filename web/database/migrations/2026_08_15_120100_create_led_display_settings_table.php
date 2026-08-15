<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Single-row settings table — see App\Models\LedDisplaySetting::current(). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('led_display_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->enum('color', ['red', 'green', 'blue', 'mixed'])->default('red');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('led_display_settings');
    }
};
