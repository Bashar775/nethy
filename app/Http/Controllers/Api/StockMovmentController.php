<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovment;
use Illuminate\Http\Request;

class StockMovmentController extends Controller
{
    public function index()
    {
        return response()->json(['data' => StockMovment::all()], 200);
    }
}
