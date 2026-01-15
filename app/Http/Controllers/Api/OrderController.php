<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    use AuthorizesRequests;
    public function index()
    {
        $this->authorize('viewAnyOrder', User::class);
        return response()->json(['data' => OrderResource::collection(Order::all())], 200);
    }
    public function show($id)
    {
        $this->authorize('viewAnyOrder', User::class);
        $order = Order::findOrFail($id);
        $relatedProducts = $order->products;
        return response()->json(['data' => $relatedProducts], 200);
    }
    public function store(Request $request)
    {
        // $this->authorize('createOrder', User::class);
        $atts = $request->validate([
            'currency' => 'nullable|string|max:5',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'string|nullable',
            'products' => 'nullable|array|min:1',
            'products.*.id' => 'required|exists:products,id|distinct',
            'products.*.quantity' => 'required|integer|min:1',
        ]);
        try {
            $order = DB::transaction(function () use ($request, $atts, &$order) {
                Log::error('here 2 ');
                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'currency' => $atts['currency'] ?? 'SEK',
                    'order_number' => 'placeholder',
                    'payment_method' => $atts['payment_method'] ?? null,
                    'notes' => $atts['notes'] ?? null,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'order_date' => now(),
                    'status' => 'pending',
                ]);
                $orderNumber = 'ORD-' .date('Y').date('M').'-'. $order->id;
                $order->order_number=$orderNumber;
                if (!$request->has('products') || count($atts['products']) === 0) {
                    return $order;
                }
                $number_of_items=0;
                foreach ($atts['products'] as $productData) {
                    $product = Product::findOrFail($productData['id']);
                    $number_of_items+=$productData['quantity'];
                    $quantity = $productData['quantity'];
                    $lineSubtotal = $product->price * $quantity;
                    $lineTax = $lineSubtotal * (($product->tax_rate ?? 0) / 100);
                    $discountAmount = 0;
                    if ($product->discount_price) {
                        $discountAmount = ($lineSubtotal -($product->discount_price * $quantity));
                    }
                    $order->products()->attach($product->id, [
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                        'tax_rate' => $product->tax_rate,
                        'discount_amount' => $discountAmount,
                        'subtotal' => $lineSubtotal,
                        'tax_amount' => $lineTax,
                    ]);
                    $order->discount_amount += $discountAmount;
                    $order->subtotal += $lineSubtotal;
                    $order->tax_amount += $lineTax;
                }
                $order->total_amount = ($order->subtotal + $order->tax_amount) - $order->discount_amount;
                $order->number_of_items=$number_of_items;
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
        //add the policy later on
        $this->authorize('updateOrder', User::class);
        $order = Order::findOrFail($id);
        $order->products()->detach();
        $order->delete();
        return response()->json(['message' => 'Order deleted successfully'], 200);
    }
    public function confirm($id)
    {
        //add the policy later on
        $this->authorize('updateOrder', User::class);
        $order = Order::findOrFail($id);
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
                    if (!$product) {
                        return response()->json(['message' => 'Product not found'], 404);
                    }
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
                    $productsstatus[$product->name] = $product->status;
                    $product->save();
                    StockMovment::create([
                        'product_id' => $product->id,
                        'related_type' => 'order',
                        'related_id' => $order->id,
                        'type' => 'out',
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
        //add the policy later on
        $this->authorize('updateOrder', User::class);
        $order = Order::findOrFail($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }
        if ($order->status !== 'confirmed') {
            return response()->json(['message' => 'Only confirmed orders can be canceled'], 400);
        }
        try {
            DB::transaction(function () use ($order) {
                foreach ($order->products as $productData) {
                    $product = Product::findOrFail($productData->id);
                    if (!$product) {
                        return response()->json(['message' => 'Product not found'], 404);
                    }
                    $projectedQty = $product->stock_quantity + $productData['pivot']->quantity;
                    Log::error('product quantity ' . $product->stock_quantity);
                    Log::error('projected qty ' . $projectedQty);
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
                    $productsstatus[$product->name] = $product->status;
                    $product->save();
                    StockMovment::create([
                        'product_id' => $product->id,
                        'related_type' => 'order',
                        'related_id' => $order->id,
                        'type' => 'in',
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
        $this->authorize('updateOrder', User::class);
        $atts = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);
        $order = Order::find($id);
        if(!$order){
            return response()->json(['message'=>'Order not found'],404);
        }
        if($order->status =='confirmed'){
            return response()->json(['message'=>'confirmed orders cannot be modified'],400);
        }
        if ($order->products()->where('product_id', $atts['product_id'])->exists()) {
            return response()->json(['error' => 'This product is already included in the order if you want to modify the quantity you can remove the product cart and add another one.'], 422);
        }
        $product = Product::find($atts['product_id']);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        if ($order && $product) {
            $quantity = $atts['quantity'];
            $lineSubtotal = $product->price * $quantity;
            $lineTax = $lineSubtotal * ($product->tax_rate ?? 0 / 100);
                    $discountAmount = 0;
                    if ($product->discount_price) {
                        $discountAmount = ($lineSubtotal -($product->discount_price * $quantity));
                    }
            $order->products()->attach($product->id, [
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'tax_rate' => $product->tax_rate,
                'discount_amount' => $discountAmount,
                'subtotal' => $lineSubtotal,
                'tax_amount' => $lineTax,
            ]);
            // Update order totals
            $order->number_of_items += 1;
            $order->discount_amount += $discountAmount;
            $order->subtotal += $lineSubtotal;
            $order->tax_amount += $lineTax;
            $order->total_amount = ($order->subtotal + $order->tax_amount) - $order->discount_amount;
            $order->save();
            return response()->json(['message' => 'Item added to order successfully'], 200);
        } else {
            return response()->json(['message' => 'Order or Product not found'], 404);
        }
    }
    public function removeItem(Request $request, $id)
    {
        $this->authorize('updateOrder', User::class);
        $atts = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);
        $order = Order::find($id);
        if(!$order){
            return response()->json(['message'=>'Order not found'],404);
        }
        if ($order->status == 'confirmed') {
            return response()->json(['message' => 'confirmed orders cannot be modified'], 400);
        }
        $product = Product::find($atts['product_id']);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        if ($order && $product) {
            $pivotData = $order->products()->where('product_id', $product->id)->first()->pivot;
            $order->products()->detach($product->id);
            // Update order totals
            $order->number_of_items -= 1;
            $order->discount_amount -= $pivotData->discount_amount;
            $order->subtotal -= $pivotData->subtotal;
            $order->tax_amount -= $pivotData->tax_amount;
            $order->total_amount = ($order->subtotal + $order->tax_amount) - $order->discount_amount;
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
        $this->authorize('updateOrder', User::class);
        $atts = $request->validate([
            'currency' => 'nullable|string|max:3',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'string|nullable',
        ]);
        $order = Order::find($id);
        if(!$order){
            return response()->json(['message'=>'Order not found'],404);
        }
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
            return response()->json(['message' => 'Order updated successfully'], 200);
        } else {
            return response()->json(['message' => 'Order not found'], 404);
        }
    }
}
