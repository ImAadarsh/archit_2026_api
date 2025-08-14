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
        'product_serial_number',
        'category_id',
        'item_code',
        'height',
        'width',
        'is_framed',
        'is_include_gst',
        'artist_name',
        'quantity',
        'is_temp',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
}
