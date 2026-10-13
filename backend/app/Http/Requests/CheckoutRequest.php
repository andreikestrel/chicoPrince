<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'],
            'coupon' => ['nullable', 'in:PRIMEIRACOMPRA'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:variant_id,quantity,unit_price'],
            'items.*.variant_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.unit_price' => ['required', 'regex:/^\d{1,8}\.\d{2}$/'],
            'address' => ['required', 'array:recipient,cep,street,number,complement,district,city,state'],
            'address.recipient' => ['required', 'string', 'max:120'],
            'address.cep' => ['required', 'regex:/^\d{5}-?\d{3}$/'],
            'address.street' => ['required', 'string', 'max:200'],
            'address.number' => ['required', 'string', 'max:20'],
            'address.complement' => ['nullable', 'string', 'max:100'],
            'address.district' => ['required', 'string', 'max:100'],
            'address.city' => ['required', 'string', 'max:100'],
            'address.state' => ['required', 'regex:/^[A-Z]{2}$/'],
        ];
    }
}
