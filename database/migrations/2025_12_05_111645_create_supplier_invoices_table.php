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
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique(); // e.g. S_INV-2025-0001
            $table->date('invoice_date');
            $table->decimal('subtotal', 10, 2)->min(0);
            $table->decimal('tax_amount', 10, 2)->min(0);
            $table->decimal('total_amount', 10, 2)->min(0);
            $table->string('currency', 3)->default('SEK');
            $table->date('due_date')->nullable();
            $table->string('payment_status')->default('unpaid'); //paid, unpaid , over due
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
    }
};
