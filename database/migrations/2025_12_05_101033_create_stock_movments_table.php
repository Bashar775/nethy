<?php

use App\Models\Order;
use App\Models\Product;
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
        Schema::create('stock_movments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Product::class)->constrained()->onDelete('cascade');
            $table->string('related_type'); // 'order' or 'supplier_order'
            $table->unsignedBigInteger('related_id')->nullable(); // order id or supplier order id
            $table->enum('type', ['in', 'out']); // supplier in, customer out
            $table->boolean('return')->default(false);
            $table->integer('quantity_ordered')->min(0);
            $table->integer('quantity_in_stock');
            $table->string('notes')->nullable(); // in case costumer or supplier canceled
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movments');
    }
};
