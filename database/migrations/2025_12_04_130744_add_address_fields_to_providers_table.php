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
        Schema::table('providers', function (Blueprint $table) {
            $table->string('shipping_gstin')->nullable();
            $table->string('shipping_state_name')->nullable();
            $table->string('shipping_state_code')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('billing_gstin')->nullable();
            $table->string('billing_state_name')->nullable();
            $table->string('billing_state_code')->nullable();
            $table->text('billing_address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn(['shipping_gstin', 'shipping_state_name', 'shipping_state_code', 'shipping_address', 'billing_gstin', 'billing_state_name', 'billing_state_code', 'billing_address']);
        });
    }
};
