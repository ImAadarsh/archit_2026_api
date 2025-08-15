<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Businesss extends Model
{
    use HasFactory;

    protected $table = 'businessses';

    protected $fillable = [
        'business_name',
        'gst',
        'email',
        'phone',
        'alternate_phone',
        'owner_name',
        'primary_location_id',
        'logo',
        'is_active',
    ];
}
