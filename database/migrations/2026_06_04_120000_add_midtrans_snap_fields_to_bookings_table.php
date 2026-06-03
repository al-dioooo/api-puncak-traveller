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
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('payment_provider')->nullable()->after('payment_status');
            $table->string('midtrans_order_id')->nullable()->unique()->after('payment_provider');
            $table->string('snap_token')->nullable()->after('midtrans_order_id');
            $table->string('snap_redirect_url')->nullable()->after('snap_token');
            $table->string('midtrans_transaction_id')->nullable()->after('snap_redirect_url');
            $table->string('midtrans_payment_type')->nullable()->after('midtrans_transaction_id');
            $table->string('midtrans_status')->nullable()->after('midtrans_payment_type');
            $table->string('midtrans_fraud_status')->nullable()->after('midtrans_status');
            $table->json('midtrans_payload')->nullable()->after('midtrans_fraud_status');
            $table->timestamp('paid_at')->nullable()->after('cancelled_at');
            $table->timestamp('payment_failed_at')->nullable()->after('paid_at');
            $table->timestamp('stock_released_at')->nullable()->after('payment_failed_at');
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
