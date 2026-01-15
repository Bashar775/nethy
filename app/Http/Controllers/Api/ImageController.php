<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Image;
use App\Models\Product;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class ImageController extends Controller
{
    use AuthorizesRequests;
    public function destroy(Request $request,$id){
    $this->authorize('createProduct',User::class);
    $image=Image::findOrFail($id);
    if($image){
        try {
            // Delete file from storage
            Storage::disk('public')->delete($image->path);

            // Delete DB record
            $image->delete();

            return response()->json([
                'message' => 'Image deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }else{
        return response()->json(['message'=>'Image not found'],404);
    }
    }
    public function addImages(Request $request, $id)
    {
        $this->authorize('createProduct', User::class);
        $atts = $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|max:2048|mimes:jpeg,png,jpg,webp',
        ]);
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        try {
            foreach ($atts['images'] as $image) {
                $path = $image->store('products', 'public');
                $product->images()->create([
                    'path' => $path,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload images',
                'error' => $e->getMessage(),
            ], 500);
        }
        return response()->json([
            'message' => 'Images uploaded successfully',
            'product' => ProductResource::make($product->load('images')),
        ], 200);
    }
}
