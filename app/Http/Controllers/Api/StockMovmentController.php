<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockResource;
use App\Models\StockMovment;
use Illuminate\Http\Request;

class StockMovmentController extends Controller
{
    public function index()
    {
        // $s=StockMovment::orderBy('updated_at', 'desc')->simplePaginate(10);
        return response()->json([StockMovment::all()]);
    }
}
