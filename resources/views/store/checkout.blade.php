@extends('layouts.store')

@section('content')
@php
  $currency = $s->currency_code ?? '$';
  use App\Models\StoreSetting;

  $s = $s ?? StoreSetting::first();
@endphp

<section class="border-bottom bg-gradient-subtle">
  <div class="container py-4">
    <h1 class="h4 mb-0">{{ __('messages.Checkout') }}</h1>
  </div>
</section>

<div class="container py-4" id="checkout-app">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
          <h5 class="mb-3">{{ __('messages.OrderSummary') }}</h5>

          <div id="summary-empty" class="text-center text-muted py-4 d-none">
            <div class="display-6">🛒</div>
            <p class="mt-2">{{ __('messages.YourCartIsEmpty') }}</p>
            <a href="{{ route('store.shop') }}" class="btn btn-outline-primary">{{ __('messages.GoToShop') }}</a>
          </div>

          <div id="summary-list"></div>

          <hr>

          <div class="d-flex justify-content-between small text-muted">
            <span>{{ __('messages.Subtotal') }}</span>
            <strong id="sum-subtotal">{{ $currency }}0.00</strong>
          </div>
          <div class="d-flex justify-content-between h5 mt-2">
            <span>{{ __('messages.GrandTotal') }}</span>
            <strong id="sum-grand">{{ $currency }}0.00</strong>
          </div>

          <div class="mt-3">
            <button class="btn btn-primary w-100 btn-lg" id="btnPlaceOrder">{{ __('messages.PlaceOrder') }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .bg-gradient-subtle{ background: linear-gradient(90deg, rgba(108,92,231,.05), rgba(0,194,255,.05)); }

  /* Order summary rows: perfect column alignment */
  .co-line{
    display: grid;
    grid-template-columns: 54px 1fr 128px 110px 40px; /* thumb | info | qty | line total | remove */
    align-items: center;
    gap: .75rem;
    padding: .5rem 0;
  }
  .co-thumb{ width:54px; height:54px; object-fit:cover; border-radius:.5rem; }

  .qty-mini{
    display: grid;
    grid-template-columns: 28px 64px 28px;
    gap: .25rem; align-items:center; justify-content:center;
    width: 128px; min-width: 128px;
  }
  .qty-mini .form-control{ width:64px; text-align:center; padding-top:.25rem; padding-bottom:.25rem; }
  .qty-mini .btn{ width:28px; height:32px; padding:0; line-height:1; }

  .co-line .js-line{ text-align:right; min-width:110px; }
  .co-line .js-remove{
    width:40px; display:inline-flex; justify-content:center; align-items:center; padding:.25rem;
  }

  @media (max-width: 480px){
    .co-line{ grid-template-columns: 54px 1fr; grid-auto-rows:auto; }
    .qty-mini, .co-line .js-line, .co-line .js-remove{
      margin-left: calc(54px + .75rem);
      margin-top: .4rem;
    }
  }
</style>

<script>
(function(){
  // ---- helpers ----
  var currencyMeta = document.querySelector('meta[name="currency"]');
  var csrfMeta     = document.querySelector('meta[name="csrf-token"]');
  var CURRENCY     = currencyMeta ? currencyMeta.content : @json($currency);
  var CSRF         = csrfMeta ? csrfMeta.content : '';
  var NOIMG        = @json(asset('images/products/no-image.png'));

  function fmt(v){ return CURRENCY + Number(v||0).toFixed(2); }

  function getCart(){
    if (window.CartLS && typeof window.CartLS.get === 'function') return window.CartLS.get();
    try {
      var raw = JSON.parse(localStorage.getItem('shop.cart.v1')||'{}');
      if (!raw || !Array.isArray(raw.items)) return { items:[], currency:CURRENCY, subtotal:0, grand:0 };
      raw.subtotal = raw.items.reduce(function(a,i){ return a + (Number(i.price)||0)*(Number(i.qty)||0); }, 0);
      raw.grand = raw.subtotal;
      return raw;
    } catch(e){ return { items:[], currency:CURRENCY, subtotal:0, grand:0 }; }
  }

  // Extract product + variant ids robustly
  function extractIds(item){
    // Prefer explicit fields (set by shop page for variant items)
    var pid  = item.product_id != null ? Number(item.product_id) : null;
    var pvid = item.product_variant_id != null ? Number(item.product_variant_id) : null;

    // Fallback: parse composite id "productId:variantId"
    if (pid == null){
      var parts = String(item.id||'').split(':');
      pid  = Number(parts[0] || 0) || 0;
      pvid = (pvid != null) ? pvid : (parts[1] ? Number(parts[1]) : null);
    }
    return { product_id: pid, product_variant_id: pvid };
  }

  // ---- DOM nodes ----
  var listEl   = document.getElementById('summary-list');
  var emptyEl  = document.getElementById('summary-empty');
  var subEl    = document.getElementById('sum-subtotal');
  var grandEl  = document.getElementById('sum-grand');
  var btn      = document.getElementById('btnPlaceOrder');

  // ---- render items + totals ----
  function render(){
    var cart = getCart();

    if (!cart.items || !cart.items.length){
      emptyEl.classList.remove('d-none');
      listEl.innerHTML = '';
      subEl.textContent   = fmt(0);
      grandEl.textContent = fmt(0);
      return;
    }

    emptyEl.classList.add('d-none');
    listEl.innerHTML = '';

    cart.items.forEach(function(it){
      var row = document.createElement('div');
      row.className = 'co-line';
      row.dataset.id = it.id; // can be "prod" or "prod:variant"

      var variantBadge = '';
      if (it.variant_name) {
        variantBadge = '<div class="small"><span class="badge text-bg-light">'+ (it.variant_name) +'</span></div>';
      }

      row.innerHTML =
        '<img class="co-thumb" src="'+ (it.image || NOIMG) +'" alt="'+ (it.name||'') +'">' +
        '<div class="flex-grow-1">' +
          '<div class="fw-semibold text-truncate" title="'+ (it.name||'') +'">'+ (it.name||'') +'</div>' +
          variantBadge +
          '<div class="small text-muted">'+ fmt(it.price) +'</div>' +
        '</div>' +
        '<div class="qty-mini">' +
          '<button class="btn btn-outline-secondary btn-sm js-dec" type="button">−</button>' +
          '<input type="number" class="form-control form-control-sm text-center js-qty" value="'+ (it.qty||1) +'" min="1">' +
          '<button class="btn btn-outline-secondary btn-sm js-inc" type="button">+</button>' +
        '</div>' +
        '<div class="ms-2 fw-semibold js-line">'+ fmt((Number(it.price)||0)*(Number(it.qty)||0)) +'</div>' +
        '<button class="btn btn-outline-danger btn-sm ms-2 js-remove" type="button" title="{{ __('messages.Remove') }}"><i class="bi bi-trash"></i></button>';

      listEl.appendChild(row);
    });

    var sub = cart.items.reduce(function(a,i){ return a + (Number(i.price)||0)*(Number(i.qty)||0); }, 0);
    subEl.textContent   = fmt(sub);
    grandEl.textContent = fmt(sub); // no shipping/tax
  }

  // ---- qty / remove handlers ----
  listEl.addEventListener('click', function(e){
    var row = e.target.closest('.co-line'); if(!row) return;
    var id  = row.dataset.id;

    if (e.target.closest('.js-dec')) {
      var inp = row.querySelector('.js-qty');
      var v   = Math.max(1, parseInt(inp.value||'1',10) - 1);
      inp.value = v;
      if (window.CartLS && CartLS.setQty) CartLS.setQty(id, v);
      render();
    }

    if (e.target.closest('.js-inc')) {
      var inp = row.querySelector('.js-qty');
      var v   = Math.max(1, parseInt(inp.value||'1',10) + 1);
      inp.value = v;
      if (window.CartLS && CartLS.setQty) CartLS.setQty(id, v);
      render();
    }

    if (e.target.closest('.js-remove')) {
      if (window.CartLS && CartLS.remove) CartLS.remove(id);
      render();
    }
  });

  listEl.addEventListener('change', function(e){
    var inp = e.target.closest('.js-qty'); if(!inp) return;
    var row = inp.closest('.co-line');
    var id  = row.dataset.id;
    var v   = Math.max(1, parseInt(inp.value||'1',10));
    inp.value = v;
    if (window.CartLS && CartLS.setQty) CartLS.setQty(id, v);
    render();
  });

  // re-render when other parts of the site update the cart
  window.addEventListener('cart:changed', render);

  // ---- place order (server create + thank-you) ----
  var THANKYOU_URL = "{{ url('/online_store/thank-you') }}";
  var CREATE_URL   = "{{ route('store.orders.store') }}"; // /store/orders

  if (btn) {
    btn.addEventListener('click', function(){
      var cart = getCart();
      if (!cart.items || !cart.items.length) { alert('{{ __("messages.YourCartIsEmpty") }}'); return; }

      var items = cart.items.map(function(i){
        var ids = extractIds(i);
        return {
          product_id:         Number(ids.product_id || 0),
          product_variant_id: (ids.product_variant_id != null ? Number(ids.product_variant_id) : null),
          qty:                Number(i.qty||1),
          price:              Number(i.price||0),
          name:               i.name || null
        };
      });

      // filter out any broken rows
      items = items.filter(function(x){ return x.product_id > 0 && x.qty > 0 && x.price >= 0; });
      if (!items.length){ alert('{{ __("messages.YourCartIsEmpty") }}'); return; }

      var payload = { items: items };

      fetch(CREATE_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': CSRF
        },
        body: JSON.stringify(payload)
      })
      .then(function(res){
        if (!res.ok) return res.json().then(function(e){ throw e || new Error('Request failed'); });
        return res.json();
      })
      .then(function(order){
        var subtotal = cart.items.reduce(function(a,i){ return a + (Number(i.price)||0)*(Number(i.qty)||0); }, 0);
        var receipt  = {
          order_id: order.id,
          order_no: order.ref || order.code || ('#'+order.id),
          placed_at: new Date().toISOString(),
          currency: cart.currency || CURRENCY,
          items: cart.items,
          totals: { subtotal: subtotal.toFixed(2), grand: Number(order.total||subtotal).toFixed(2) }
        };
        try { localStorage.setItem('shop.last_order', JSON.stringify(receipt)); } catch(e){}
        try { if (window.CartLS && CartLS.clear) CartLS.clear(); else localStorage.removeItem('shop.cart.v1'); } catch(e){}
        window.location.href = THANKYOU_URL;
      })
      .catch(function(err){
        console.error(err);
        var msg = (err && (err.message || err.error)) || '{{ __("messages.CouldNotPlaceOrder") }}';
        if (err && Array.isArray(err.items) && err.items.length) {
          msg = '{{ __("messages.InsufficientStockFor") }}\n' + err.items.map(function(x){
            return (x.name || ('#'+x.product_id)) + ' — {{ __("messages.Available") }}: ' + x.available + ', {{ __("messages.Required") }}: ' + x.required;
          }).join('\n');
        }
        alert(msg);
      });
    });
  }

  // initial draw
  render();
})();
</script>
@endsection
