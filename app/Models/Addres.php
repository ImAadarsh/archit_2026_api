<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Addres extends Model
{
    use HasFactory;
    protected $fillable = [
        'invoice_id',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'pincode',
        'is_billing',
        'is_shipping'
    ];  

}
