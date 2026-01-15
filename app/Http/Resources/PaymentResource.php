<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'invoice_type'=>$this->invoice_type,
            'invoice_id'=>$this->invoice_id,
            'payable_type'=>$this->payable_type,
            'payable_id'=>$this->payable_id,
            'payment_type'=>$this->payment_type,
            'payer_type'=>$this->payer_type,
            'payer_id'=>$this->payer_id,
            'status'=>$this->status,
            'payment_date'=>$this->payment_date,
            'payment_method'=>$this->payment_method,
            'currency'=>$this->currency,
            'transaction_id'=>$this->transaction_id,
            'amount'=>$this->amount,
            'notes'=>$this->notes,
            'created_at'=>$this->created_at,
            'updated_at'=>$this->updated_at
        ];
    }
}
