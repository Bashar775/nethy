<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovment;
use Illuminate\Http\Request;

class StockMovmentController extends Controller
{
    public function index()
    {
        $s=StockMovment::all();
        $s=$s->simplePaginate(10);
        return response()->json(['data' => $s], 200);
    }
}
