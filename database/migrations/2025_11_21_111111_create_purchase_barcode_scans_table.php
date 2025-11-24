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
        Schema::create('purchase_barcode_scans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_detail_id');
            $table->string('barcode');
            $table->enum('type', ['indoor', 'outdoor'])->nullable();
            $table->timestamps();

            // $table->foreign('purchase_detail_id')->references('id')->on('purchase_details')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_barcode_scans');
    }
};
