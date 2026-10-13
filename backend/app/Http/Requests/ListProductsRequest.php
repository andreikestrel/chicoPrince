<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $price = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'];

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'min_price' => $price,
            'max_price' => [...$price, ...($this->filled('min_price') ? ['gte:min_price'] : [])],
            'in_stock' => ['nullable', 'boolean'],
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:2147483647'],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'required', Rule::in(['id', 'name', 'price', 'category', 'created_at', 'updated_at'])],
            'direction' => ['sometimes', 'required', Rule::in(['asc', 'desc'])],
        ];
    }
}
