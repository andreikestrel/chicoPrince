<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $optional = $this->isMethod('PUT') ? 'present' : 'sometimes';

        return [
            'name' => [$required, 'required', 'string', 'max:255'],
            'description' => [$required, 'required', 'string'],
            'image' => [$optional, 'nullable', 'url:http,https', 'max:2048'],
            'price' => [$required, 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'category' => [$required, 'required', 'string', 'max:100'],
            'stock' => [$this->isMethod('PUT') ? 'required' : 'sometimes', 'required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
