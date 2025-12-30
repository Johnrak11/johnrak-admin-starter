<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('headline', 200)->nullable();
            $table->text('summary')->nullable();
            $table->string('location', 200)->nullable();
            $table->string('email_public', 255)->nullable();
            $table->string('phone_public', 50)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('github_url', 500)->nullable();
            $table->string('linkedin_url', 500)->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_profiles');
    }
};
