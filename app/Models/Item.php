<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{

    use HasFactory;
    protected $fillable = [
        'product_id',
        'invoice_id',
        'quantity',
        'price_of_one',
        'price_of_all',
        'is_gst',
        'dgst',
        'cgst',
        'igst'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_of_one' => 'float',
        'price_of_all' => 'float',
        'dgst' => 'float',
        'cgst' => 'float',
        'igst' => 'float'
    ];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

}

