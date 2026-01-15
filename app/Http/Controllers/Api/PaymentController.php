<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PaymentController extends Controller
{
    use AuthorizesRequests;
    public function index()
    {
        $this->authorize('payment', User::class);
        return response()->json(['data' => PaymentResource::collection(Payment::all())]);
    }
    public function store(Request $request)
    {
        $this->authorize('payment', User::class);
        $atts = $request->validate([
            'invoice_type' => 'required|in:customer_invoice,supplier_invoice',
            'invoice_id' => 'required|integer',
            'payable_type' => 'required|in:order,supplier_order',
            'payable_id' => 'required|integer',
            'payment_type' => 'required|in:incoming,outgoing',
            'payer_type' => 'required|in:customer,supplier',
            'payer_id' => 'required|integer',
            'status' => 'required|in:pending,completed,failed,refunded',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'currency' => 'required|string|size:3',
            'transaction_id' => 'nullable|string|unique:payments,transaction_id',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        try {
            if ($atts['invoice_type'] == 'customer_invoice') {
                $invoice = Invoice::find($atts['invoice_id']);
                if (!$invoice) {
                    return response()->json(['message' => 'Customer Invoice not found'], 404);
                }
            } elseif ($atts['invoice_type'] == 'supplier_invoice') {
                $invoice = SupplierInvoice::find($atts['invoice_id']);
                if (!$invoice) {
                    return response()->json(['message' => 'Supplier Invoice not found'], 404);
                }
            }
            if ($atts['payable_type'] == 'order') {
                $payable = Order::find($atts['payable_id']);
                if (!$payable) {
                    return response()->json(['message' => 'Order not found'], 404);
                }
            } elseif ($atts['payable_type'] == 'supplier_order') {
                $payable = SupplierOrder::find($atts['payable_id']);
                if (!$payable) {
                    return response()->json(['message' => 'Supplier Order not found'], 404);
                }
            }
            if ($atts['payer_type'] == 'customer') {
                $payer = User::find($atts['payer_id']);
                if (!$payer) {
                    return response()->json(['message' => 'Customer not found'], 404);
                }
            } elseif ($atts['payer_type'] == 'supplier') {
                $payer = Supplier::find($atts['payer_id']);
                if (!$payer) {
                    return response()->json(['message' => 'Supplier not found'], 404);
                }
            }
            $payment = Payment::create([
                'invoice_type' => $atts['invoice_type'],
                'invoice_id' => $atts['invoice_id'],
                'payable_type' => $atts['payable_type'],
                'payable_id' => $atts['payable_id'],
                'payment_type' => $atts['payment_type'],
                'payer_type' => $atts['payer_type'],
                'payer_id' => $atts['payer_id'],
                'status' => $atts['status'],
                'payment_date' => $atts['payment_date'],
                'payment_method' => $atts['payment_method'],
                'currency' => $atts['currency'],
                'transaction_id' => $atts['transaction_id'] ?? null,
                'amount' => $atts['amount'],
                'notes' => $atts['notes'] ?? 'no notes',
            ]);
            return response()->json(['data' =>PaymentResource::make($payment)], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Server Error', 'error' => $e->getMessage()], 500);
        }
        return response()->json(['message' => 'Payment created'], 201);
    }
    public function show($id)
    {
        $this->authorize('payment', User::class);
        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }
        return response()->json(['data' => PaymentResource::make($payment)]);
    }
    public function destroy($id)
    {
        $this->authorize('payment', User::class);
        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }
        $payment->delete();
        return response()->json(['message' => 'Payment deleted']);
    }
    public function update(Request $request, $id)
    {
        $this->authorize('payment', User::class);
        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }
        $atts = $request->validate([
            'status' => 'in:pending,completed,failed,refunded',
            'payment_date' => 'date',
            'payment_method' => 'string',
            'currency' => 'string|size:3',
            'transaction_id' => 'nullable|string|unique:payments,transaction_id,' . $payment->id,
            'amount' => 'numeric|min:0',
            'notes' => 'nullable|string',
        ]);
        try{
        $payment->update($atts);
        }catch (\Exception $e){
            return response()->json(['message'=>'Server Error','error'=>$e->getMessage()],500);
        }
        return response()->json(['data' => PaymentResource::make($payment)]);
    }
}
