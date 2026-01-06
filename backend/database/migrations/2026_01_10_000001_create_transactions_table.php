<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Can be null for test payments
            $table->string('order_id')->nullable(); // Order identifier (can be null for payments without order)
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'error'])->default('pending');
            $table->string('transaction_id')->nullable()->unique(); // ABA transaction ID (for idempotency)
            $table->string('payer_name')->nullable();
            $table->string('payer_phone')->nullable();
            $table->text('remark')->nullable(); // Order ID stored in remark
            $table->string('khqr_string')->nullable(); // Generated KHQR string
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable(); // Additional data (location, apv, etc)
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('transaction_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
