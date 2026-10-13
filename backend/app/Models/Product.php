<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'image',
        'price',
        'category',
        'stock',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }
}
