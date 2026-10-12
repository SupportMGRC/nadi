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
    Schema::create('stock_movements', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sku_id')->constrained()->restrictOnDelete();
        $table->foreignId('batch_id')->nullable()->constrained()->restrictOnDelete();
        $table->foreignId('location_id')->constrained()->restrictOnDelete();
        $table->integer('qty');                         // + in, − out
        $table->string('type');                         // receive, sale, adjustment, ...
        $table->nullableMorphs('reference');            // link to an order, return, etc. (later)
        $table->text('notes')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();

        $table->index(['sku_id', 'batch_id', 'location_id']);
        $table->index('type');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
