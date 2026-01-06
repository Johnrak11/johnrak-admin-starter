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
        Schema::table('payment_configs', function (Blueprint $table) {
            // Store provider-specific merchant info as JSON
            // Format: {"aba": {"merchant_city": "...", ...}, "bakong": {"merchant_city": "...", ...}}
            $table->json('provider_merchant_info')->nullable()->after('merchant_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->dropColumn('provider_merchant_info');
        });
    }
};
