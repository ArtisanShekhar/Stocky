<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'name', 'code', 'adresse', 'email', 'phone', 'country', 'city','tax_number',
        'is_royalty_eligible','points'
    ];

    protected $casts = [
        'code'                => 'integer',
        'is_royalty_eligible' => 'integer',
        'points'              => 'double',
    ];
}
