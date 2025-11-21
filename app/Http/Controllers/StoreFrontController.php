<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use App\Models\Product;
use App\Models\Category;
use App\Models\StoreBanner;
use App\Models\Collection;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;

class StoreFrontController extends Controller
{


    /**
     * Homepage — blocks driven by StoreSetting->homepage_lineup.
     */
    public function index(Request $request)
    {
        $s = StoreSetting::firstOrFail();

        // 1) Load lineup (already cast to array by StoreSetting::$casts)
        $lineup = is_array($s->homepage_lineup) ? $s->homepage_lineup : [];

        // 2) Legacy fallback (home_collections -> lineup)
        if (empty($lineup)) {
            $legacy = $s->home_collections ?? [];
            if (is_string($legacy)) $legacy = json_decode($legacy, true) ?: [];
            if ($legacy) {
                $rows = collect($legacy)
                    ->filter(fn($r) => is_array($r) && !empty($r['collection_id']) && (
                        !array_key_exists('visible', $r)
                        || filter_var($r['visible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false
                    ))
                    ->sortBy(fn($r) => (int)($r['sort_order'] ?? 9999))
                    ->values();

                $ids = $rows->pluck('collection_id')->map(fn($v) => (int)$v)->unique()->all();
                $idToSlug = $ids ? Collection::whereIn('id', $ids)->pluck('slug', 'id')->toArray() : [];

                $lineup = [];
                foreach ($rows as $r) {
                    $slug = (string)($idToSlug[(int)$r['collection_id']] ?? '');
                    if ($slug === '') continue;
                    $limit = max(1, (int)($r['limit'] ?? 8));
                    $lineup[] = [
                        'type'           => 'collection',
                        'slug'           => $slug,
                        'limit'          => $limit,
                        'layout'         => 'grid',
                        'title_override' => '',
                    ];
                }
            }
        }

        // ===== Shared price SQL (mirrors shop()) =====
        $minVariantSub = DB::table('product_variants')
            ->select('product_id', DB::raw('MIN(price) AS min_variant_price'))
            ->groupBy('product_id');

        // Base: if a product has variants, use MIN(variant.price); else use products.price
        $baseExpr = "COALESCE(pvmin.min_variant_price, products.price)";

        // discount_method: '1' => percent, '2' => fixed
        $discValExpr       = "IFNULL(products.discount, 0)";
        $afterDiscountExpr = "GREATEST(0,
            CASE
                WHEN products.discount_method = '1' THEN $baseExpr - ($baseExpr * ($discValExpr/100))
                WHEN products.discount_method = '2' THEN $baseExpr - LEAST($discValExpr, $baseExpr)
                ELSE $baseExpr
            END
        )";

        // tax_method: '2' => Inclusive (leave as-is), otherwise treat as Exclusive and add tax
        $taxRateExpr = "COALESCE(products.TaxNet, 0)";
        $finalExpr   = "ROUND(
            CASE
                WHEN products.tax_method = '2' THEN $afterDiscountExpr
                ELSE $afterDiscountExpr * (1 + ($taxRateExpr/100))
            END, 2
        )";

        // 3) Build blocks
        $blocks = [];
        $defaultTaxRate = (float)($s->default_tax_rate ?? 0);

        foreach ($lineup as $i => $item) {
            if (!is_array($item) || empty($item['type'])) continue;
            $type = strtolower((string)$item['type']);

            if ($type === 'hero') {
                $blocks[] = [
                    'type'     => 'hero',
                    'title'    => $s->hero_title ?? null,
                    'subtitle' => $s->hero_subtitle ?? null,
                    'image'    => $s->hero_image_path ?? null,
                    'cfg'      => ['index' => $i],
                ];
                continue;
            }

            if ($type === 'newsletter') {
                $blocks[] = [
                    'type'  => 'newsletter',
                    'title' => __('Newsletter'),
                    'cfg'   => ['index' => $i],
                ];
                continue;
            }

            if ($type === 'collection') {
                $slug = trim((string)($item['slug'] ?? ($item['handle'] ?? '')));
                if ($slug === '') continue;

                $limit         = max(1, (int)($item['limit'] ?? 8));
                $layout        = in_array(($item['layout'] ?? 'grid'), ['grid', 'carousel'], true) ? $item['layout'] : 'grid';
                $titleOverride = trim((string)($item['title_override'] ?? ''));

                $collection = Collection::where('slug', $slug)->first()
                    ?: (is_numeric($slug) ? Collection::find((int)$slug) : null);
                if (!$collection) continue;

                $colTitle = $titleOverride !== '' ? $titleOverride : ($collection->title ?? $collection->name ?? $slug);

                // === Use the same SQL pipeline as shop(), scoped to this collection ===
                $products = Product::query()
                    ->where('products.is_active', 1)
                    ->with(['variants:id,product_id,name,price,image']) // for QuickView/variant picker
                    ->join('collection_product', 'collection_product.product_id', '=', 'products.id')
                    ->where('collection_product.collection_id', $collection->id)
                    ->leftJoinSub($minVariantSub, 'pvmin', function ($join) {
                        $join->on('pvmin.product_id', '=', 'products.id');
                    })
                    ->addSelect(
                        'products.*',
                        DB::raw("$baseExpr AS base_price"),
                        DB::raw("$afterDiscountExpr AS after_discount"),
                        DB::raw("$finalExpr AS final_display_price")
                    )
                    ->orderBy('collection_product.sort_order')
                    ->orderBy('products.created_at', 'desc')
                    ->take($limit)
                    ->get();

                // Attach display_price to product (from SQL) AND compute each variant's display price (PHP)
                foreach ($products as $p) {
                    // Product display price from SQL
                    $p->display_price = (float)($p->final_display_price ?? 0);

                    // Variant display prices computed with same rules as SQL
                    $taxRate     = is_numeric($p->TaxNet) ? (float)$p->TaxNet : $defaultTaxRate;
                    $discVal     = is_numeric($p->discount) ? (float)$p->discount : 0.0;
                    $isPercent   = (string)$p->discount_method === '1';
                    $isInclusive = (string)$p->tax_method === '2';

                    if ($p->relationLoaded('variants') && $p->variants) {
                        foreach ($p->variants as $v) {
                            $price = (float)($v->price ?? 0);
                            // discount
                            if ($discVal > 0) {
                                $price = $isPercent ? ($price - ($price * $discVal / 100)) : ($price - min($discVal, $price));
                                if ($price < 0) $price = 0;
                            }
                            // tax
                            if (!$isInclusive && $taxRate > 0) {
                                $price = $price * (1 + $taxRate / 100);
                            }
                            $v->display_price = round($price, 2);
                        }
                    }
                }

                $blocks[] = [
                    'type'       => 'collection',
                    'title'      => $colTitle,
                    'collection' => $collection,
                    'products'   => $products, // each $p has display_price; each variant has display_price
                    'cfg'        => [
                        'limit'  => $limit,
                        'layout' => $layout,
                        'index'  => $i,
                    ],
                ];
            }
        }

        // 4) Active banners
        $banners = StoreBanner::query()
            ->where('active', 1)
            ->whereIn('position', ['top_left','top_right','center_left','center_right','footer_left','footer_right'])
            ->orderBy('position')
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($banners as $b) {
            $b->image_url = asset($b->image ?: 'images/brands/no-image.png');
        }

        $categories = Category::orderBy('name')->get();

        return view('store.index', [
            's'          => $s,
            'blocks'     => $blocks,
            'categories' => $categories,
            'banners'    => $banners,
        ]);
    }


    /**
     * Shop — products with filters.
     * Sorting/filters use base "effective_price" (min variant or product price).
     * UI shows final display price (discount + tax) computed per item after fetch.
     */
    public function shop(Request $request)
    {
        $s = StoreSetting::firstOrFail();

        $q        = trim((string) $request->get('q', ''));
        $cat      = $request->get('category');
        $minPrice = $request->get('min');
        $maxPrice = $request->get('max');
        $sort     = $request->get('sort', 'latest');   // latest|price_asc|price_desc
        $coll     = $request->get('collection');       // id or slug

        // 1) Subquery: MIN(variant.price) per product
        $minVariantSub = DB::table('product_variants')
            ->select('product_id', DB::raw('MIN(price) AS min_variant_price'))
            ->groupBy('product_id');

        // 2) SQL price pipeline (MySQL-compatible)
        $baseExpr = "COALESCE(pvmin.min_variant_price, products.price)";

        // discount_method: '1'=percent, '2'=fixed (varchar)
        $discValExpr        = "IFNULL(products.discount, 0)";
        $afterDiscountExpr  = "GREATEST(0,
            CASE
                WHEN products.discount_method = '1' THEN $baseExpr - ($baseExpr * ($discValExpr/100))
                WHEN products.discount_method = '2' THEN $baseExpr - LEAST($discValExpr, $baseExpr)
                ELSE $baseExpr
            END
        )";

        // tax_method: '1'=Exclusive, '2'=Inclusive (varchar);  TaxNet
        $taxRateExpr = "COALESCE(products.TaxNet, 0)";
        $finalExpr   = "ROUND(
            CASE
                WHEN products.tax_method = '2' THEN $afterDiscountExpr
                ELSE $afterDiscountExpr * (1 + ($taxRateExpr/100))
            END, 2
        )";

        $products = Product::query()
            ->where('is_active', 1)
            ->with(['variants:id,product_id,name,price,image']) // eager for Quick View / picker
            ->leftJoinSub($minVariantSub, 'pvmin', function ($join) {
                $join->on('pvmin.product_id', '=', 'products.id');
            })
            ->addSelect(
                'products.*',
                DB::raw("$baseExpr AS base_price"),
                DB::raw("$afterDiscountExpr AS after_discount"),
                DB::raw("$finalExpr AS final_display_price")   // <= final price for filter/sort/UI
            )
            // Search
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where('products.name', 'like', "%{$q}%");
            })
            // Category
            ->when($cat, function ($qb) use ($cat) {
                $qb->where('products.category_id', $cat);
            })
            // Price range (by final price)
            ->when(is_numeric($minPrice), function ($qb) use ($finalExpr, $minPrice) {
                $qb->whereRaw("$finalExpr >= ?", [(float)$minPrice]);
            })
            ->when(is_numeric($maxPrice), function ($qb) use ($finalExpr, $maxPrice) {
                $qb->whereRaw("$finalExpr <= ?", [(float)$maxPrice]);
            })
            // Collection: id or slug
            ->when($coll, function ($qb) use ($coll) {
                $qb->whereHas('collections', function ($rel) use ($coll) {
                    if (is_numeric($coll)) $rel->where('collections.id', (int)$coll);
                    else $rel->where('collections.slug', (string)$coll);
                });
            });

        // Sort
        if ($sort === 'price_asc') {
            $products->orderByRaw("$finalExpr ASC");
        } elseif ($sort === 'price_desc') {
            $products->orderByRaw("$finalExpr DESC");
        } else {
            $products->orderBy('products.created_at', 'desc');
        }

        $products    = $products->paginate(12)->withQueryString();
        $categories  = Category::orderBy('name')->get(['id', 'name']);
        $collections = Collection::orderBy('title')
            ->get(['id', 'title', 'slug'])
            ->map(function ($c) { $c->title = $c->title ?: ($c->name ?? ''); return $c; });

        // Attach display_price for the Blade (use SQL-computed final_display_price)
        foreach ($products as $p) {
            $p->display_price = (float)($p->final_display_price ?? 0);
        }

        return view('store.shop', [
            's'           => $s,
            'products'    => $products,
            'categories'  => $categories,
            'collections' => $collections,
            'q'           => $q,
            'cat'         => $cat,
            'min'         => $minPrice,
            'max'         => $maxPrice,
            'sort'        => $sort,
            'collection'  => $coll,
        ]);
    }




    public function contact()
    {
        $s = StoreSetting::first();
        return view('store.contact', compact('s'));
    }

    public function sendContact(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:190',
            'email'   => 'required|email',
            'phone'   => 'nullable|string|max:190',
            'subject' => 'nullable|string|max:190',
            'message' => 'required|string|max:5000',
            'company' => 'nullable|string|max:190', // honeypot
        ]);

        // Honeypot → quietly succeed
        if (!empty($data['company'])) {
            return back()->with('success', __('Thanks!'))->withInput();
        }

        $s = StoreSetting::first();

        // Example mail:
        // \Mail::to($s->contact_email ?? config('mail.from.address'))
        //     ->send(new \App\Mail\ContactFormMail($data));

        return back()->with('success', __('Your message has been sent. Thank you!'));
    }
}
