<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProductReviewImage extends Model
{
    protected $table = 'shop_product_review_images';

    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'image',
        'sort_order',
    ];

    public function review()
    {
        return $this->belongsTo(ShopProductReview::class, 'review_id');
    }
}
