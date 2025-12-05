<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'code', 'adresse', 'email', 'phone', 'country', 'city','tax_number',
        'is_royalty_eligible','points',
        'shipping_gstin', 'shipping_state_name', 'shipping_state_code', 'shipping_address',
        'billing_gstin', 'billing_state_name', 'billing_state_code', 'billing_address'
    ];

    protected $casts = [
        'code'                => 'integer',
        'is_royalty_eligible' => 'integer',
        'points'              => 'double',
    ];
}
