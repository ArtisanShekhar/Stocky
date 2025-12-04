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
        Schema::table('sales', function (Blueprint $table) {
            $table->string('irn_number')->nullable();
            $table->string('ack_no')->nullable();
            $table->date('ack_date')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('dated')->nullable();
            $table->text('delivery_note')->nullable();
            $table->string('mode_terms_of_payment')->nullable();
            $table->string('reference_no')->nullable();
            $table->date('reference_date')->nullable();
            $table->text('other_references')->nullable();
            $table->string('buyers_order_no')->nullable();
            $table->date('order_dated')->nullable();
            $table->string('dispatch_doc_no')->nullable();
            $table->date('delivery_note_date')->nullable();
            $table->string('dispatched_through')->nullable();
            $table->string('destination')->nullable();
            $table->string('terms_of_delivery')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['irn_number', 'ack_no', 'ack_date', 'invoice_number', 'dated', 'delivery_note', 'mode_terms_of_payment', 'reference_no', 'reference_date', 'other_references', 'buyers_order_no', 'order_dated', 'dispatch_doc_no', 'delivery_note_date', 'dispatched_through', 'destination', 'terms_of_delivery']);
        });
    }
};
