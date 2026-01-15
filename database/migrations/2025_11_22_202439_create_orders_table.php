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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique()->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->Integer('number_of_items')->min(0)->default(0);
            $table->decimal('subtotal', 10, 2)->min(0)->default(0);
            $table->decimal('tax_amount', 10, 2)->min(0)->default(0);
            $table->decimal('discount_amount', 10, 2)->min(0)->default(0);
            $table->decimal('total_amount', 10, 2)->min(0)->default(0);   // total=subtotal + tax - discount
            $table->string('currency', 3)->default('SEK');
            $table->string('payment_method')->nullable();
            $table->date('order_date');
            $table->string('status')->default('pending');//pendening , confirmed, cancelled,shipped
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('order_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_rate', 5, 2)->min(0)->max(100.0)->default(0);   // e.g. 25.00
            $table->decimal('discount_amount',8,2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0); // calculated per line
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_product');
        Schema::dropIfExists('orders');
    }
};
