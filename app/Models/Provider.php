<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'code', 'adresse', 'phone', 'country', 'email', 'city','tax_number',
        'shipping_gstin', 'shipping_state_name', 'shipping_state_code', 'shipping_address',
        'billing_gstin', 'billing_state_name', 'billing_state_code', 'billing_address'
    ];

    protected $casts = [
        'code' => 'integer',
    ];

}
