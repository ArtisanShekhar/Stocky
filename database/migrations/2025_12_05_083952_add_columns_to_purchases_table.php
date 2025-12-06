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
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('vehicle_no')->nullable();
            $table->string('station')->nullable();
            $table->string('place_of_supply')->nullable();
            $table->string('e_way_bill_no')->nullable();
            $table->boolean('reverse_charge')->nullable();
            $table->string('order_no')->nullable();
            $table->string('gr_rr_no')->nullable();
            $table->date('order_date')->nullable();
            $table->string('transport')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
        $table->dropColumn(['transport','vehicle_no', 'order_date', 'gr_rr_no', 'order_no', 'reverse_charge', 'e_way_bill_no', 'place_of_supply', 'station']);
        });
    }
};
