<?php

use App\Models\Category;
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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            //stock keeping unit
            $table->string('sku')->unique();
            $table->foreignIdFor(Category::class)->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('s_name');
            $table->text('description')->nullable()->default('no description available');
            $table->text('s_description')->nullable()->default('no description available');
            $table->decimal('price', 8, 2);
            $table->decimal('cost', 8, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->min(0)->max(100.0)->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->integer('stock_alert')->default(10);
            $table->string('unit')->nullable();
            $table->string('status')->default('instock'); //instock, outofstock, alertstock
            $table->decimal('product_rate',2,1)->min(0)->max(5.0)->default(0);
            $table->string('delivery_option')->nullable();
            //restrict this to be max 100
            $table->decimal('discount_price', 8, 2)->min(0)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
