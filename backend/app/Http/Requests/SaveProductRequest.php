<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('variants'))) {
            $this->merge(['variants' => array_map(function ($variant) {
                if (! is_array($variant)) {
                    return $variant;
                }
                foreach (['color', 'size'] as $field) {
                    if (isset($variant[$field]) && is_string($variant[$field])) {
                        $variant[$field] = mb_strtoupper(trim($variant[$field]));
                    }
                }

                return $variant;
            }, $this->input('variants'))]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $seen = [];
            foreach ($this->input('variants', []) as $index => $variant) {
                $key = json_encode([$variant['color'], $variant['size']]);
                if (isset($seen[$key])) {
                    $validator->errors()->add("variants.$index.color", 'Esta combinação de cor e tamanho já existe.');
                }
                $seen[$key] = true;
            }
        }];
    }

    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $optional = $this->isMethod('PUT') ? 'present' : 'sometimes';

        return [
            'variants' => ['sometimes', 'array', 'max:100'],
            'variants.*' => ['required', 'array:color,size,stock'],
            'variants.*.color' => ['required', 'string', 'max:60'],
            'variants.*.size' => ['required', 'string', 'max:30'],
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'name' => [$required, 'required', 'string', 'max:255'],
            'description' => [$required, 'required', 'string'],
            'image' => [$optional, 'nullable', 'url:http,https', 'max:2048'],
            'price' => [$required, 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'category' => [$required, 'required', 'string', 'max:100'],
            'stock' => [$this->isMethod('PUT') ? 'required' : 'sometimes', 'required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
