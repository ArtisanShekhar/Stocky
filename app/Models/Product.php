<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'code', 'Type_barcode', 'name', 'cost', 'price', 'unit_id', 'unit_sale_id', 'unit_purchase_id',
        'stock_alert', 'category_id', 'sub_category_id', 'is_variant','is_imei',
        'tax_method', 'image', 'brand_id', 'is_active', 'note','type',
        'warranty_period', 'warranty_unit', 'warranty_terms',
        'has_guarantee', 'guarantee_period', 'guarantee_unit','points','discount', 'discount_method','is_featured', 'serial_number', 'hsn_number'
    ];

    protected $casts = [
        'category_id' => 'integer',
        'is_featured' => 'integer',
        'sub_category_id' => 'integer',
        'unit_id' => 'integer',
        'unit_sale_id' => 'integer',
        'unit_purchase_id' => 'integer',
        'is_variant' => 'integer',
        'is_imei' => 'integer',
        'brand_id' => 'integer',
        'is_active' => 'integer',
        'cost' => 'double',
        'price' => 'double',
        'stock_alert' => 'double',
        'TaxNet' => 'double',
        'points' => 'double',
        'has_guarantee'   => 'boolean',
        'discount' => 'double',
    ];

    public function variants()
    {
        // table is "product_variants", FK is "product_id"
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function ProductVariant()
    {
        return $this->belongsTo('App\Models\ProductVariant');
    }

    public function PurchaseDetail()
    {
        return $this->belongsTo('App\Models\PurchaseDetail');
    }

    public function SaleDetail()
    {
        return $this->belongsTo('App\Models\SaleDetail');
    }

    public function QuotationDetail()
    {
        return $this->belongsTo('App\Models\QuotationDetail');
    }

    public function category()
    {
        return $this->belongsTo('App\Models\Category');
    }

    public function unit()
    {
        return $this->belongsTo('App\Models\Unit', 'unit_id');
    }

    public function unitPurchase()
    {
        return $this->belongsTo('App\Models\Unit', 'unit_purchase_id');
    }

    public function unitSale() 
    {
        return $this->belongsTo('App\Models\Unit', 'unit_sale_id');
    }

    public function brand()
    {
        return $this->belongsTo('App\Models\Brand');
    }

     // Relationship for products that are combined in a combo
     public function combinedProducts()
     {
         return $this->belongsToMany(Product::class, 'combined_products', 'product_id', 'combined_product_id')
                     ->withPivot('quantity')
                     ->withTimestamps();
     }


     public function collections()
    {
        return $this->belongsToMany(Collection::class, 'collection_product')
                    ->withPivot(['sort_order', 'pinned'])
                    ->withTimestamps();
    }


  /**
     * Final price after discount + tax.
     * Encodings (varchar):
     * - discount_method: "1"=percent, "2"=fixed
     * - tax_method:      "1"=exclusive, "2"=inclusive
     *
     * @param  float|null $taxRate       Percent (e.g. 20 for 20%)
     * @param  float|null $overrideBase  Use this base instead of $this->price (for variants)
     * @return array{base:float, discount:float, after_discount:float, tax:float, final:float}
     */
    public function computeFinalPrice(?float $taxRate = null, ?float $overrideBase = null): array
    {
        // ---- Base
        $base = (float) ($overrideBase ?? $this->price ?? 0);

        // ---- Discount method normalization
        $dmRaw = $this->discount_method ?? null;
        $dm = null; // 'percent' | 'fixed' | null

        if (is_string($dmRaw)) {
            $dmRaw = trim(strtolower($dmRaw));
            if ($dmRaw === '1')         $dm = 'percent';
            elseif ($dmRaw === '2')     $dm = 'fixed';
            elseif (in_array($dmRaw, ['percent','percentage'], true)) $dm = 'percent';
            elseif ($dmRaw === 'fixed') $dm = 'fixed';
        } elseif (is_numeric($dmRaw)) {
            $dm = ((int)$dmRaw === 1) ? 'percent' : (((int)$dmRaw === 2) ? 'fixed' : null);
        }

        $discVal = (float) ($this->discount ?? 0);
        if ($dm === 'percent') {
            $discountAmount = round($base * ($discVal / 100), 2);
        } elseif ($dm === 'fixed') {
            $discountAmount = round(min($discVal, $base), 2);
        } else {
            $discountAmount = 0.0;
        }

        $afterDiscount = max(0.0, round($base - $discountAmount, 2));

        // ---- Tax rate
        if ($taxRate === null) {
            if (isset($this->tax_rate))   $taxRate = (float)$this->tax_rate;
            elseif (isset($this->TaxNet)) $taxRate = (float)$this->TaxNet; // some schemas use TaxNet
            else                          $taxRate = 0.0;
        } else {
            $taxRate = (float)$taxRate;
        }

        // ---- Tax method normalization
        $tmRaw = $this->tax_method ?? '1';
        $taxMode = 'exclusive'; // default
        if (is_string($tmRaw)) {
            $tm = trim(strtolower($tmRaw));
            if ($tm === '2' || $tm === 'inclusive') $taxMode = 'inclusive';
            else                                     $taxMode = 'exclusive'; // '1' or 'exclusive'
        } elseif (is_numeric($tmRaw)) {
            $taxMode = ((int)$tmRaw === 2) ? 'inclusive' : 'exclusive';
        }

        if ($taxRate <= 0) {
            $taxAmount = 0.0;
            $final = $afterDiscount;
        } elseif ($taxMode === 'inclusive') {
            // afterDiscount is gross; extract tax portion
            $taxAmount = round($afterDiscount - ($afterDiscount / (1 + $taxRate / 100)), 2);
            $final = $afterDiscount;
        } else {
            // exclusive; add tax on top
            $taxAmount = round($afterDiscount * ($taxRate / 100), 2);
            $final = round($afterDiscount + $taxAmount, 2);
        }

        return [
            'base'           => round($base, 2),
            'discount'       => $discountAmount,
            'after_discount' => $afterDiscount,
            'tax'            => $taxAmount,
            'final'          => $final,
        ];
    }

    /**
     * Minimum final price across variants (if loaded), else product itself.
     */
    public function minDisplayPrice(?float $taxRate = null): float
    {
        if (!empty($this->is_variant) && $this->relationLoaded('variants') && $this->variants->count()) {
            $min = null;
            foreach ($this->variants as $v) {
                $calc = $this->computeFinalPrice($taxRate, (float)($v->price ?? 0));
                $min = is_null($min) ? $calc['final'] : min($min, $calc['final']);
            }
            if ($min !== null) return round($min, 2);
        }
        return round($this->computeFinalPrice($taxRate)['final'], 2);
    }


}
