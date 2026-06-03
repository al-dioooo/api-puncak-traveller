<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('payment_provider')->nullable();
            $table->string('midtrans_order_id')->nullable();
            $table->string('snap_token')->nullable();
            $table->string('snap_redirect_url')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('midtrans_payment_type')->nullable();
            $table->string('midtrans_status')->nullable();
            $table->string('midtrans_fraud_status')->nullable();
            $table->json('midtrans_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('payment_failed_at')->nullable();
            $table->timestamp('stock_released_at')->nullable();

            DB::getDriverName() === 'mongodb'
                ? $table->unique('midtrans_order_id', null, null, ['partialFilterExpression' => ['midtrans_order_id' => ['$type' => 'string']]])
                : $table->unique('midtrans_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropUnique(['midtrans_order_id']);
            $table->dropColumn([
                'payment_provider',
                'midtrans_order_id',
                'snap_token',
                'snap_redirect_url',
                'midtrans_transaction_id',
                'midtrans_payment_type',
                'midtrans_status',
                'midtrans_fraud_status',
                'midtrans_payload',
                'paid_at',
                'payment_failed_at',
                'stock_released_at',
            ]);
        });
    }
};
