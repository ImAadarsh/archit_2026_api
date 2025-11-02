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
        'art_category_id',
        'item_code',
        'height',
        'width',
        'is_framed',
        'is_include_gst',
        'is_customizable',
        'artist_name',
        'quantity',
        'is_temp',
        'orientation',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function artCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'art_category_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
}
