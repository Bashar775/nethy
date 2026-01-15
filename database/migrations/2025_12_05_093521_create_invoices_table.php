<?php

use App\Models\Order;
use App\Models\User;
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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(Order::class)->constrained()->onDelete('cascade');
            $table->string('invoice_number')->unique(); // e.g. INV-2025-0001
            $table->date('invoice_date');
            $table->decimal('subtotal', 10, 2)->min(0);
            $table->decimal('tax_amount', 10, 2)->min(0)->default(0);
            $table->decimal('discount_amount', 10, 2)->min(0)->default(0);
            $table->decimal('total_amount', 10, 2)->min(0);
            $table->string('currency', 3)->default('SEK');
            $table->string('payment_status')->default('unpaid'); // unpaid, paid, overdue
            $table->date('due_date')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
