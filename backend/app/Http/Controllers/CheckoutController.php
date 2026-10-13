<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request): JsonResponse
    {
        $data = $request->validated();
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $order = DB::transaction(function () use ($request, $data, $hash) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $existing = Order::where('user_id', $request->user()->id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->payload_hash !== $hash) {
                    throw ValidationException::withMessages(['request_key' => 'Esta tentativa já foi usada com outros dados.']);
                }

                return $existing;
            }
            $variantIds = array_column($data['items'], 'variant_id');
            $productIds = ProductVariant::whereIn('id', $variantIds)->pluck('product_id')->unique()->sort()->values();
            $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $variants = ProductVariant::whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $subtotal = 0;
            $snapshots = [];
            foreach ($data['items'] as $index => $item) {
                $variant = $variants->get($item['variant_id']);
                $product = $variant ? $products->get($variant->product_id) : null;
                if (! $product || ! $variant || $variant->stock < $item['quantity']) {
                    throw ValidationException::withMessages(["items.$index.quantity" => 'Produto indisponível ou estoque insuficiente. Atualize o carrinho.']);
                }
                if ($product->price !== $item['unit_price']) {
                    throw ValidationException::withMessages(["items.$index.unit_price" => 'O preço mudou. Atualize o carrinho e confira os valores antes de finalizar.']);
                }
                [$whole, $fraction] = explode('.', $product->price);
                $price = (int) $whole * 100 + (int) $fraction;
                $subtotal += $price * $item['quantity'];
                $snapshots[] = ['product_id' => $product->id, 'variant_id' => $variant->id, 'name' => $product->name,
                    'color' => $variant->color, 'size' => $variant->size, 'quantity' => $item['quantity'], 'unit_price' => $price];
            }
            $coupon = $data['coupon'] ?? null;
            if ($coupon && Order::where('user_id', $request->user()->id)->exists()) {
                throw ValidationException::withMessages(['coupon' => 'PRIMEIRACOMPRA é válido somente no primeiro pedido da conta. Remova o cupom para continuar.']);
            }
            $discount = $coupon ? intdiv($subtotal + 5, 10) : 0;
            foreach ($data['items'] as $item) {
                $variants[$item['variant_id']]->decrement('stock', $item['quantity']);
            }
            foreach ($products as $product) {
                $product->update(['stock' => $product->variants()->orderBy('id')->lockForUpdate()->get()->sum('stock')]);
            }

            return Order::create(['user_id' => $request->user()->id, 'request_key' => $data['request_key'],
                'payload_hash' => $hash, 'status' => 'completed', 'subtotal' => $subtotal, 'discount' => $discount,
                'shipping' => 0, 'total' => $subtotal - $discount, 'coupon' => $coupon,
                'items' => $snapshots, 'address' => $data['address']]);
        }, 3);

        return response()->json(['data' => $order->only('id', 'status', 'subtotal', 'discount', 'shipping', 'total', 'items', 'created_at')], $order->wasRecentlyCreated ? 201 : 200);
    }
}
