<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lets a customer restrict a P2P alert to one liquidity source — see App\Services\P2P\P2POfferAggregator. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->enum('source', ['robosats', 'mostro', 'all'])->default('all')->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
