@extends('layouts.store')

@section('content')
@php
  $currency = $s->currency_code ?? '$';
  use App\Models\StoreSetting;

  $s = $s ?? StoreSetting::first();
@endphp

<section class="border-bottom bg-gradient-subtle">
  <div class="container py-4 text-center">
    <h1 class="h4 mb-0">{{ __('messages.ThankYou') }}</h1>
  </div>
</section>

<div class="container py-5" id="ty-app">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
          <div id="ty-empty" class="text-center text-muted d-none">
            <div class="display-6">🛒</div>
            <p class="mt-2">{{ __('messages.NoRecentOrder') }}</p>
            <a href="{{ route('store.shop') }}" class="btn btn-outline-primary">{{ __('messages.GoToShop') }}</a>
          </div>

          <div id="ty-receipt" class="d-none">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <div>
                <h5 class="mb-0">{{ __('messages.OrderPlaced') }}</h5>
                <div class="text-muted small" id="ty-date">—</div>
              </div>
              <div class="brand-badge" id="ty-no">#—</div>
            </div>

            <div id="ty-list"></div>

            <hr>

            <div class="d-flex justify-content-between small text-muted">
              <span>{{ __('messages.Subtotal') }}</span>
              <strong id="ty-subtotal">{{ $currency }}0.00</strong>
            </div>
            <div class="d-flex justify-content-between h5 mt-2">
              <span>{{ __('messages.GrandTotal') }}</span>
              <strong id="ty-grand">{{ $currency }}0.00</strong>
            </div>

            <div class="mt-4 d-flex gap-2">
              <a href="{{ route('store.shop') }}" class="btn btn-primary">{{ __('messages.ContinueShopping') }}</a>
              <button class="btn btn-outline-secondary" id="ty-print"><i class="bi bi-printer"></i> {{ __('messages.Print') }}</button>
            </div>
          </div>
        </div>
      </div>

      <div class="small text-muted text-center mt-3">
        {{ __('messages.ReceiptSavedInBrowser') }}
      </div>
    </div>
  </div>
</div>

<style>
  .bg-gradient-subtle{ background: linear-gradient(90deg, rgba(108,92,231,.05), rgba(0,194,255,.05)); }
  .ty-thumb{ width:54px; height:54px; object-fit:cover; border-radius:.5rem; }
  .ty-line{ display:flex; align-items:center; gap:.75rem; padding:.5rem 0; }
</style>

<script>
(function(){
  const CURRENCY = document.querySelector('meta[name="currency"]')?.content || @json($currency);
  const NOIMG    = @json(asset('images/products/no-image.png'));
  const fmt = v => CURRENCY + Number(v || 0).toFixed(2);

  const empty   = document.getElementById('ty-empty');
  const wrap    = document.getElementById('ty-receipt');
  const noEl    = document.getElementById('ty-no');
  const dateEl  = document.getElementById('ty-date');
  const listEl  = document.getElementById('ty-list');
  const subEl   = document.getElementById('ty-subtotal');
  const grandEl = document.getElementById('ty-grand');
  const printBtn= document.getElementById('ty-print');

  let rec = null;
  try { rec = JSON.parse(localStorage.getItem('shop.last_order') || 'null'); } catch(e){ rec = null; }

  if (!rec || !Array.isArray(rec.items) || !rec.items.length){
    empty.classList.remove('d-none');
    return;
  }

  wrap.classList.remove('d-none');
  noEl.textContent = '#' + (rec.order_no || '—');

  const dt = new Date(rec.placed_at || Date.now());
  const htmlLang = document.documentElement.getAttribute('lang') || undefined;
  dateEl.textContent = htmlLang ? dt.toLocaleString(htmlLang) : dt.toLocaleString();

  listEl.innerHTML = '';
  rec.items.forEach(it => {
    const name = String(it.name || '');
    const line = document.createElement('div');
    line.className = 'ty-line';
    line.innerHTML = `
      <img class="ty-thumb" src="${it.image || NOIMG}" alt="${name.replace(/"/g,'&quot;')}">
      <div class="flex-grow-1">
        <div class="fw-semibold text-truncate" title="${name.replace(/"/g,'&quot;')}">${name}</div>
        <div class="small text-muted">{{ __('messages.Qty') }}: ${Number(it.qty||0)}</div>
      </div>
      <div class="fw-semibold">${fmt((Number(it.price)||0) * (Number(it.qty)||0))}</div>
    `;
    listEl.appendChild(line);
  });

  const subtotal = Number(rec.totals?.subtotal || 0);
  subEl.textContent   = fmt(subtotal);
  grandEl.textContent = fmt(Number(rec.totals?.grand ?? subtotal));

  printBtn?.addEventListener('click', () => window.print());
})();
</script>

@endsection
