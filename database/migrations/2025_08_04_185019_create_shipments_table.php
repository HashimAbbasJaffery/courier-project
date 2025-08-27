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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string("consignee_name");
            $table->string("consignee_phone");
            $table->string("consignee_address");
            $table->string("destination_city");
            $table->string("shipment_type");
            $table->string("division");
            $table->string("product_description");
            $table->float("weight");
            $table->integer("no_of_pieces")->default(1);
            $table->float("cod_amount")->default(0);
            $table->string("tracking_number")->nullable();
            $table->string("special_instructions")->nullable();
            $table->string("slip_link");
            $table->foreignId("courier_id")->constrained("couriers")->onDelete("cascade");
            $table->string("order_id")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
