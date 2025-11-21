@extends('layouts.store')

@section('content')
@php
  $currency = $s->currency_code ?? 'USD';
@endphp

<section class="border-bottom bg-gradient-subtle">
  <div class="container py-4 d-flex align-items-center justify-content-between">
    <div>
      <h1 class="h4 mb-1">{{ __('messages.MyOrders') }}</h1>
      <div class="text-muted small">{{ __('messages.TrackOrdersStatus') }}</div>
    </div>
    <a href="{{ url('/online_store/account') }}" class="btn btn-outline-secondary">
      <i class="bi bi-person me-1"></i> {{ __('messages.Account') }}
    </a>
  </div>
</section>

<div class="container py-4" id="orders-app">
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div>
          <label class="form-label small mb-1">{{ __('messages.Search') }}</label>
          <input type="text" id="ord-q" class="form-control form-control-sm" placeholder="{{ __('messages.RefOrDate') }}">
        </div>
        <div>
          <label class="form-label small mb-1">{{ __('messages.Status') }}</label>
          <select id="ord-status" class="form-select form-select-sm">
            <option value="">{{ __('messages.All') }}</option>
            <option value="pending">{{ __('messages.pending') }}</option>
            <option value="confirmed">{{ __('messages.confirmed') }}</option>
            <option value="cancelled">{{ __('messages.cancelled') }}</option>
          </select>
        </div>
        <button id="ord-refresh" class="btn btn-sm btn-primary">
          <i class="bi bi-arrow-repeat"></i> {{ __('messages.Update') }}
        </button>
      </div>

      <div id="ord-empty" class="text-center text-muted py-5 d-none">
        <div class="display-6">📦</div>
        <p class="mt-2 mb-4">{{ __('messages.NoOrdersYet') }}</p>
        <a href="{{ route('store.shop') }}" class="btn btn-outline-primary">
          <i class="bi bi-bag"></i> {{ __('messages.GoShopping') }}
        </a>
      </div>

      <div class="table-responsive">
        <table class="table align-middle" id="ord-table">
          <thead>
            <tr>
              <th>{{ __('messages.Ref') }}</th>
              <th>{{ __('messages.Date') }}</th>
              <th class="text-center">{{ __('messages.Status') }}</th>
              <th class="text-end">{{ __('messages.Total') }}</th>
              <th class="text-end">{{ __('messages.Action') }}</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>

      <nav class="mt-3">
        <ul class="pagination pagination-sm mb-0" id="ord-pager"></ul>
      </nav>
    </div>
  </div>
</div>

<script>
(function(){
  const CURRENCY = document.querySelector('meta[name="currency"]')?.content || @json($currency);
  const tableBody = document.querySelector('#ord-table tbody');
  const pager     = document.getElementById('ord-pager');
  const emptyBox  = document.getElementById('ord-empty');
  const qInp      = document.getElementById('ord-q');
  const stSel     = document.getElementById('ord-status');
  const btnRef    = document.getElementById('ord-refresh');

  // Localized status labels
  const STATUS_LABELS = {
    pending:   @json(__('messages.pending')),
    confirmed: @json(__('messages.confirmed')),
    cancelled: @json(__('messages.cancelled')),
  };

  function money(n){
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: CURRENCY })
        .format(Number(n || 0));
    } catch(e){
      return (CURRENCY || '$') + Number(n || 0).toFixed(2);
    }
  }

  async function fetchOrders(page = 1){
    const params = new URLSearchParams({
      page: page,
      q: (qInp.value || '').trim(),
      status: stSel.value || '',
      mine: '1'
    });

    // Try "my orders" first; fall back to generic
    const urls = [
      '/online_store/my/orders?' + params.toString(),
      '/online_store/orders?' + params.toString()
    ];

    for (const u of urls) {
      try {
        const r = await fetch(u, { headers: { 'Accept': 'application/json' } });
        if (!r.ok) continue;
        const data = await r.json();
        return normalize(data);
      } catch(e){ /* try next */ }
    }
    return { rows: [], total: 0, page: 1, pages: 1 };
  }

  function normalize(resp){
    // Accept {data:[...], meta:{total,page,pages}} OR array
    const rows = Array.isArray(resp) ? resp : (resp.data || resp.rows || []);
    const meta = resp.meta || {};
    return {
      rows,
      total: meta.total ?? rows.length,
      page:  meta.page  ?? 1,
      pages: meta.pages ?? 1
    };
  }

  function rowHtml(o){
    const code   = o.code || o.ref || ('#' + o.id);
    const date   = o.date || o.created_at || '';
    const total  = money(o.total || 0);
    const status = String(o.status || '').toLowerCase();

    const badgeClass =
      status === 'confirmed' ? 'success' :
      status === 'pending'   ? 'warning' :
      status === 'cancelled' ? 'danger'  : 'secondary';

    // Prefer localized label when known
    const statusLabel = STATUS_LABELS[status] || status || '—';

    const viewUrlBase = @json(url('/online_store/account/orders/'));
    const viewUrl     = viewUrlBase + '/' + o.id;

    return `
      <tr>
        <td class="fw-semibold">${code}</td>
        <td>${date}</td>
        <td class="text-center"><span class="badge text-bg-${badgeClass} text-uppercase">${statusLabel}</span></td>
        <td class="text-end">${total}</td>
        <td class="text-end">
          <a class="btn btn-sm btn-outline-primary" href="${viewUrl}">
            <i class="bi bi-eye"></i> {{ __('messages.View') }}
          </a>
        </td>
      </tr>
    `;
  }

  function renderPager(page, pages){
    pager.innerHTML = '';
    if (pages <= 1) return;

    const make = (p, label, active=false, disabled=false) => {
      const li = document.createElement('li');
      li.className = 'page-item' + (active ? ' active' : '') + (disabled ? ' disabled' : '');
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.textContent = label;
      a.addEventListener('click', (e) => { e.preventDefault(); if (!disabled) load(p); });
      li.appendChild(a);
      pager.appendChild(li);
    };

    make(Math.max(1, page - 1), '«', false, page === 1);
    for (let i = 1; i <= pages; i++) make(i, String(i), i === page, false);
    make(Math.min(pages, page + 1), '»', false, page === pages);
  }

  async function load(page = 1){
    const res = await fetchOrders(page);
    if (!res.rows.length){
      emptyBox.classList.remove('d-none');
      tableBody.innerHTML = '';
      pager.innerHTML = '';
      return;
    }
    emptyBox.classList.add('d-none');
    tableBody.innerHTML = res.rows.map(rowHtml).join('');
    renderPager(res.page, res.pages);
  }

  btnRef.addEventListener('click', () => load(1));
  qInp.addEventListener('keydown', (e) => { if (e.key === 'Enter') load(1); });
  stSel.addEventListener('change', () => load(1));

  // Initial render
  load(1);
})();
</script>
@endsection
