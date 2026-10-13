<?php

namespace App\Http\Requests;

use App\Models\CatalogCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('category'))) {
            $name = CatalogCategory::all()->first(
                fn (array $category) => mb_strtolower($category['name']) === mb_strtolower(trim($this->input('category')))
            )['name'] ?? null;
            if ($name !== null) {
                $this->merge(['category' => $name]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'image' => ['required', 'url:http,https', 'max:2048'],
            'category' => ['required', 'string', 'max:100', Rule::exists('products', 'category')],
            'position' => ['required', 'integer', 'min:0', 'max:9999'],
            'active' => ['required', 'boolean'],
        ];
    }
}
