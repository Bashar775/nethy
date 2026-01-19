<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class AnalysisController extends Controller
{
    use AuthorizesRequests;
    public function all(Request $request){
        $this->authorize('analysis',User::class);
        $atts=$request->validate([
            'from'=>'required_with:to|date',
            'to'=> 'required_with:from|date|after_or_equal:from',
        ]);
        if(!isset($atts['from'])){
            $atts['from']=now()->subMonth();
        }
        if(!isset($atts['to'])){
            $atts['to']=now();
        }
        $confirmedOrders=\App\Models\Order::where('status','confirmed')
            ->whereBetween('order_date',[$atts['from'],$atts['to']])
            ->get();
            $totalamount=0;
            foreach($confirmedOrders as $order){
                $totalamount += $order->total_amount;
            }
        $totalRevanue=$totalamount;
        $totalCustomerOrders=$confirmedOrders->count();
        $supplierConfirmedOrders=\App\Models\SupplierOrder::where('status','confirmed')
            ->whereBetween('order_date',[$atts['from'],$atts['to']])
            ->get();
        $totalamountSpent=0;
        foreach($supplierConfirmedOrders as $sorder){
            $totalamountSpent += $sorder->total_amount;
        }
        $totalProfit=$totalamount-$totalamountSpent;
        $newUsers=User::whereBetween('created_at',[$atts['from'],$atts['to']])->get()->count();
        $productsSold=0;
        foreach($confirmedOrders as $order){
            $productsSold += $order->number_of_items;
        }
        $customers=User::where('is_employee',false)
            ->whereBetween('created_at',[$atts['from'],$atts['to']])
            ->get();
            $payingCustomers=0;
        foreach($customers as $customer){
            $customerOrders=\App\Models\Order::where('user_id',$customer->id)
                ->where('status','confirmed')
                ->whereBetween('order_date',[$atts['from'],$atts['to']])
                ->get();
            if($customerOrders->count()>0){
                $payingCustomers +=1;
            }
        }
        return response()->json(['Total Revanue'=>$totalRevanue,'Total Profit'=>$totalProfit,'Total Customer Orders'=>$totalCustomerOrders,'New Users'=>$newUsers,'Products Sold'=>$productsSold,'number of customers'=>$customers->count(),'number of paying customers'=>$payingCustomers],200);

    }
    // public function monthlyRevanue(Request $request){
    //     $this->authorize('analysis',User::class);
    //     $atts=$request->validate([
    //         'year'=>'nullable|integer|min:2000|max:'.now()->year,
    //     ]);
    //     if(!isset($atts['year'])){
    //         $atts['year']=now()->year;
    //     }
    //     $monthlyRevanue=[];
    //     for($month=1;$month<=12;$month++){
    //         $confirmedOrders=\App\Models\Order::where('status','confirmed')
    //             ->whereYear('order_date',$atts['year'])
    //             ->whereMonth('order_date',$month)
    //             ->get();
    //         $totalamount=0;
    //         foreach($confirmedOrders as $order){
    //             $totalamount += $order->total_amount;
    //         }
    //         $monthlyRevanue[]=[
    //             'month'=>$month,
    //             'total_revanue'=>$totalamount
    //         ];
    //     }
    //     return response()->json(['monthly_revanue'=>$monthlyRevanue],200);
    // }
    public function monthlyProfit(Request $request){
        $this->authorize('analysis',User::class);
        $atts=$request->validate([
            'year'=>'nullable|integer|min:2000|max:'.now()->year,
        ]);
        if(!isset($atts['year'])){
            $atts['year']=now()->year;
        }
        $monthlyProfit=[];
        for($month=1;$month<=12;$month++){
            $confirmedOrders=\App\Models\Order::where('status','confirmed')
                ->whereYear('order_date',$atts['year'])
                ->whereMonth('order_date',$month)
                ->get();
            $totalamount=0;
            foreach($confirmedOrders as $order){
                $totalamount += $order->total_amount;
            }
            $supplierConfirmedOrders=\App\Models\SupplierOrder::where('status','confirmed')
                ->whereYear('order_date',$atts['year'])
                ->whereMonth('order_date',$month)
                ->get();
            $totalamountSpent=0;
            foreach($supplierConfirmedOrders as $sorder){
                $totalamountSpent += $sorder->total_amount;
            }
            $monthlyProfit[]=[
                'month'=>$month,
                'total_profit'=>$totalamount-$totalamountSpent,
                'total_revanue'=>$totalamount
            ];
        }
        return response()->json(['monthly_profit'=>$monthlyProfit],200);
    }
    public function numberOfProductsPerCategory(){
        $categories=Category::all();
        $numOfProducts=0;
        $data=[];
        foreach($categories as $category){
            $numOfProducts += $category->products->count();
            $data[]=[
                'category'=>$category->name,
                'number_of_products'=>$category->products->count()
            ];
        }
        return response()->json(['total_number_of_products'=>$numOfProducts,'data'=>$data],200);
    }
    public function totalRevanue(){
        $this->authorize('analysis',User::class);
        $confirmedOrders=\App\Models\Order::where('status','confirmed')->get();
        $totalamount=0;
        foreach($confirmedOrders as $order){
            $totalamount += $order->total_amount;
        }
        return response()->json(['total_revanue'=>$totalamount],200);
    }
    public function totalcustomers(){
        $this->authorize('analysis',User::class);
        $customers=User::where('is_employee',false)->get();
        return response()->json(['total_customers'=>$customers->count()],200);
    }
    public function orderstoday(){
        $this->authorize('analysis',User::class);
        $confirmedOrders=\App\Models\Order::where('status','confirmed')
            ->whereDate('order_date',now()->toDateString())
            ->get();
        return response()->json(['total_orders_today'=>$confirmedOrders->count()],200);
    }
    public function fiverecentorders(){
        $this->authorize('analysis',User::class);
        $recentOrders=\App\Models\Order::where('status','confirmed')
            ->orderBy('order_date','desc')
            ->take(5)
            ->get();
        return response()->json(['data'=>\App\Http\Resources\OrderResource::collection($recentOrders)],200);
    }
    public function topProducts(){
        $this->authorize('analysis',User::class);
        $products=Product::all();
        $maxproduct=[];
        foreach($products as $product){
            $totalSold=0;
            $orders=$product->orders()->where('status','confirmed')->get();
            foreach($orders as $order){
                $totalSold += $order->pivot->quantity;
            }
            $maxproduct[]=[
                'product_id'=>$product->id,
                'product_name'=>$product->name,
                'total_sold'=>$totalSold
            ];
        }
        $sorted=collect($maxproduct)->sortByDesc('total_sold');
        $sorted=$sorted->values()->all();
        return response()->json(['data'=>$sorted],200);
    }
    public function totalProducts(){
        return response()->json(['data'=>Product::all()->count()]);
    }
    public function salesToOrders(Request $request){
        $this->authorize('analysis', User::class);
        $atts = $request->validate([
            'year' => 'nullable|integer|min:2000|max:' . now()->year,
        ]);
        if (!isset($atts['year'])) {
            $atts['year'] = now()->year;
        }
        $salesToOrders = [];
        for ($month = 1; $month <= 12; $month++) {
            $confirmedOrders = \App\Models\Order::where('status', 'confirmed')
                ->whereYear('order_date', $atts['year'])
                ->whereMonth('order_date', $month)
                ->get();
            $totalamount = 0;
            foreach ($confirmedOrders as $order) {
                $totalamount += $order->total_amount;
            }
            $salesToOrders[] = [
                'month' => $month,
                'sales' => $totalamount,
                'orders'=>$confirmedOrders->count()
            ];
        }
        return response()->json(['monthly_revanue' => $salesToOrders], 200);
    }
}
