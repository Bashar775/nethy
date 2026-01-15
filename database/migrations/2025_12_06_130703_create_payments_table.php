<?php

use App\Models\Order;
use App\Models\SupplierOrder;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->enum('invoice_type',['customer_invoice','supplier_invoice']);
            $table->unsignedBigInteger('invoice_id');
            $table->enum('payable_type',['order','supplier_order']);
            $table->unsignedBigInteger('payable_id');//postgre
            $table->enum('payment_type',['incoming','outgoing']);
            $table->enum('payer_type',['customer','supplier']);
            $table->unsignedBigInteger('payer_id');
            $table->enum('status',['pending','completed','failed','refunded'])->default('pending');
            $table->date('payment_date');
            $table->string('payment_method');
            $table->string('currency',3)->default('SEK');
            $table->string('transaction_id')->nullable()->unique();
            $table->decimal('amount',10,2)->min(0);
            $table->string('notes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
