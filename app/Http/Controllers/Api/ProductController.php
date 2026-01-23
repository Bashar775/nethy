<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Image;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    use AuthorizesRequests;
    public function index()
    {
        $products=Product::all();
        $products=$products->simplePaginate(10);
        return response()->json(['data' => ProductResource::collection($products)], 200);
    }
    public function show(Request $request, $id)
    {
        $request['show']=1;
        $product = Product::with('images')->findOrFail($id);
        return response()->json(['data' => ProductResource::make($product)], 200);
    }
    public function indexWebsite(Request $request){
        $request['show']=1;
        $products=Product::where('status','!=','deleted')->with('images')->simplePaginate(10);
        return response()->json(['date'=>ProductResource::collection($products)]);
    }
    public function mainPage(Request $request){
        $request['show']=1;
        $categories=Category::all();
        $products=[];
        foreach($categories as $category){
        $products[]=['category'=>$category->name,'products'=>ProductResource::collection(Product::where('status','!=','deleted')
        ->where('category_id',$category->id)
        ->orderBy('product_rate','desc')
        ->take(10)
        ->with('images')
        ->get())];
        }
        return response()->json(['date'=>$products]);
    }
    public function categoryFilter(Request $request){
        $atts=$request->validate([
            'categories'=>'array|required|min:0',
            'categories.*'=>'required|integer|distinct|exists:categories,id'
        ]);
        $request['show']=1;
        $products=[];
        foreach($atts['categories'] as $category_id){
            $category=Category::find($category_id);
            $products[]=[
                'category'=>$category->name,
                'products'=> ProductResource::collection(Product::where('status', '!=', 'deleted')
                    ->where('category_id', $category->id)
                    ->orderBy('product_rate', 'desc')
                    ->with('images')
                    ->get())
            ];
        }
        return response()->json(['date'=>$products],200);
    }
    public function search(Request $request){
        $atts=$request->validate([
            'value'=>'required|string|max:255'
        ]);
        $request['show']=1;
        $products=Product::where('name','LIKE',"%{$atts['value']}%")
        ->orWhere('s_name','LIKE', "%{$atts['value']}%")
        ->with('images')
        ->get();
        $products=$products->where('status','!=','deleted');
        if($products->count()==0){
            return response()->json(['message'=>'there is no product with this name'],404);
        }
        return response()->json(['data'=>ProductResource::collection($products)]);

    }
    public function store(Request $request)
    {
        $this->authorize('createProduct', User::class);
        $atts = $request->validate([
            'name' => 'required_without:s_name|string|max:255',
            's_name' => 'required_without:name|string|max:255',
            'description' => 'nullable|string',
            's_description' => 'nullable|string',
            'sku'=>'nullable|string|unique:products,sku',
            'price' => 'required|numeric|min:0|gt:cost',
            'cost'=> 'required|numeric|min:0|lt:price',
            'category' => 'required|string',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_alert_threshold'=>'nullable|integer|min:0',
            'delivery_option' => 'nullable|string',
            'tax_rate' => 'nullable|numeric|max:100|min:0',
            'product_rate' => 'nullable|numeric|max:5|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048|mimes:jpeg,png,jpg,webp',
        ]);
        try {
            $product = DB::transaction(function () use ($atts, &$product) {
                $category = Category::where('name', $atts['category'])->orWhere('s_name', $atts['category'])->first();
                if (!$category) {
                    $category = Category::create([
                        'name' => $atts['category'],
                        's_name' => $atts['category'],
                        'enabled' => true,
                    ]);
                    $category_id=$category->id;
                }else{
                    $category_id=$category->id;
                }
                if (isset($atts['discount_price'])) {
                    if ($atts['discount_price'] > $atts['price']) {
                        $atts['discount_price'] = $atts['price'];
                    }
                }
                $atts['stock_alert']=$atts['low_stock_alert_threshold'] ?? 10;
                $product= Product::create([
                    'name' => $atts['name'] ?? $atts['s_name'],
                    's_name' => $atts['s_name'] ?? $atts['name'],
                    'sku'=> $atts['sku'] ?? 'placeholder',
                    'description' => $atts['description'] ?? null,
                    's_description' => $atts['s_description'] ?? null,
                    'price' => $atts['price'],
                    'cost' => $atts['cost'],
                    'product_rate' => $atts['product_rate'] ?? 0,
                    'status' => $atts['status'] ?? 'instock',
                    'category_id' => $category_id,
                    'stock_quantity' => $atts['stock_quantity'] ?? 0,
                    'stock_alert'=>$atts['stock_alert'] ?? 10,
                    'delivery_option' => $atts['delivery_option'] ?? null,
                    'tax_rate' => $atts['tax_rate'] ?? 0,
                    'discount_price' => $atts['discount_price'] ?? $atts['price'],
                ]);
                if ($product->stock_quantity < 0) {
                    $product->status = 'alertstock';
                } elseif ($product->stock_quantity <= 0) {
                    $product->status = 'outofstock';
                } elseif ($product->stock_quantity < $product->stock_alert) {
                    $product->status = 'lowstock';
                } else {
                    $product->status = 'instock';
                }
                if(!isset($atts['sku'])){
                $product->sku = 'SKU-' . date('Y').date('M').$product->id;
                }
                $product->save();
                return $product;
            });
            if ($request->hasFile('images')) {
                try {
                    foreach ($request->file('images') as $image) {
                        $path = $image->store('products', 'public');
                        $product->images()->create([
                            'path' => $path,
                        ]);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Product created but failed to upload the images or one of them you can re upload by updating the product and only sending the images',
                        'error' => $e->getMessage(),
                    ], 500);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 500);
        }
        return response()->json([
            'message' => 'Product created successfully',
            'product' =>ProductResource::make($product->load('images')),
        ], 201);
    }
    public function update(Request $request, $id)
    {
        $this->authorize('createProduct', User::class);
        $product = Product::findOrFail($id);
        $atts = $request->validate([
            'name' => 'nullable|string|max:255',
            's_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            's_description' => 'nullable|string',
            'sku'=>'nullable|string|unique:products,sku,'.$product->id,
            'product_rate' => 'nullable|numeric',
            'status' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'cost'=> 'nullable|numeric|min:0',
            'category' => 'nullable|string',
            'stock_quantity' => 'nullable|integer|min:0',
            'low_stock_alert_threshold'=>'nullable|integer|min:0',
            'delivery_option' => 'nullable|string',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_price' => 'nullable|numeric|min:0',
        ]);
        try {
            DB::transaction(function () use ($atts, &$product) {
                if (isset($atts['category'])) {
                    $category = Category::where('name', $atts['category'])->orWhere('s_name', $atts['category'])->first();
                    if (!$category) {
                        $category = Category::create([
                            'name' => $atts['category'],
                            's_name' => $atts['category'],
                            'enabled' => true,
                        ]);
                        $category_id=$category->id;
                        $atts['category_id'] = $category_id;
                    }else{
                        $category_id=$category->id;
                        $atts['category_id'] = $category_id;
                    }
                }
                if(isset($atts['price'])){
                    if($atts['price']<($atts['discount_price']??$product->discount_price)){
                        return response()->json(['message'=>'the product price you specified is lower than the offer price'],422);
                    }
                }
                if (isset($atts['discount_price'])) {
                    if ($atts['discount_price'] > ($atts['price'] ?? $product->price) ) {
                        $atts['discount_price'] = ($atts['price'] ?? $product->price)-1+1;
                    }
                }
                $atts['stock_alert']=$atts['low_stock_alert_threshold'] ?? $product->stock_alert;
                $product->update([
                    'name' => $atts['name'] ?? $product->name,
                    's_name' => $atts['s_name'] ?? $product->s_name,
                    'sku'=> $atts['sku'] ?? $product->sku,
                    'description' => $atts['description'] ?? $product->description,
                    's_description' => $atts['s_description'] ?? $product->s_description,
                    'price' => $atts['price'] ?? $product->price,
                    'cost' => $atts['cost'] ?? $product->cost,
                    'product_rate' => $atts['product_rate'] ?? $product->product_rate,
                    'status' => $atts['status'] ?? $product->status,
                    'category_id' => $atts['category_id'] ?? $product->category_id,
                    'stock_quantity' => $atts['stock_quantity'] ?? $product->stock_quantity,
                    'stock_alert'=>$atts['stock_alert'] ?? $product->stock_alert,
                    'delivery_option' => $atts['delivery_option'] ?? $product->delivery_option,
                    'tax_rate' => $atts['tax_rate'] ?? $product->tax_rate,
                    'discount_price' => $atts['discount_price'] ?? $product->discount_price,
                ]);
                if($product->stock_quantity<0){
                    $product->status = 'alertstock';
                }elseif($product->stock_quantity<=0){
                    $product->status='outofstock';
                    }elseif($product->stock_quantity < $product->stock_alert){
                    $product->status='lowstock';
                    }else{
                    $product->status='instock';
                    }
                $product->save();
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
        return response()->json([
            'message' => 'Product updated successfully',
            'product' => ProductResource::make($product->load('images')),
        ], 200);
    }
    public function destroy($id)
    {
        //do we need to create a policy for deleting products?
        $this->authorize('createProduct', User::class);
        $product = Product::find($id);
        if(!$product){
            return response()->json(['message'=>'Product not found'],404);
        }
        // try {
        //     $images=$product->images()->get();
        //     Log::error($images);
        //     foreach($images as $image){
        //         Storage::disk('public')->delete($image->path);
        //     }
            $product->status='deleted';
            $product->save();
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'message' => 'Failed to delete product',
        //         'error' => $e->getMessage(),
        //     ], 500);
        // }
        return response()->json([
            'message' => 'Product deleted successfully Note:the products still in the DB for safty reasons you can always restore it later',
        ], 200);
    }
    public function restore(Request $request,$id){
        $this->authorize('createProduct', User::class);
        $product=Product::find($id);
        if(!$product){
            return response()->json(['message'=>'product not found'],404);
        }
        if($product->status!='deleted'){
            return response()->json(['message'=>'this product is not even deleted'],402);
        }
        if ($product->stock_quantity < 0) {
            $product->status = 'alertstock';
        } elseif ($product->stock_quantity <= 0) {
            $product->status = 'outofstock';
        } elseif ($product->stock_quantity < $product->stock_alert) {
            $product->status = 'lowstock';
        } else {
            $product->status = 'instock';
        }
        $product->save();
        return response()->json(['message'=>'product restored successfuly'],200);
    }
}
