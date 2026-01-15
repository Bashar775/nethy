<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class SupplierController extends Controller
{
    use AuthorizesRequests;
    
    public function index()
    {
        $this->authorize('supplierInfo', User::class);
        return response()->json(['data' =>SupplierResource::collection(Supplier::all())], 200);
    }
    public function show($id)
    {
        $this->authorize('supplierInfo', User::class);
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }
        return response()->json(['data' => SupplierResource::make($supplier)], 200);
    }
    public function store(Request $request)
    {
        $this->authorize('supplierInfo', User::class);
        $atts = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:suppliers,email',
            'phone' => 'nullable|string|max:20|unique:suppliers,phone',
            'address' => 'nullable|string|max:500',
            'product_type' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
        try{
        $supplier = Supplier::create($atts);
        }catch(\Exception $e){
            return response()->json(['message'=>'Error creating supplier: '.$e->getMessage()],500);
        }
        return response()->json(['message' => 'Supplier created successfully', 'data' => SupplierResource::make($supplier)], 201);
    }
    public function update(Request $request, $id)
    {
        $this->authorize('supplierInfo', User::class);
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }
        $atts = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|email|max:255|unique:suppliers,email,' . $supplier->id,
            'phone' => 'sometimes|nullable|string|max:20|unique:suppliers,phone,' . $supplier->id,
            'address' => 'sometimes|nullable|string|max:500',
            'product_type' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string',
        ]);
        try{
        $supplier->update($atts);
        }catch(\Exception $e){
            return response()->json(['message'=>'Error updating supplier: '.$e->getMessage()],500);
        }
        return response()->json(['message' => 'Supplier updated successfully', 'data' => SupplierResource::make($supplier)], 200);
    }
    public function destroy($id)
    {
        $this->authorize('supplierInfo', User::class);
        $supplier = Supplier::find($id);
        if (!$supplier) {
            return response()->json(['message' => 'Supplier not found'], 404);
        }
        try{
        $supplier->delete();
        }catch(\Exception $e){
            return response()->json(['message'=>'Error deleting supplier: '.$e->getMessage()],500);
        }
        return response()->json(['message' => 'Supplier deleted successfully'], 200);
    }
}
