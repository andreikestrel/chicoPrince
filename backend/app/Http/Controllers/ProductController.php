<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListProductsRequest;
use App\Http\Requests\SaveProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index(ListProductsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $query = Product::query();

        if (isset($filters['search'])) {
            // Trata % e _ como texto digitado, não como curingas SQL.
            $search = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']);
            $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$search.'%']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }

        $sort = $filters['sort'] ?? 'id';
        $direction = $filters['direction'] ?? 'asc';
        $query->orderBy($sort, $direction);
        if ($sort !== 'id') {
            $query->orderBy('id', $direction);
        }

        return ProductResource::collection(
            $query->paginate($filters['per_page'] ?? 15)->appends($filters)
        );
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
        Log::channel('products')->info('product.created', [
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
        ]);

        return new ProductResource($product);
    }

    public function update(SaveProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());
        Log::channel('products')->info('product.updated', [
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
        ]);

        return new ProductResource($product->refresh());
    }

    public function destroy(Request $request, Product $product): Response
    {
        $product->delete();
        Log::channel('products')->info('product.deleted', [
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->noContent();
    }
}
