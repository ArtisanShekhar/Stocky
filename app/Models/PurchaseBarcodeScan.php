<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseBarcodeScan extends Model
{
    protected $fillable = [
        'purchase_detail_id',
        'barcode',
        'type',
    ];

    public function purchaseDetail()
    {
        return $this->belongsTo(PurchaseDetail::class);
    }
}
