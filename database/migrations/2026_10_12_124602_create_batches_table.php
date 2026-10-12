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
    Schema::create('batches', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sku_id')->constrained()->restrictOnDelete();
        $table->string('batch_no');
        $table->date('received_date');
        $table->date('expiry_date')->nullable();
        $table->string('supplier')->nullable();
        $table->text('notes')->nullable();
        $table->timestamps();

        $table->unique(['sku_id', 'batch_no']);
        $table->index('expiry_date');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
