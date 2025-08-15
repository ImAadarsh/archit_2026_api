<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    protected $fillable = [
        'serial_no',
        'name',
        'mobile_number',
        'customer_type',
        'doc_type',
        'doc_no',
        'business_id',
        'location_id',
        'payment_mode',
        'billing_address_id',
        'shipping_address_id',
        'type',
        'is_completed',
        'invoice_date',
        'total_dgst',
        'total_cgst',
        'total_igst',
        'total_amount',
        'full_paid',
        'total_paid'
    ];

    // You can also add type casting for certain attributes
    protected $casts = [
        'total_dgst' => 'float',
        'total_cgst' => 'float',
        'total_igst' => 'float',
        'total_amount' => 'float',
        'full_paid' => 'integer',
        'total_paid' => 'float',
    ];
    public function items()
    {
        return $this->hasMany(Item::class)->with('product:name,hsn_code');
    }
        /**
     * Get the billing address for the invoice.
     */
    public function billingAddress()
    {
        return $this->hasOne(Addres::class, 'id', 'billing_address_id');
    }

    /**
     * Get the shipping address for the invoice.
     */
    public function shippingAddress()
    {
        return $this->hasOne(Addres::class, 'id', 'shipping_address_id');
    }

    /**
     * Get the products for the invoice.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'items', 'invoice_id', 'product_id');
    }
}
