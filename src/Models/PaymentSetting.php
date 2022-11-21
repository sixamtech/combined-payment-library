<?php

namespace Mdalimrun\CombinedPaymentLibrary\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    use HasFactory;

    protected $casts = [
        'live_values'=>'array',
        'test_values'=>'array',
        'is_active'=>'integer',
    ];

    protected $fillable = ['key_name', 'live_values', 'test_values', 'settings_type', 'mode', 'is_active'];
}
