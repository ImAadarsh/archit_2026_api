<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'business_id',
        'location_id',
        'filter_data',
        'selected_products',
        'inquiry_notes',
        'status'
    ];

    protected $casts = [
        'filter_data' => 'array',
        'selected_products' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function business()
    {
        return $this->belongsTo(Businesss::class);
    }

    public function location()
    {
        return $this->belongsTo(Locations::class);
    }

    public function selectedProducts()
    {
        return $this->belongsToMany(Product::class, 'user_inquiry_products', 'inquiry_id', 'product_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeContacted($query)
    {
        return $query->where('status', 'contacted');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function scopeByLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }
} 