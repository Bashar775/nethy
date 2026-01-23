<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ImageResource;
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
        $orders=Order::orderBy('updated_at', 'desc')->simplePaginate(10);
        return response()->json(['data' => OrderResource::collection($orders)], 200);
    }
    public function show($id)
    {
        $this->authorize('viewAnyOrder', User::class);
        $order = Order::find($id);
        if(!$order){
            return response()->json(['message'=>'Order not found'],404);
        }
        $relatedProducts = $order->products;
        foreach ($relatedProducts as $productData){
            $product=Product::find($productData->id);
            $images=ImageResource::collection($product->images);
            $productData['images']=$images;
        }
        return response()->json(['order'=>OrderResource::make($order),'products' => $relatedProducts], 200);
    }
    public function store(Request $request)
    {
        // $this->authorize('createOrder', User::class);
        //check for clients only creation of customer order
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
                    'status' => 'unchecked',
                ]);
                $orderNumber = 'ORD-' .date('Y').date('M').'-'. $order->id;
                $order->order_number=$orderNumber;
                $order->save();
                if (!$request->has('products') || count($atts['products']) === 0) {
                    return $order;
                }
                $number_of_items=0;
                foreach ($atts['products'] as $productData) {
                    $product = Product::findOrFail($productData['id']);
                    if($product->status=='deleted'){
                        throw new \Exception('there is a deleted product included in the order');
                    }
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
    // public function destroy($id)
    // {
    //     //add the policy later on
    //     $this->authorize('updateOrder', User::class);
    //     $order = Order::findOrFail($id);
    //     $order->products()->detach();
    //     $order->delete();
    //     return response()->json(['message' => 'Order deleted successfully'], 200);
    // }
    public function confirm(Request $request,$id)
    {
        //add the policy later on
        $this->authorize('updateOrder', User::class);
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }
        if ($order->status == 'confirmed') {
            return response()->json(['message' => 'you cannot confirm the same order twice'], 400);
        }
        if($order->status == 'unchecked'){
            return response()->json(['message'=>'you cannot confirm the order until the customer checkout it , anyway how are you seeing this :('],403);
        }
        try {
            DB::transaction(function () use ($order) {
                foreach ($order->products as $productData) {
                    $product = Product::findOrFail($productData->id);
                    if (!$product) {
                        return response()->json(['message' => 'Product not found'], 404);
                    }
                    $projectedQty = $product->stock_quantity - $productData['pivot']->quantity;
                    if($product->status=='deleted'){
                    }elseif ($projectedQty < 0) {
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
                        'notes'=>'customer order with number '.$order->order_number.' removed '.$productData['pivot']->quantity.' of product '.$product->name.' from the inventoery'
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
                    if($product->status=='deleted'){
                    }elseif ($projectedQty < 0) {
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
                        'notes'=>'canceling customer order with number '.$order->order_number.' added back '.$productData['pivot']->quantity.' of product '.$product->name.' to the inventoery'
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
    public function addItem(Request $request)
    {
        $atts = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);
        $order = Order::where('user_id',$request->user()->id)->where('status','unchecked')->first();

        if(!$order){
            $this->store($request);
            $order = Order::where('user_id',$request->user()->id)->where('status','unchecked')->first();
            if(!$order){
                return response()->json(['message'=>'sorry something went wrong call the support team'],422);
            }
        }
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'this is not your order to modify only the customer who made the order can modify it'], 403);
        }
        if ($order->status !== 'unchecked') {
            return response()->json(['message' => 'only unchecked orders can be modified by customer if your order is pending you can return it to unchecked state but if it is confirmed you need to call the support team to cancel your order'], 422);
        }
        if ($order->products()->where('product_id', $atts['product_id'])->exists()) {
            return response()->json(['error' => 'This product is already included in the order if you want to modify the quantity you can remove the product cart and add another one.'], 422);
        }
        $product = Product::find($atts['product_id']);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        if($product->status=='deleted'){
            return response()->json(['message'=>'the product sepcified is deleted from the system'],422);
        }
        if ($order && $product) {
            $quantity = $atts['quantity'];
            $lineSubtotal = $product->price * $quantity;
            $lineTax = $lineSubtotal * (($product->tax_rate ?? 0) / 100);
                    $discountAmount = 0;
                    if ($product->discount_price) {
                        $discountAmount = ($product->price - ($product->discount_price ?? $product->price))  * $quantity;
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
            $order->number_of_items += $quantity;
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
        $atts = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);
        $order = Order::find($id);
        if(!$order){
            return response()->json(['message'=>'Order not found'],404);
        }
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'this is not your order to modify only the customer who made the order can modify it'], 403);
        }
        if ($order->status !== 'unchecked') {
            return response()->json(['message' => 'only unchecked orders can be modified by customer if your order is pending you can return it to unchecked state but if it is confirmed you need to call the support team to cancel your order'], 422);
        }
        $product = Product::find($atts['product_id']);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        if ($order && $product) {
            $pivotData = $order->products()->where('product_id', $product->id)->first()->pivot;
            $order->products()->detach($product->id);
            // Update order totals
            $order->number_of_items -= $pivotData->quantity ;
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
        $atts = $request->validate([
            'order_number'=>'nullable|string|unique:orders,order_number,'.$id,
            'currency' => 'nullable|string|max:3',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'string|nullable',
        ]);
        $order = Order::find($id);
        if(!$order){
            return response()->json(['message'=>'Order not found'],404);
        }
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'this is not your order to modify only the customer who made the order can modify it'], 403);
        }
        if ($order->status !== 'unchecked') {
            return response()->json(['message' => 'only unchecked orders can be modified by customer if your order is pending you can return it to unchecked state but if it is confirmed you need to call the support team to cancel your order'], 422);
        }
        if ($order) {
            if(isset($atts['order_number'])){
                $order->order_number = $atts['order_number'];
            }
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
    public function checkout(Request $request,$id){
        $order=Order::where('user_id',$request->user()->id)->where('status','unchecked')->get();
        if(!$order){
            return response()->json(['message'=>'Order not found'],404);
        }
        if($order->count()>1){
            return response()->json(['message'=>'you have more than one unchecked order and this is problomatic pleas call the support team which is me who is writing the code :)'],422);
        }
        $order=$order->first();
        if(!$order){
            return response()->json(['message'=>'Order not found'],404);
        }
        $order->status= 'pending';
        $order->save();
        return response()->json(['message'=>'your order is in teh queue to be confirmed , our sales team will call you soon to complete the order'],200);
    }
    // public function uncheckout(Request $request,$id){
    //     $order=Order::find($id);
    //     if(!$order){
    //         return response()->json(['message'=>'order not found'],404);
    //     }
    //     if($order->user_id !== $request->user()->id){
    //         return response()->json(['message'=>'This is not your order'],403);
    //     }
    //     if($order->status!=='pending'){
    //         return response()->json(['message'=>'Only pending orders can be unchecked if it is confirmed you need to call the support team']);
    //     }
    //     $order->status=='unchecked';
    //     $order->save();
    //     return response()->json(['message'=>'your order is back to UNCHECKED state , you can modify it now'],200);
    // }
    public function myCart(Request $request){
        $orders=$request->user()->orders()->where('status','unchecked')->get();
        if($orders->count()>1){
            return response()->json(['message'=>'there is a problem you have more than one cart'],422);
        }
        $order=$orders->first();
        if(!$order){
            return response()->json(['message'=>'you have no cart yet'],404);
        }
        $relatedProducts = $order->products;
        foreach ($relatedProducts as $productData) {
            $product = Product::find($productData->id);
            $images = ImageResource::collection($product->images);
            $productData['images'] = $images;
        }
        return response()->json(['order'=>OrderResource::make($order),'related_products'=> $relatedProducts]);
    }
    public function myHistory(Request $request){
        $orders=Order::where('user_id',$request->user()->id)->get();
        return response()->json(['data'=>OrderResource::collection($orders)]);
    }
}
