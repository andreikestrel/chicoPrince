<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = ['title', 'image', 'category', 'position', 'active'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'active' => 'boolean'];
    }
}
