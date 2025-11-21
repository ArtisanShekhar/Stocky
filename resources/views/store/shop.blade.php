@extends('layouts.store')

@section('content')
@php
  $currency   = $s->currency_code ?? '$';
  $total      = $products->total();
  $hasFilters = filled($q ?? null) || filled($cat ?? null) || filled($collection ?? null) || filled($min ?? null) || filled($max ?? null);
@endphp

{{-- ===== Top bar ===== --}}
<section class="shop-hero border-bottom bg-gradient-subtle">
  <div class="container py-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div>
        <h1 class="h3 mb-1">{{ __('messages.Shop') }}</h1>
        <div class="text-muted small">
          {{ trans_choice('messages.products', $total, ['count' => $total]) }}
          @if($hasFilters) • {{ __('messages.FiltersApplied') }} @endif
        </div>
      </div>

      <form method="get" action="{{ route('store.shop') }}" class="d-flex align-items-end gap-2 flex-wrap">
        {{-- keep other query params when changing sort --}}
        @foreach(request()->except(['sort','page']) as $k => $v)
          @if(is_array($v))
            @foreach($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach
          @else
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
          @endif
        @endforeach

        <div class="d-flex align-items-end gap-2">
          <div>
            <label class="form-label small mb-1">{{ __('messages.Sort') }}</label>
            <select name="sort" class="form-select form-select-sm">
              <option value="latest" @selected(($sort ?? 'latest') === 'latest')>{{ __('messages.Latest') }}</option>
              <option value="price_asc" @selected($sort === 'price_asc')>{{ __('messages.PriceUp') }}</option>
              <option value="price_desc" @selected($sort === 'price_desc')>{{ __('messages.PriceDown') }}</option>
            </select>
          </div>
          <button class="btn btn-sm btn-primary">
            <i class="bi bi-arrow-repeat"></i> {{ __('messages.Update') }}
          </button>
        </div>

        <button class="btn btn-outline-primary d-lg-none ms-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#filtersOffcanvas">
          <i class="bi bi-funnel"></i> {{ __('messages.Filters') }}
        </button>
      </form>
    </div>
  </div>
</section>

<div class="container py-4">
  <div class="row">
    {{-- ===== Sidebar filters (desktop) ===== --}}
    <aside class="col-lg-3 d-none d-lg-block">
      @include('store.partials.filters-card', [
        'q' => $q, 'cat' => $cat, 'collection' => $collection,
        'min' => $min, 'max' => $max, 'sort' => $sort,
        'categories' => $categories, 'collections' => $collections
      ])
    </aside>

    {{-- ===== Main content ===== --}}
    <main class="col-lg-9">
      {{-- Applied filter chips --}}
      @if($hasFilters)
        <div class="d-flex flex-wrap gap-2 mb-3">
          @if(filled($q))
            <a href="{{ route('store.shop', request()->except('q','page')) }}" class="chip">
              <i class="bi bi-search"></i> “{{ $q }}” <span class="chip-x">×</span>
            </a>
          @endif
          @if(filled($cat))
            @php $catName = optional($categories->firstWhere('id', $cat))->name ?? $cat; @endphp
            <a href="{{ route('store.shop', request()->except('category','page')) }}" class="chip">
              <i class="bi bi-tag"></i> {{ $catName }} <span class="chip-x">×</span>
            </a>
          @endif
          @if(filled($collection))
            @php
              $coObj  = $collections->first(fn($c) => (string)$c->slug === (string)$collection || (string)$c->id === (string)$collection);
              $coName = $coObj->title ?? $collection;
            @endphp
            <a href="{{ route('store.shop', request()->except('collection','page')) }}" class="chip">
              <i class="bi bi-collection"></i> {{ $coName }} <span class="chip-x">×</span>
            </a>
          @endif
          @if(filled($min))
            <a href="{{ route('store.shop', request()->except('min','page')) }}" class="chip">
              {{ __('messages.Min') }}: {{ $currency }}{{ number_format((float)$min, 2) }} <span class="chip-x">×</span>
            </a>
          @endif
          @if(filled($max))
            <a href="{{ route('store.shop', request()->except('max','page')) }}" class="chip">
              {{ __('messages.Max') }}: {{ $currency }}{{ number_format((float)$max, 2) }} <span class="chip-x">×</span>
            </a>
          @endif

          <a href="{{ route('store.shop') }}" class="chip chip-reset">
            <i class="bi bi-x-circle"></i> {{ __('messages.ResetAll') }}
          </a>
        </div>
      @endif

      {{-- Products grid --}}
      @if($products->count())
        <div class="row g-4">
          @foreach($products as $p)
            @php
              // Media & description
              $imgUrl      = $p->image ? asset('images/products/'.$p->image) : asset('images/products/no-image.png');
              $descShort   = \Illuminate\Support\Str::limit(strip_tags($p->description ?? ''), 600);

              // FINAL product price from controller (already discount+tax). Fallback to model helper if needed.
              $taxRate     = isset($p->TaxNet) ? (float)$p->TaxNet : (float)($s->default_tax_rate ?? 0);
              $finalPrice  = (float)($p->display_price
                              ?? (method_exists($p,'minDisplayPrice') ? $p->minDisplayPrice($taxRate) : ($p->price ?? 0)));

              // Variants payload with FINAL price for each (display_price)
              $hasVariants = $p->variants && $p->variants->count() > 0;
              $variantsPayload = $hasVariants
                ? $p->variants->map(function($v) use ($p, $imgUrl, $taxRate, $currency) {
                    $base = (float)($v->price ?? 0);
                    $calc = method_exists($p,'computeFinalPrice') ? $p->computeFinalPrice($taxRate, $base) : ['final' => $base];
                    return [
                      'id'                      => (int)$v->id,
                      'name'                    => (string)($v->name ?? ''),
                      'price'                   => (float)$base,
                      'display_price'           => (float)$calc['final'],
                      'display_price_formatted' => $currency . number_format($calc['final'], 2),
                      'image'                   => $v->image ? asset('images/products/'.$v->image) : $imgUrl,
                    ];
                  })->values()
                : collect([]);
            @endphp

            <div class="col-6 col-md-4 col-xl-3">
              <div class="card product-card h-100 border-0 rounded-4 shadow-sm">
                <div class="product-media ratio ratio-1x1 position-relative rounded-top-4 overflow-hidden">
                  <img src="{{ $imgUrl }}" class="img-cover" alt="{{ $p->name }}">

                  {{-- Quick View (uses FINAL price) --}}
                  <div class="icon-stack">
                    <button type="button"
                            class="btn btn-light btn-sm rounded-circle shadow position-absolute top-0 end-0 m-2 js-quick-view"
                            title="{{ __('messages.QuickView') }}"
                            style="z-index:3"
                            data-id="{{ $p->id }}"
                            data-slug="{{ $p->slug }}"
                            data-name="{{ e($p->name) }}"
                            data-price="{{ number_format($finalPrice, 2, '.', '') }}"
                            data-image="{{ $imgUrl }}"
                            data-currency="{{ $currency }}"
                            data-description="{{ e($descShort) }}"
                            data-variants='@json($variantsPayload)'>
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>

                  <div class="media-gradient"></div>
                </div>

                <div class="card-body p-3">
                  <h6 class="product-title text-truncate mb-1" title="{{ $p->name }}">
                    <span class="text-reset text-decoration-none">{{ $p->name }}</span>
                  </h6>
                  <div class="product-price fw-bold">
                    {{ $currency }}{{ number_format($finalPrice, 2) }}
                  </div>
                </div>

                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
                  <div class="d-grid">
                    {{-- SAME add-to-cart contract as your base: let global handler process non-variant items.
                         For variant items, capture-phase script will open the picker and stop propagation. --}}
                    <button type="button"
                            class="btn btn-sm btn-primary w-100 js-add-to-cart"
                            data-id="{{ $p->id }}"
                            data-slug="{{ $p->slug }}"
                            data-name="{{ e($p->name) }}"
                            data-price="{{ number_format($finalPrice, 2, '.', '') }}"
                            data-image="{{ $imgUrl }}"
                            data-currency="{{ $currency }}"
                            data-qty="1"
                            data-product-id="{{ $p->id }}"
                            data-product-image="{{ $imgUrl }}"
                            data-variants='@json($variantsPayload)'>
                      <i class="bi bi-cart-plus"></i> {{ __('messages.AddToCart') }}
                    </button>
                    <div class="small mt-2 js-add-status text-muted" style="min-height:1.25rem;"></div>
                  </div>
                </div>
              </div>
            </div>
          @endforeach
        </div>

        {{-- Pagination --}}
        @php $products->appends(request()->except('page')); @endphp
        @if ($products->hasPages())
          <div class="mt-4 d-flex flex-column align-items-center gap-2">
            <div class="text-muted small">
              @if($products->total() > 0)
                {{ __('messages.Showing') }}
                <strong>{{ $products->firstItem() }}</strong>–<strong>{{ $products->lastItem() }}</strong>
                {{ __('messages.of') }} <strong>{{ $products->total() }}</strong> {{ __('messages.productsLower') }}
              @else
                {{ __('messages.NoProductsFound') }}
              @endif
            </div>

            <nav aria-label="Product pagination">
              <ul class="pagination pagination-modern">
                {{-- Prev --}}
                @if ($products->onFirstPage())
                  <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
                @else
                  <li class="page-item"><a class="page-link" href="{{ $products->previousPageUrl() }}"><i class="bi bi-chevron-left"></i></a></li>
                @endif

                {{-- Windowed page list --}}
                @php
                  $current = $products->currentPage();
                  $last    = $products->lastPage();
                  $window  = 1;
                  $pages   = collect([1, $last])
                              ->merge(range(max(1, $current - $window), min($last, $current + $window)))
                              ->unique()->sort()->values();
                  $prev = null;
                @endphp

                @foreach ($pages as $page)
                  @if(!is_null($prev) && $page - $prev > 1)
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                  @endif

                  @if ($page == $current)
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                  @else
                    <li class="page-item"><a class="page-link" href="{{ $products->url($page) }}">{{ $page }}</a></li>
                  @endif

                  @php $prev = $page; @endphp
                @endforeach

                {{-- Next --}}
                @if ($products->hasMorePages())
                  <li class="page-item"><a class="page-link" href="{{ $products->nextPageUrl() }}"><i class="bi bi-chevron-right"></i></a></li>
                @else
                  <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
                @endif
              </ul>
            </nav>
          </div>
        @endif

      @else
        {{-- Empty state --}}
        <div class="text-center py-5">
          <div class="display-6 mb-2">😕</div>
          <h5 class="mb-2">{{ __('messages.NoProductsFound') }}</h5>
          <p class="text-muted mb-4">{{ __('messages.TryAdjustingFiltersOrBrowseAll') }}</p>
          <a href="{{ route('store.shop') }}" class="btn btn-outline-primary">{{ __('messages.ClearFilters') }}</a>
        </div>
      @endif
    </main>
  </div>
</div>

{{-- ===== Offcanvas Filters (mobile) ===== --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="filtersOffcanvas" aria-labelledby="filtersOffcanvasLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="filtersOffcanvasLabel">{{ __('messages.Filters') }}</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('messages.Close') }}"></button>
  </div>
  <div class="offcanvas-body">
    @include('store.partials.filters-card', [
      'q' => $q, 'cat' => $cat, 'collection' => $collection,
      'min' => $min, 'max' => $max, 'sort' => $sort,
      'categories' => $categories, 'collections' => $collections,
      'isOffcanvas' => true
    ])
  </div>
</div>

{{-- ===== Quick View Modal (image + description + VARIANTS) ===== --}}
<div class="modal fade" id="quickViewModal" tabindex="-1" aria-labelledby="quickViewLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-body p-0">
        <div class="row g-0">
          <div class="col-lg-6">
            <div class="qv-media">
              <img id="qvImg" src="" alt="Product" class="qv-img">
            </div>
          </div>
          <div class="col-lg-6">
            <div class="p-4">
              <button type="button" class="btn-close float-end" data-bs-dismiss="modal" aria-label="{{ __('messages.Close') }}"></button>
              <h3 id="qvTitle" class="h5 mb-1">—</h3>
              <div id="qvPrice" class="h4 text-primary mb-3">—</div>
              <div id="qvDesc" class="text-muted mb-3" style="max-height: 240px; overflow:auto;">—</div>

              {{-- Variant list (shown only if variants exist) --}}
              <div id="qvVariantWrap" class="mb-3 d-none">
                <div class="fw-semibold mb-1">{{ __('messages.ChooseVariant') }}</div>
                <ul id="qvVariantList" class="list-group mb-2"></ul>
                <div class="text-muted small" id="qvSelectedInfo">—</div>
              </div>

              <div class="d-flex gap-2">
                <button type="button" id="qvAddBtn" class="btn btn-primary">
                  <i class="bi bi-cart-plus"></i> {{ __('messages.AddToCart') }}
                </button>
              </div>

              <div id="qvStatus" class="small text-muted mt-3" style="min-height:1.25rem;"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ===== Variant Picker Modal (for card Add-to-cart) ===== --}}
<div class="modal fade" id="variantPickerModal" tabindex="-1" aria-labelledby="variantPickerLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header">
        <h5 class="modal-title" id="variantPickerLabel">{{ __('messages.ChooseVariant') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.Close') }}"></button>
      </div>
      <div class="modal-body">
        <div id="vpProductTitle" class="fw-semibold mb-2">—</div>
        <ul id="vpVariantList" class="list-group mb-3"></ul>
        <div class="d-flex align-items-center justify-content-between">
          <div class="text-muted small" id="vpSelectedInfo">—</div>
          <button type="button" class="btn btn-primary" id="vpConfirmBtn" disabled>
            <i class="bi bi-cart-plus"></i> {{ __('messages.AddToCart') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ===== Styles ===== --}}
<style>
  .bg-gradient-subtle{ background: linear-gradient(90deg, rgba(108,92,231,.05), rgba(0,194,255,.05)); }
  .chip{ display:inline-flex; align-items:center; gap:.5rem; background:#f8fafc; border:1px solid #e5e7eb; border-radius:999px; padding:.35rem .7rem; text-decoration:none; color:#334155; font-size:.9rem; }
  .chip:hover{ background:#fff; border-color:#cbd5e1; color:#0f172a; }
  .chip .chip-x{ color:#94a3b8; margin-left:.2rem; }
  .product-card{ border-radius:1rem; transition: transform .15s ease, box-shadow .15s ease; box-shadow: 0 2px 10px rgba(15,23,42,.06); }
  .product-card:hover{ transform: translateY(-3px); box-shadow: 0 8px 24px rgba(15,23,42,.08); }
  .ratio.ratio-1x1{ --bs-aspect-ratio:100%; }
  .img-cover{ width:100%; height:100%; object-fit:cover; display:block; }
  .pagination-modern{ display:flex; gap:.25rem; }
  .pagination-modern .page-link{ border-radius:.6rem; border:1px solid #e5e7eb; color:#334155; background:#fff; padding:.45rem .75rem; }
  .pagination-modern .page-link:hover{ border-color:#cbd5e1; color:#0f172a; }
  .pagination-modern .page-item.active .page-link{ background:#0d6efd; border-color:#0d6efd; color:#fff; box-shadow:0 0 0 .15rem rgba(13,110,253,.15); }
  .pagination-modern .page-item.disabled .page-link{ color:#94a3b8; background:#f8fafc; border-color:#e5e7eb; }
  .product-media{ position:relative; }
  .icon-stack{ position:absolute; top:.6rem; right:.6rem; display:flex; flex-direction:column; gap:.4rem; z-index:2; }
  .media-gradient{ content:""; position:absolute; inset:0; background: linear-gradient(to top, rgba(0,0,0,.35), rgba(0,0,0,0) 50%); opacity:0; transition: opacity .2s ease; }
  .product-media:hover .media-gradient{ opacity:.9; }
  .qv-media{ position:relative; min-height: 420px; background:#0b1220; display:flex; align-items:center; justify-content:center; overflow:hidden; border-top-left-radius:1rem; border-bottom-left-radius:1rem; }
  .qv-img{ max-width:100%; max-height:80vh; }
</style>

{{-- ===== Quick View + Variant Picker + Capture-phase Add-to-cart (same logic as your base) ===== --}}
<script>
(function(){
  const NOIMG    = @json(asset('images/products/no-image.png'));
  const CURRENCY = @json($currency);

  const safeParse = (str) => { try { return JSON.parse(str || '[]'); } catch(e){ return []; } };
  const money     = (v) => CURRENCY + Number(v||0).toFixed(2);
  const html      = (s) => String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m]));

  /* ---------- QUICK VIEW ---------- */
  const qvModalEl = document.getElementById('quickViewModal');
  const qvImg   = document.getElementById('qvImg');
  const qvTitle = document.getElementById('qvTitle');
  const qvPrice = document.getElementById('qvPrice');
  const qvDesc  = document.getElementById('qvDesc');
  const qvWrap  = document.getElementById('qvVariantWrap');
  const qvList  = document.getElementById('qvVariantList');
  const qvInfo  = document.getElementById('qvSelectedInfo');
  const qvBtn   = document.getElementById('qvAddBtn');
  const qvStatus= document.getElementById('qvStatus');

  let qvProduct = null, qvSelected = null;

  document.addEventListener('click', function(e){
    const trigger = e.target.closest('.js-quick-view');
    if(!trigger) return;

    e.preventDefault();

    const variants = safeParse(trigger.dataset.variants);
    qvProduct = {
      id         : Number(trigger.dataset.id),
      slug       : trigger.dataset.slug,
      name       : trigger.dataset.name || '',
      price      : parseFloat(trigger.dataset.price || '0'), // FINAL min price
      image      : trigger.dataset.image || NOIMG,
      currency   : trigger.dataset.currency || CURRENCY,
      description: trigger.dataset.description || '',
      variants   : Array.isArray(variants) ? variants : []
    };

    // Fill UI
    qvImg.src = qvProduct.image || NOIMG;
    qvTitle.textContent = qvProduct.name || '—';
    qvDesc.textContent  = qvProduct.description || '';

    qvList.innerHTML = '';
    qvSelected = null;

    if (qvProduct.variants.length){
      qvWrap.classList.remove('d-none');
      qvProduct.variants.forEach(v => {
        const show = (typeof v.display_price !== 'undefined') ? v.display_price : v.price;
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center justify-content-between';
        li.innerHTML = `
          <div>
            <div class="fw-semibold">${html(v.name || '')}</div>
            <div class="text-muted small">${money(show)}</div>
          </div>
          <div><input class="form-check-input" type="radio" name="qvChoice" value="${v.id}"></div>
        `;
        qvList.appendChild(li);
      });
      qvInfo.textContent = @json(__('messages.SelectVariant'));
      qvBtn.disabled = true;
      qvList.addEventListener('change', onQVChange, { once:true });
      qvPrice.textContent = money(qvProduct.price);
    } else {
      qvWrap.classList.add('d-none');
      qvInfo.textContent = '—';
      qvBtn.disabled = false;
      qvPrice.textContent = money(qvProduct.price);
    }

    if (window.bootstrap && qvModalEl) bootstrap.Modal.getOrCreateInstance(qvModalEl).show();
  });

  function onQVChange(){
    const chosen = qvList.querySelector('input[name="qvChoice"]:checked');
    if (!chosen){ qvSelected = null; qvInfo.textContent = @json(__('messages.SelectVariant')); qvBtn.disabled = true; }
    else {
      const id = Number(chosen.value);
      qvSelected = (qvProduct.variants || []).find(v => Number(v.id) === id) || null;
      if (qvSelected){
        const show = (typeof qvSelected.display_price !== 'undefined') ? qvSelected.display_price : qvSelected.price;
        qvInfo.textContent = `${qvSelected.name || ''} — ${money(show)}`;
        qvPrice.textContent = money(show);
        qvBtn.disabled = false;
      }
    }
    qvList.addEventListener('change', onQVChange, { once:true });
  }

  qvBtn.addEventListener('click', function(){
    if (!qvProduct) return;
    let item;

    if ((qvProduct.variants || []).length){
      if (!qvSelected) { qvStatus.textContent = @json(__('messages.SelectVariant')); return; }
      const priceUse = (typeof qvSelected.display_price !== 'undefined') ? qvSelected.display_price : qvSelected.price;
      item = {
        id: String(qvProduct.id) + ':' + String(qvSelected.id),
        product_id: qvProduct.id,
        product_variant_id: Number(qvSelected.id),
        name: (qvProduct.name || 'Item') + ' — ' + (qvSelected.name || ''),
        variant_name: qvSelected.name || '',
        price: Number(priceUse || 0),
        qty: 1,
        image: qvProduct.image || NOIMG,
        slug: qvProduct.slug || '',
        currency: qvProduct.currency || CURRENCY
      };
    } else {
      item = {
        id: String(qvProduct.id),
        product_id: qvProduct.id,
        name: qvProduct.name || 'Item',
        price: Number(qvProduct.price || 0),
        qty: 1,
        image: qvProduct.image || NOIMG,
        slug: qvProduct.slug || '',
        currency: qvProduct.currency || CURRENCY
      };
    }

    try { CartLS.add(item, 1); } catch(e){ console.error(e); }
    if (window.bootstrap && qvModalEl) bootstrap.Modal.getOrCreateInstance(qvModalEl).hide();
  });

  /* ---------- VARIANT PICKER (for card Add-to-cart) ---------- */
  const vpEl    = document.getElementById('variantPickerModal');
  const vpTitle = document.getElementById('vpProductTitle');
  const vpList  = document.getElementById('vpVariantList');
  const vpInfo  = document.getElementById('vpSelectedInfo');
  const vpBtn   = document.getElementById('vpConfirmBtn');

  let vpProduct = null, vpSelected = null;

  function openVariantPicker(source){
    const variants = safeParse(source.dataset.variants);
    vpProduct = {
      id: Number(source.dataset.productId || source.dataset.id),
      name: source.dataset.name || source.getAttribute('data-name') || '',
      image: source.dataset.productImage || source.dataset.image || NOIMG,
      currency: source.dataset.currency || CURRENCY,
      slug: source.dataset.slug || '',
      variants: Array.isArray(variants) ? variants : []
    };
    vpSelected = null;

    vpTitle.textContent = vpProduct.name || '';
    vpList.innerHTML = '';

    (vpProduct.variants || []).forEach(v => {
      const show = (typeof v.display_price !== 'undefined') ? v.display_price : v.price;
      const li = document.createElement('li');
      li.className = 'list-group-item d-flex align-items-center justify-content-between';
      li.innerHTML = `
        <div>
          <div class="fw-semibold">${html(v.name || '')}</div>
          <div class="text-muted small">${money(show)}</div>
        </div>
        <div><input class="form-check-input" type="radio" name="vpChoice" value="${v.id}"></div>
      `;
      vpList.appendChild(li);
    });

    vpInfo.textContent = @json(__('messages.SelectVariant'));
    vpBtn.disabled = true;

    function onVPChange(){
      const chosen = vpList.querySelector('input[name="vpChoice"]:checked');
      if (!chosen){ vpSelected = null; vpBtn.disabled = true; vpInfo.textContent = @json(__('messages.SelectVariant')); }
      else {
        const id = Number(chosen.value);
        vpSelected = (vpProduct.variants || []).find(v => Number(v.id) === id) || null;
        if (vpSelected){
          const show = (typeof vpSelected.display_price !== 'undefined') ? vpSelected.display_price : vpSelected.price;
          vpInfo.textContent = `${vpSelected.name || ''} — ${money(show)}`;
          vpBtn.disabled = false;
        }
      }
      vpList.addEventListener('change', onVPChange, { once:true, passive:false });
    }
    vpList.addEventListener('change', onVPChange, { once:true, passive:false });

    if (window.bootstrap && vpEl) bootstrap.Modal.getOrCreateInstance(vpEl).show();
  }

  vpBtn.addEventListener('click', function(){
    if (!vpProduct || !vpSelected) return;
    const priceUse = (typeof vpSelected.display_price !== 'undefined') ? vpSelected.display_price : vpSelected.price;
    const item = {
      id: String(vpProduct.id) + ':' + String(vpSelected.id),
      product_id: vpProduct.id,
      product_variant_id: Number(vpSelected.id),
      name: (vpProduct.name || 'Item') + ' — ' + (vpSelected.name || ''),
      variant_name: vpSelected.name || '',
      price: Number(priceUse || 0),
      qty: 1,
      image: vpSelected.image || vpProduct.image || NOIMG,
      slug: vpProduct.slug || '',
      currency: vpProduct.currency || CURRENCY
    };
    try { CartLS.add(item, 1); } catch(e){ console.error(e); }
    if (window.bootstrap && vpEl) bootstrap.Modal.getOrCreateInstance(vpEl).hide();
  });

  // Capture-phase: if .js-add-to-cart has variants, open picker and stop global handler (SAME as your base)
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-add-to-cart');
    if (!btn) return;

    const variants = safeParse(btn.dataset.variants);
    if (Array.isArray(variants) && variants.length){
      e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
      if (!btn.dataset.productId) btn.dataset.productId = btn.dataset.id || '';
      if (!btn.dataset.productImage) btn.dataset.productImage = btn.dataset.image || NOIMG;
      openVariantPicker(btn);
      return;
    }
    // else: no variants => allow global handler on the page to handle add-to-cart
  }, true);
})();
</script>
@endsection
