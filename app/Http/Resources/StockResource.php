<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'product_id'=>$this->product_id,
            'related_type'=>$this->related_type,
            'related_id'=>$this->related_id,
            'type'=>$this->type,
            'return'=>$this->return,
            'quantity_ordered'=>$this->quantity_ordered,
            'quantity_in_stock'=>$this->quantity_in_stock,
            'notes'=>$this->quantity_in_stock,
            'created_at'=>$this->created_at,
            'updated_at'=>$this->updated_at,

        ];
    }
}
