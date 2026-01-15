<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if(($request->has('show') && $request->get('show')==1)||($request->has('dashboard') && $request->get('dashboard')==1)){
            return [
                'id' => $this->id,
                'name' => $this->name,
                's_name' => $this->s_name,
                'description' => $this->description,
                's_description' => $this->s_description,
                'sku' => $this->sku,
                'price' => $this->price,
                'low_stock_alert_threshold'=>$this->stock_alert,
                'cost' => $this->cost,
                'stock_quantity' => $this->stock_quantity,
                'category' => $this->category->name,
                'tax_rate' => $this->tax_rate,
                'discount_price' =>$this->discount_price,
                'images' => ImageResource::collection($this->whenLoaded('images')),
                'product_rate' => $this->product_rate,
                'status' => $this->status,
                'created_at' => $this->created_at
            ];
        }else{
            return [
                'id'=>$this->id,
                'name' => $this->name,
                's_name'=> $this->s_name,
                'sku'=>$this->sku,
                'cost'=>$this->cost,
                'price' => $this->price,
                'stock_quantity' => $this->stock_quantity,
                'category' => $this->category->name,
                'tax_rate'=> $this->tax_rate,
                'discount_price' => $this->discount_price,
                'status' => $this->status,
                'created_at' => $this->created_at
            ];
        }
    }
    }

