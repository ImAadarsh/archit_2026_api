<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expenses extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'paid_to',
        'gst_number',
        'taxable_amount',
        'gst_amount',
        'amount',
        'type',
        'file',
        'business_id',
        'location_id',
        'user_id',
        'expense_date',
    ];

    protected $casts = [
        'expense_date' => 'date',
    ];
}
