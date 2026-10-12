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
    Schema::create('bundle_components', function (Blueprint $table) {
        $table->id();
        $table->foreignId('bundle_sku_id')->constrained('skus')->cascadeOnDelete();
        $table->foreignId('component_sku_id')->constrained('skus')->restrictOnDelete();
        $table->unsignedInteger('qty')->default(1);
        $table->timestamps();

        $table->unique(['bundle_sku_id', 'component_sku_id']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_components');
    }
};
