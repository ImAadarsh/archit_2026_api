<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'business_id',
        'location_id',
        'name',
        'hsn_code',
        'price',
        'product_serial_number'
    ];
}
