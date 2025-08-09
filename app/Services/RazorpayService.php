<?php

namespace App\Services;

use Razorpay\Api\Api;

class RazorpayService
{
    public function client(): Api
    {
        $keyId = config('services.razorpay.key_id');
        $keySecret = config('services.razorpay.key_secret');
        return new Api($keyId, $keySecret);
    }
}

