<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierOrderResource;
use App\Models\Product;
use App\Models\StockMovment;
use App\Models\SupplierOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

class SupplierOrderController extends Controller
{
    use AuthorizesRequests;
    public function index()
    {
        $this->authorize('supplierOrder', User::class);
        return response()->json(['data'=>SupplierOrderResource::collection(SupplierOrder::all())],200);
    }
    public function show($id)
    {
        $this->authorize('supplierOrder', User::class);
        $order = SupplierOrder::findOrFail($id);
        $relatedProducts = $order->products;
        return response()->json(['order'=>SupplierOrderResource::make($order),'related_products' => $relatedProducts], 200);
    }
    public function store(Request $request)
    {
        $this->authorize('supplierOrder', User::class);
        $atts = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'currency' => 'nullable|string|max:3',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'string|nullable',
            'products' => 'nullable|array|min:1',
            'products.*.id' => 'required|exists:products,id|distinct',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.tax_rate'=> 'nullable|numeric|min:0|max:100'
        ]);
        try {
            $order = DB::transaction(function () use ($request, $atts, &$order) {
                $order = SupplierOrder::create([
                    'supplier_id' => $atts['supplier_id'],
                    'currency' => $atts['currency'] ?? 'SEK',
                    'supplier_order_number' =>'placeholder',
                    'payment_method' => $atts['payment_method'] ?? null,
                    'notes' => $atts['notes'] ?? null,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    // 'discount_amount' => 0,
                    'total_amount' => 0,
                    'order_date' => now(),
                    'status' => 'pending',
                ]);
                $supplier_order_number = 'S_ORD-'.date('Y').date('M') . '-'.$order->id;
                $order->supplier_order_number=$supplier_order_number;
                if (!$request->has('products') || count($atts['products']) === 0) {
                    return $order;
                }
                $number_of_items=0;
                foreach ($atts['products'] as $productData) {
                    $product = Product::findOrFail($productData['id']);
                    $number_of_items += $productData['quantity'];
                    // $discountAmount = 0;
                    // if ($product->discount_rate) {
                    //     $discountAmount = ($product->price * ($product->discount_rate / 100));
                    // }
                    $quantity = $productData['quantity'];
                    $lineSubtotal = $product->cost * $quantity;
                    $lineTax = $lineSubtotal * (($productData['tax_rate'] ?? 0) / 100);
                    $order->products()->attach($product->id, [
                        'quantity' => $quantity,
                        'unit_cost_price' => $product->cost,
                        'tax_rate' => $productData['tax_rate'] ?? 0,
                        // 'discount_amount' => $discountAmount,
                        'subtotal' => $lineSubtotal,
                        'tax_amount' => $lineTax,
                    ]);
                    // $order->discount_amount += $discountAmount;
                    $order->subtotal += $lineSubtotal;
                    $order->tax_amount += $lineTax;
                }
                $order->total_amount = ($order->subtotal + $order->tax_amount);
                $order->number_of_items = $number_of_items;
                $order->save();
                return $order;
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Order creation failed', 'error' => $e->getMessage()], 500);
        }
        return response()->json(['data' => $order, 'details' => $order->products()->get()]);
    }
    public function destroy($id)
    {
        $this->authorize('supplierOrder', User::class);
        $order = SupplierOrder::find($id);
        if(!$order){
            return response()->json(['message' => 'Order not found'], 404);
        }
        $order->products()->detach();
        $order->delete();
        return response()->json(['message' => 'Order deleted successfully'], 200);
    }
    public function confirm($id)
    {
        //add the policy later on
        $this->authorize('supplierOrder', User::class);
        $order = SupplierOrder::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }
        if ($order->status == 'confirmed') {
            return response()->json(['message' => 'you cannot confirm the same order twice'], 400);
        }
        try {
            DB::transaction(function () use ($order) {
                foreach ($order->products as $productData) {
                    $product = Product::findOrFail($productData->id);
                    $projectedQty = $product->stock_quantity + $productData['pivot']->quantity;
                    if ($projectedQty < 0) {
                        $product->status = 'alertstock';
                    } elseif ($projectedQty <= 0) {
                        $product->status = 'outofstock';
                    } elseif ($projectedQty < $product->stock_alert) {
                        $product->status = 'lowstock';
                    } else {
                        $product->status = 'instock';
                    }
                    $product->stock_quantity = $projectedQty;
                    $product->save();
                    StockMovment::create([
                        'product_id' => $product->id,
                        'related_type' => 'supplier_order',
                        'related_id' => $order->id,
                        'type' => 'in',
                        'quantity_ordered' => $productData['pivot']->quantity,
                        'quantity_in_stock' => $projectedQty,
                        'return' => false,
                    ]);
                }
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Order confirmation failed', 'error' => $e->getMessage()], 500);
        }
        $order->status = 'confirmed';
        $order->save();
        return response()->json(['message' => 'Order confirmed successfully'], 200);
    }
    public function cancel($id)
    {
        $this->authorize('supplierOrder', User::class);
        $order = SupplierOrder::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }
        if($order->status !== 'confirmed'){
            return response()->json(['message' => 'Only confirmed orders can be canceled'], 400);
        }
        try {
            DB::transaction(function () use ($order) {
                foreach ($order->products as $productData) {
                    $product = Product::findOrFail($productData->id);
                    $projectedQty = $product->stock_quantity - $productData['pivot']->quantity;
                    if ($projectedQty < 0) {
                        $product->status = 'alertstock';
                    } elseif ($projectedQty <= 0) {
                        $product->status = 'outofstock';
                    } elseif ($projectedQty < $product->stock_alert) {
                        $product->status = 'lowstock';
                    } else {
                        $product->status = 'instock';
                    }
                    $product->stock_quantity = $projectedQty;
                    $product->save();
                    StockMovment::create([
                        'product_id' => $product->id,
                        'related_type' => 'supplier_order',
                        'related_id' => $order->id,
                        'type' => 'out',
                        'quantity_ordered' => $productData['pivot']->quantity,
                        'quantity_in_stock' => $projectedQty,
                        'return' => true,
                    ]);
                }
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Order cancellation failed', 'error' => $e->getMessage()], 500);
        }
        $order->status = 'canceled';
        $order->save();
        return response()->json(['message' => 'Order canceled successfully'], 200);
    }
    public function addItem(Request $request, $id)
    {
        $this->authorize('supplierOrder', User::class);
        $atts = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'tax_rate'=> 'nullable|numeric|min:0|max:100'
        ]);
        $order = SupplierOrder::find($id);
        if(!$order){
            return response()->json(['message' => 'Order not found'], 404);
        }
        if($order->status =='confirmed'){
            return response()->json(['message' => 'Cannot modify confirmed order'], 400);
        }
        if ($order->products()->where('product_id', $atts['product_id'])->exists()) {
            return response()->json(['error' => 'This product is already included in the order if you want to modify the quantity you can remove the product cart and add another one.'], 422);
        }
        $product = Product::find($atts['product_id']);
        if(!$product){
            return response()->json(['message' => 'Product not found'], 404);
        }
        if ($order && $product) {
            // $discountAmount = 0;
            // if ($product->discount_rate) {
            //     $discountAmount = ($product->price * ($product->discount_rate / 100));
            // }
            $quantity = $atts['quantity'];
            $lineSubtotal = $product->cost * $quantity;
            $lineTax = $lineSubtotal * (($atts['tax_rate'] ?? 0) / 100);
            $order->products()->attach($product->id, [
                'quantity' => $quantity,
                'unit_cost_price' => $product->cost,
                'tax_rate' => $atts['tax_rate'] ?? 0,
                // 'discount_amount' => $discountAmount,
                'subtotal' => $lineSubtotal,
                'tax_amount' => $lineTax,
            ]);
            // Update order totals
            $order->number_of_items += 1;
            // $order->discount_amount += $discountAmount;
            $order->subtotal += $lineSubtotal;
            $order->tax_amount += $lineTax;
            $order->total_amount = ($order->subtotal + $order->tax_amount);
            $order->save();
            return response()->json(['message' => 'Item added to order successfully'], 200);
        } else {
            return response()->json(['message' => 'Order or Product not found'], 404);
        }
    }
    public function removeItem(Request $request, $id)
    {
        $this->authorize('supplierOrder', User::class);
        $atts = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);
        $order = SupplierOrder::find($id);
        if(!$order){
            return response()->json(['message' => 'Order not found'], 404);
        }
        if ($order->status == 'confirmed') {
            return response()->json(['message' => 'Cannot modify confirmed order'], 400);
        }
        $product = Product::find($atts['product_id']);
        if(!$product){
            return response()->json(['message' => 'Product not found'], 404);
        }
        if ($order && $product) {
            $pivotData = $order->products()->where('product_id', $product->id)->first()->pivot;
            $order->products()->detach($product->id);
            // Update order totals
            $order->number_of_items -= 1;
            // $order->discount_amount -= $pivotData->discount_amount;
            $order->subtotal -= $pivotData->subtotal;
            $order->tax_amount -= $pivotData->tax_amount;
            $order->total_amount = ($order->subtotal + $order->tax_amount);
            $order->save();
            return response()->json(['message' => 'Item removed from order successfully'], 200);
        } else {
            return response()->json(['message' => 'Order or Product not found'], 404);
        }
    }
    // public function updateStatus(Request $request,$id){
    //     $this->authorize('updateOrder', User::class);
    //     $atts = $request->validate([
    //         'status' => 'required|string|in:pending,shipped,canceled',
    //     ]);
    //     $order = Order::findOrFail($id);
    //     if ($order) {
    //         $order->status = $atts['status'];
    //         $order->save();
    //         return response()->json(['message' => 'Order status updated successfully'], 200);
    //     }else{
    //         return response()->json(['message' => 'Order not found'], 404);}
    // }
    public function update(Request $request, $id)
    {
        $this->authorize('supplierOrder', User::class);
        $atts = $request->validate([
            'currency' => 'nullable|string|max:3',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'string|nullable',
        ]);
        $order = SupplierOrder::findOrFail($id);
        if ($order) {
            if (isset($atts['currency'])) {
                $order->currency = $atts['currency'];
            }
            if (isset($atts['payment_method'])) {
                $order->payment_method = $atts['payment_method'];
            }
            if (isset($atts['notes'])) {
                $order->notes = $atts['notes'];
            }
            $order->save();
            return response()->json(['message' => 'Order updated successfully','order'=>SupplierOrderResource::make($order)], 200);
        } else {
            return response()->json(['message' => 'Order not found'], 404);
        }
    }
}
