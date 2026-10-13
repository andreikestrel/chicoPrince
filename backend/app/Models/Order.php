<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['items' => 'array', 'address' => 'array', 'subtotal' => 'integer', 'discount' => 'integer', 'shipping' => 'integer', 'total' => 'integer'];
    }
}
