<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('symbol', 10)->index();
            $table->decimal('entry', 18, 8);
            $table->decimal('tp1', 18, 8)->nullable();
            $table->decimal('tp2', 18, 8)->nullable();
            $table->decimal('sl', 18, 8)->nullable();
            $table->string('status', 16)->default('active'); // active,tp1,tp2,sl,closed
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};

