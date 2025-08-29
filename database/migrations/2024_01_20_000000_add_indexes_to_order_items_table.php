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
        Schema::table('order_items', function (Blueprint $table) {
            // Add indexes for better performance
            $table->index('shipment_id');
            $table->index('vendor_id');
            $table->index('material_id');
            
            // Composite index for common queries
            $table->index(['shipment_id', 'vendor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['shipment_id']);
            $table->dropIndex(['vendor_id']);
            $table->dropIndex(['material_id']);
            $table->dropIndex(['shipment_id', 'vendor_id']);
        });
    }
};








