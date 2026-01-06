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
            $table->string('merchant_city')->nullable()->after('merchant_name');
            $table->string('merchant_phone')->nullable()->after('merchant_city');
            $table->string('merchant_email')->nullable()->after('merchant_phone');
            $table->text('merchant_address')->nullable()->after('merchant_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_configs', function (Blueprint $table) {
            $table->dropColumn(['merchant_city', 'merchant_phone', 'merchant_email', 'merchant_address']);
        });
    }
};
