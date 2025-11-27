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
        Schema::create('sale_barcode_scans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_detail_id');
            $table->string('barcode');
            $table->string('type');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_barcode_scans');
    }
}; 
