<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Remove unique constraint first, then make nullable
            $table->dropUnique(['order_id']);
            $table->string('order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Make order_id required and unique again
            $table->string('order_id')->nullable(false)->change();
            $table->unique('order_id');
        });
    }
};
