{{-- resources/views/store/account/order-show.blade.php --}}
@extends('layouts.store')

@section('content')
@php
  $currency = $s->currency_code ?? '$';
  use App\Models\StoreSetting;

  $s = $s ?? StoreSetting::first();
@endphp

<section class="border-bottom bg-light">
  <div class="container py-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <a href="{{ route('account.orders') }}" class="text-decoration-none">
          <i class="bi bi-arrow-left"></i> {{ __('messages.BackToOrders') }}
        </a>
        <h1 class="h5 mb-0 mt-2">{{ __('messages.OrderDetails') }}</h1>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" id="btnPrint">
          <i class="bi bi-printer"></i> {{ __('messages.Print') }}
        </button>
      </div>
    </div>
  </div>
</section>

<div class="container py-4" id="order-app" data-order-id="{{ $id ?? request()->route('id') }}">
  <div class="row justify-content-center">
    <div class="col-lg-10">

      {{-- Header card --}}
      <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body p-4">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
              <div class="text-muted small">{{ __('messages.Order') }}</div>
              <h3 class="h5 mb-0">
                <span id="o-code">—</span>
              </h3>
              <div class="small text-muted mt-1">
                <span id="o-date">—</span> • <span id="o-time">—</span>
              </div>
            </div>
            <div class="text-end">
              <span id="o-status-badge" class="badge rounded-pill bg-secondary">—</span>
              <div class="small text-muted mt-2">
                <i class="bi bi-shop"></i>
                <span id="o-warehouse">—</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Items + Summary --}}
      <div class="row">
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-0">
              <div class="p-3 border-bottom">
                <h6 class="mb-0">{{ __('messages.Items') }}</h6>
              </div>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>{{ __('messages.Product') }}</th>
                      <th class="text-center" style="width:100px">{{ __('messages.Qty') }}</th>
                      <th class="text-end" style="width:140px">{{ __('messages.Price') }}</th>
                      <th class="text-end" style="width:160px">{{ __('messages.Total') }}</th>
                    </tr>
                  </thead>
                  <tbody id="o-items">
                    <tr><td colspan="4" class="text-center text-muted py-4">—</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-4">
              <h6 class="mb-3">{{ __('messages.Summary') }}</h6>
              <ul class="list-unstyled mb-0">
                <li class="d-flex justify-content-between small text-muted">
                  <span>{{ __('messages.Subtotal') }}</span>
                  <strong id="o-subtotal">{{ $currency }}0.00</strong>
                </li>
                <li class="d-flex justify-content-between small text-muted mt-2">
                  <span>{{ __('messages.Shipping') }}</span>
                  <strong id="o-shipping">{{ $currency }}0.00</strong>
                </li>
                <li class="d-flex justify-content-between small text-muted mt-2">
                  <span>{{ __('messages.Discount') }}</span>
                  <strong id="o-discount">-{{ $currency }}0.00</strong>
                </li>
                <li class="d-flex justify-content-between h5 mt-3 border-top pt-3">
                  <span>{{ __('messages.Total') }}</span>
                  <strong id="o-total">{{ $currency }}0.00</strong>
                </li>
              </ul>
            </div>
          </div>

          <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
              <h6 class="mb-2">{{ __('messages.StatusHelp') }}</h6>
              <ul class="small text-muted mb-0">
                <li><span class="badge bg-warning">pending</span> {{ __('messages.StatusPendingHelp') }}</li>
                <li><span class="badge bg-success">confirmed</span> {{ __('messages.StatusConfirmedHelp') }}</li>
                <li><span class="badge bg-danger">cancelled</span> {{ __('messages.StatusCancelledHelp') }}</li>
              </ul>
            </div>
          </div>

        </div>
      </div>

      {{-- Empty state (hidden by default) --}}
      <div id="o-empty" class="text-center text-muted py-5 d-none">
        <div class="display-6">🧾</div>
        <p class="mt-2">{{ __('messages.OrderNotFound') }}</p>
        <a href="{{ route('account.orders') }}" class="btn btn-outline-primary">{{ __('messages.BackToOrders') }}</a>
      </div>

    </div>
  </div>
</div>

<style>
  .badge.pending   { background:#f59f00 !important; }  /* warning */
  .badge.confirmed { background:#2fb344 !important; }  /* success */
  .badge.cancelled { background:#fa5252 !important; }  /* danger */
</style>

<script>
(function(){
  const wrap  = document.getElementById('order-app');
  const id    = wrap?.dataset.orderId;
  const cur   = document.querySelector('meta[name="currency"]')?.content || '{{ $currency }}';

  const el = {
    code:      document.getElementById('o-code'),
    date:      document.getElementById('o-date'),
    time:      document.getElementById('o-time'),
    badge:     document.getElementById('o-status-badge'),
    wh:        document.getElementById('o-warehouse'),
    items:     document.getElementById('o-items'),
    subtotal:  document.getElementById('o-subtotal'),
    shipping:  document.getElementById('o-shipping'),
    discount:  document.getElementById('o-discount'),
    total:     document.getElementById('o-total'),
    empty:     document.getElementById('o-empty'),
  };

  function money(n){ return cur + Number(n||0).toFixed(2); }
  function badgeClass(status){
    status = String(status||'').toLowerCase();
    return status === 'pending'   ? 'badge pending'
         : status === 'confirmed' ? 'badge confirmed'
         : status === 'cancelled' ? 'badge cancelled'
         : 'badge bg-secondary';
  }

  async function load(){
    if (!id) return showEmpty();
    try{
      const res = await fetch(`/online_store/my/orders/${id}`, { headers:{'Accept':'application/json'} });
      if (!res.ok) throw new Error('not ok');
      const o = await res.json();

      el.code.textContent = o.code || ('#'+o.id);
      el.date.textContent = o.date || '—';
      el.time.textContent = o.time || '—';
      el.wh.textContent   = o.warehouse_name || '—';

      el.badge.className  = badgeClass(o.status);
      el.badge.textContent= o.status || '—';

      // items
      el.items.innerHTML = '';
      if (Array.isArray(o.items) && o.items.length){
        o.items.forEach(it=>{
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${escapeHtml(it.name||('#'+it.product_id))}</td>
            <td class="text-center">${Number(it.qty||0)}</td>
            <td class="text-end">${money(it.price)}</td>
            <td class="text-end">${money((it.price||0)*(it.qty||0))}</td>
          `;
          el.items.appendChild(tr);
        });
      } else {
        el.items.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-4">—</td></tr>`;
      }

      el.subtotal.textContent = money(o.subtotal);
      el.shipping.textContent = money(o.shipping||0);
      el.discount.textContent = '-' + money(o.discount||0);
      el.total.textContent    = money(o.total);

    } catch(e){
      showEmpty();
    }
  }

  function showEmpty(){
    el.empty?.classList.remove('d-none');
  }

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, m => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
  }

  document.getElementById('btnPrint')?.addEventListener('click', ()=> window.print());

  load();
})();
</script>
@endsection
