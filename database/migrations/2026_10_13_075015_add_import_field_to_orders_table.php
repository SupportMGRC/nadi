<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->nullable()->after('status');              // Shopify: paid, refunded...
            $table->string('fulfillment_status')->nullable()->after('payment_status');  // Shopify: unfulfilled, fulfilled...
            $table->timestamp('imported_at')->nullable()->after('cancelled_at');

            // The same Shopify/TikTok order can only exist once
            $table->unique(['channel', 'external_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['channel', 'external_ref']);
            $table->dropColumn(['payment_status', 'fulfillment_status', 'imported_at']);
        });
    }
};