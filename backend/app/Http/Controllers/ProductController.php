<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(Product::orderBy('id')->paginate(15));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    public function store(SaveProductRequest $request): ProductResource
    {
        $product = Product::create($request->validated());
        // Recarrega os defaults do banco mantendo a resposta de criação em 201.
        $product->refresh();

        return new ProductResource($product);
    }

    public function update(SaveProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->refresh());
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }
}
