<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProductReview extends Model
{
    protected $table = 'shop_product_reviews';

    protected $fillable = [
        'product_id',
        'business_id',
        'customer_name',
        'place',
        'purchase_date',
        'review_text',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'is_published' => 'boolean',
    ];

    public function images()
    {
        return $this->hasMany(ShopProductReviewImage::class, 'review_id')->orderBy('sort_order')->orderBy('id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
