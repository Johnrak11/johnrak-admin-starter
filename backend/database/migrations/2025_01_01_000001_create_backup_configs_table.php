<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('backup_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->boolean('enabled')->default(false);
            $table->string('provider', 50)->default('s3');
            $table->string('s3_access_key')->nullable();
            $table->string('s3_secret')->nullable();
            $table->string('s3_region')->nullable();
            $table->string('s3_bucket')->nullable();
            $table->string('s3_endpoint')->nullable();
            $table->string('s3_path_prefix')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_configs');
    }
};

