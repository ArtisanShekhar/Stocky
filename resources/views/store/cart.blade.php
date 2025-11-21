@extends('layouts.store')

@section('content')
<section class="border-bottom bg-gradient-subtle">
  <div class="container py-4">
    <h1 class="h3 mb-0">{{ __('messages.YourCart') }}</h1>
  </div>
</section>

<div class="container py-4" id="cart-page">
  <div id="cart-empty" class="text-center py-5 d-none">
    <div class="display-6 mb-2">🛒</div>
    <h5 class="mb-2">{{ __('messages.YourCartEmpty') }}</h5>
    <a href="{{ route('store.shop') }}" class="btn btn-primary mt-2">{{ __('messages.GoToShop') }}</a>
  </div>

  <div id="cart-filled" class="d-none">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead class="table-light">
          <tr>
            <th>{{ __('messages.Product') }}</th>
            <th class="text-end">{{ __('messages.Price') }}</th>
            <th class="text-center" style="width:160px">{{ __('messages.Qty') }}</th>
            <th class="text-end">{{ __('messages.Total') }}</th>
            <th style="width:56px"></th>
          </tr>
        </thead>
        <tbody id="cart-body"></tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="text-end text-muted">{{ __('messages.Subtotal') }}</td>
            <td class="text-end fw-semibold"><span id="subtotal-val">$0.00</span></td>
            <td></td>
          </tr>
          <tr>
            <td colspan="3" class="text-end h5">{{ __('messages.GrandTotal') }}</td>
            <td class="text-end h5"><span id="grand-val">$0.00</span></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="d-flex justify-content-between mt-3">
      <a href="{{ route('store.shop') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> {{ __('messages.ContinueShopping') }}
      </a>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-danger" id="btn-clear">{{ __('messages.ClearCart') }}</button>
        <button class="btn btn-primary" id="btn-checkout">{{ __('messages.Checkout') }}</button>
      </div>
    </div>
  </div>
</div>

<style>
  .bg-gradient-subtle{ background: linear-gradient(90deg, rgba(108,92,231,.05), rgba(0,194,255,.05)); }
  .qty-group{ display:flex; align-items:center; justify-content:center; gap:.25rem; }
  .qty-group input{ width:70px; text-align:center; }
  .variant-pill{
    display:inline-flex; align-items:center; gap:.35rem;
    background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;
    border-radius:999px; padding:.1rem .5rem; font-size:.75rem;
  }
  .prod-thumb{ width:64px; height:64px; object-fit:cover; border-radius:.5rem; }
</style>

<script>
(function(){
  const $empty       = document.getElementById('cart-empty');
  const $filled      = document.getElementById('cart-filled');
  const $body        = document.getElementById('cart-body');
  const $subtotal    = document.getElementById('subtotal-val');
  const $grand       = document.getElementById('grand-val');
  const $btnClear    = document.getElementById('btn-clear');
  const $btnCheckout = document.getElementById('btn-checkout');

  const CURRENCY   = document.querySelector('meta[name="currency"]')?.content || '$';
  const NOIMG      = @json(asset('images/products/no-image.png'));
  const T_REMOVE   = @json(__('messages.Remove'));
  const T_DECR     = @json(__('messages.Decrease'));
  const T_INCR     = @json(__('messages.Increase'));
  const T_VARIANT  = @json(__('messages.Variant'));

  function money(val, currency) {
    return (currency || CURRENCY) + (Number(val || 0).toFixed(2));
  }

  function escapeHtml(s){
    return String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  function variantBadge(it){
    if (it.variant_name && String(it.variant_name).trim()) {
      return `<span class="variant-pill"><i class="bi bi-grid-3x3-gap-fill"></i> ${escapeHtml(it.variant_name)}</span>`;
    }
    if (it.product_variant_id) {
      return `<span class="variant-pill"><i class="bi bi-grid-3x3-gap-fill"></i> ${T_VARIANT} #${String(it.product_variant_id)}</span>`;
    }
    if (typeof it.id === 'string' && it.id.includes(':')) {
      const parts = it.id.split(':');
      if (parts[1]) {
        return `<span class="variant-pill"><i class="bi bi-grid-3x3-gap-fill"></i> ${T_VARIANT} #${parts[1]}</span>`;
      }
    }
    return '';
  }

  function render() {
    const cart = (window.CartLS && window.CartLS.get) ? CartLS.get() : {items:[], currency:CURRENCY, subtotal:0, grand:0};

    if (!cart.items.length) {
      $empty.classList.remove('d-none');
      $filled.classList.add('d-none');
      return;
    }
    $empty.classList.add('d-none');
    $filled.classList.remove('d-none');

    $body.innerHTML = '';
    for (const it of cart.items) {
      const tr = document.createElement('tr');
      tr.dataset.id = it.id;

      const vBadge   = variantBadge(it);
      const imgSrc   = it.image || NOIMG;
      const safeName = escapeHtml(it.name);

      tr.innerHTML = `
        <td>
          <div class="d-flex align-items-center gap-3">
            <img src="${imgSrc}" alt="${safeName}" class="prod-thumb">
            <div>
              <div class="fw-semibold">${safeName}</div>
              ${vBadge ? `<div class="mt-1">${vBadge}</div>` : ''}
              <div class="text-muted small">#${escapeHtml(String(it.id))}</div>
            </div>
          </div>
        </td>
        <td class="text-end">${money(it.price, it.currency)}</td>
        <td class="text-center">
          <div class="input-group input-group-sm justify-content-center">
            <button class="btn btn-outline-secondary js-dec" type="button" aria-label="${T_DECR}">−</button>
            <input type="number" class="form-control text-center js-qty" min="1" value="${it.qty}" style="max-width:70px">
            <button class="btn btn-outline-secondary js-inc" type="button" aria-label="${T_INCR}">+</button>
          </div>
        </td>
        <td class="text-end fw-semibold js-line">${money((Number(it.price)||0) * (Number(it.qty)||0), it.currency)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-danger js-remove" title="${T_REMOVE}"><i class="bi bi-trash"></i></button>
        </td>
      `;
      $body.appendChild(tr);
    }

    $subtotal.textContent = money(cart.subtotal, cart.currency);
    $grand.textContent    = money(cart.grand, cart.currency);
  }

  // Qty +/- / remove
  document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-id]');
    if (!row) return;

    if (e.target.closest('.js-dec')) {
      const input = row.querySelector('.js-qty');
      input.value = Math.max(1, parseInt(input.value || '1', 10) - 1);
      CartLS.setQty(row.dataset.id, parseInt(input.value, 10));
      render(); return;
    }
    if (e.target.closest('.js-inc')) {
      const input = row.querySelector('.js-qty');
      input.value = Math.max(1, parseInt(input.value || '1', 10) + 1);
      CartLS.setQty(row.dataset.id, parseInt(input.value, 10));
      render(); return;
    }
    if (e.target.closest('.js-remove')) {
      CartLS.remove(row.dataset.id);
      render(); return;
    }
  });

  // Manual qty change
  document.addEventListener('change', (e) => {
    const input = e.target.closest('.js-qty');
    if (!input) return;
    const row = input.closest('tr[data-id]');
    const val = Math.max(1, parseInt(input.value || '1', 10));
    input.value = val;
    CartLS.setQty(row.dataset.id, val);
    render();
  });

  // Clear
  $btnClear?.addEventListener('click', () => {
    CartLS.clear();
    render();
  });

  // Checkout: require login; otherwise show auth modal from layout
  $btnCheckout?.addEventListener('click', (e)=>{
    e.preventDefault();
    const CHECKOUT_URL = @json(route('checkout'));
    if (window.__LOGGED_IN__) {
      window.location.href = CHECKOUT_URL;
    } else {
      const modalEl = document.getElementById('authModal');
      if (window.bootstrap && modalEl) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
      } else {
        window.location.href = @json(route('store.login.page', [], false));
      }
    }
  });

  // Initial & react to changes elsewhere
  render();
  window.addEventListener('cart:changed', render);
})();
</script>
@endsection
