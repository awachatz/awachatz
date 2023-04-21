<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    protected $fillable = [
        'name',
        'min_items',
        'max_items',
        'weight',
        'height',
        'width',
        'length',
    ];
}
