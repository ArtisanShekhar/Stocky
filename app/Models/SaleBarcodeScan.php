<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleBarcodeScan extends Model
{
    protected $fillable = [
        'sale_detail_id',
        'barcode',
        'type',
    ];

    public function saleDetail()
    {
        return $this->belongsTo(SaleDetail::class);
    }
}
