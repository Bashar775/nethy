<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Product extends Model
{
    use HasFactory;
    protected $guarded = [];
    public function orders(){
        Log::error('ay you');
        return $this->belongsToMany(Order::class,'order_product')->withPivot('quantity', 'unit_price', 'tax_rate', 'tax_amount')->withTimestamps();
    }
    public function images(){
        return $this->hasMany(Image::class);
    }
    public function category(){
        return $this->belongsTo(Category::class);
    }
    public function stockMovments(){
        return $this->hasMany(StockMovment::class);
    }
    public function supplierOrders(){
        return $this->belongsToMany(SupplierOrder::class, 'product_supplier_order')
            ->withPivot('quantity', 'unit_price', 'tax_rate', 'tax_amount')->withTimestamps();
    }
}
