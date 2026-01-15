<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class InvoiceController extends Controller
{
    use AuthorizesRequests;
    public function index(){
        $this->authorize('customerInvoice',User::class);
        $updated=Invoice::where('due_date','<',now())->where('payment_status','unpaid')->get();
        foreach($updated as $invoice){
            $invoice->payment_status='overdue';
            $invoice->save();
        }
        return response()->json(['data'=>InvoiceResource::collection(Invoice::all())],200);
    }
    public function generate(Request $request){
        $this->authorize('customerInvoice',User::class);
        $atts=$request->validate([
            'order_id'=>'required|exists:orders,id',
            'due_date'=>'nullable|date',
            'notes'=>'nullable|string',
        ]);
        $order=Order::findOrFail($atts['order_id']);
        if(!$order){
            return response()->json(['message'=>'order not found to generate the invoice'],404);
        }
        try{
        $invoice=Invoice::create([
            'user_id'=>$order->user_id,
            'order_id'=>$order->id,
            'invoice_number'=>'placeholder',
            'invoice_date'=>now(),
            'subtotal'=>$order->subtotal,
            'tax_amount'=>$order->tax_amount,
            'discount_amount'=>$order->discount_amount,
            'total_amount'=>$order->total_amount,
            'currnecy'=>$order->currency ?? 'SEK',
            'due_date'=>$atts['due_date']??null,
            'payment_status'=>'unpaid',
            'notes'=>$atts['notes']??'Invoice generated for order #'.$order->id
        ]);
        $invoice->invoice_number='INV-'.date('Y').date('M').'-'.$invoice->id;
        $invoice->save();
        }catch(\Exception $e){
            return response()->json(['message'=>'Error generating invoice: '.$e->getMessage()],500);
        }
        return response()->json(['message'=>'Invoice created succefully','data'=>InvoiceResource::make($invoice)],201);
    }
    public function show($id){
        $this->authorize('customerInvoice',User::class);
        $invoice=Invoice::findOrFail($id);
        if(!$invoice){
            return response()->json(['message'=>'Invoice not found'],404);
        }
        return response()->json(['data'=>InvoiceResource::make($invoice)],200);
    }
    public function destroy($id){
        $this->authorize('customerInvoice',User::class);
        $invoice=Invoice::findOrFail($id);
        if(!$invoice){
            return response()->json(['message'=>'Invoice not found'],404);
        }
        try{
        $invoice->delete();}catch(\Exception $e){
            return response()->json(['message'=>'Error deleting invoice: '.$e->getMessage()],500);
        }
        return response()->json(['message'=>'Invoice deleted successfully'],200);
    }
    public function changeStatus(Request $request,$id){
        $this->authorize('customerInvoice',User::class);
        $invoice=Invoice::findOrFail($id);
        if(!$invoice){
            return response()->json(['message'=>'Invoice not found'],404);
        }
        $atts=$request->validate([
            'payment_status'=>'required|in:unpaid,paid,overdue'
        ]);
        $invoice->payment_status=$atts['payment_status'];
        $invoice->save();
        return response()->json(['message'=>'Invoice status updated successfully','data'=>InvoiceResource::make($invoice)],200);
    }
    public function update(Request $request,$id){
        $this->authorize('customerInvoice',User::class);
        $invoice=Invoice::findOrFail($id);
        if(!$invoice){
            return response()->json(['message'=>'Invoice not found'],404);
        }
        $atts=$request->validate([
            'due_date'=>'nullable|date',
            'notes'=>'nullable|string',
            'currency'=>'nullable|string|size:3',
            'payment_status'=>'nullable|in:unpaid,paid,overdue',
            'invoice_date'=>'nullable|date',
            'invoice_number'=>'nullable|string|unique:invoices,invoice_number,'.$invoice->id,
            'subtotal'=>'nullable|numeric|min:0',
            'tax_amount'=>'nullable|numeric|min:0',
            'discount_amount'=>'nullable|numeric|min:0',
            'total_amount'=>'nullable|numeric|min:0',
        ]);
        try{
        $invoice->update($atts);
        $invoice->save();
        }catch(\Exception $e){
            return response()->json(['message'=>'Error updating invoice: '.$e->getMessage()],500);
        }
        return response()->json(['message'=>'Invoice updated successfully','data'=>InvoiceResource::make($invoice)],200);
    }
    public function store(Request $request)
    {
        $this->authorize('customerInvoice', User::class);
        $atts = $request->validate([
            'user_id' => 'required|exists:users,id',
            'order_id' => 'required|exists:orders,id',
            'invoice_number' => 'required|string|unique:invoices,invoice_number',
            'invoice_date' => 'required|date',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'due_date' => 'nullable|date',
            'payment_status' => 'required|in:unpaid,paid,overdue',
            'notes' => 'nullable|string',
        ]);
        try {
            $invoice = Invoice::create($atts);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating invoice: ' . $e->getMessage()], 500);
        }
        return response()->json(['message' => 'Invoice created successfully', 'data' => InvoiceResource::make($invoice)], 201);
    }
}
