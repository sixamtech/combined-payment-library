<?php

namespace Mdalimrun\CombinedPaymentLibrary\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Mdalimrun\CombinedPaymentLibrary\Traits\HasUuid;

class Payment extends Model
{
    use HasUuid;
    use HasFactory;
}
