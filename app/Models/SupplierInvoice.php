<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierInvoice extends Model
{
    protected $guarded = [];

    public function supplier(){
        return $this->belongsTo(Supplier::class);
    }
    public function supplierOrder(){
        return $this->belongsTo(SupplierOrder::class);
    }
}
