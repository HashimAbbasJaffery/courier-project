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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId("shipment_id")->constrained("shipments")->onDelete("cascade");
            $table->foreignId("material_id")->constrained("materials")->onDelete("cascade");
            $table->foreignId("vendor_id")->constrained("vendors")->onDelete("cascade");
            $table->foreignId("platform_id")->constrained("platforms")->onDelete("cascade");
            $table->string("item_name");
            $table->float("purchase_cost")->default(0);
            $table->float("item_price")->default(0);
            $table->float("material_price")->default(0); // Assuming this is the price of the material
            $table->float("profit")->default(0); // Calculated profit
            $table->float("total_amount")->default(0); // Total cost of the item
            $table->float("advance_payment")->default(0); // Advance payment for the item
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
