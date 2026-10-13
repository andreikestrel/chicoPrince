<?php

namespace App\Http\Controllers;

use App\Models\CatalogCategory;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => CatalogCategory::all()]);
    }
}
