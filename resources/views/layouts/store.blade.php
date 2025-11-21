{{-- resources/views/layouts/store.blade.php (refactored to use messages.* keys) --}}
@php
  use Illuminate\Support\Str;

  // Safe defaults
  $primary   = $s->primary_color   ?? '#6c5ce7';
  $secondary = $s->secondary_color ?? '#00c2ff';
  $title     = $s->seo_meta_title  ?? ($s->store_name ?? __('messages.Store'));
  $desc      = $s->seo_meta_description ?? '';

  // Social links may be array or JSON string — normalize to [{platform,url}]
  $social = $s->social_links ?? [];
  if (is_string($social)) {
      $d = json_decode($social, true);
      if (json_last_error() === JSON_ERROR_NONE) $social = $d;
  }
  if (!is_array($social)) { $social = []; }
  // assoc → convert {platform:url} to [{platform,url}]
  $isAssoc = array_keys($social) !== range(0, count($social) - 1);
  if ($isAssoc) {
      $social = collect($social)->map(fn($u,$p)=>['platform'=>$p,'url'=>$u])->values()->all();
  }

  // Resolve asset path whether it's absolute or relative
  $assetPath = function ($p) {
      if (!$p) return '';
      return Str::startsWith($p, ['/','http://','https://']) ? $p : asset($p);
  };

  // Store-account (customer) from 'store' guard
  $client = Auth::guard('store')->user();

  // Account links (safe fallbacks if the named routes don't exist)
  $accountUrl   = url('/online_store/account');
  $ordersUrl    = url('/online_store/account/orders');
  $logoutUrl    = url('/online_store/logout');
  $loginUrl     = url('/online_store/login');
  $registerUrl  = url('/online_store/register');

  // Avatar helpers
  $displayName = $client ? ($client->username ?: ($client->email ?? __('messages.Account'))) : '';
  $initial     = $client ? Str::upper(Str::substr($displayName, 0, 1)) : '';
  $avatar      = $client ? ($client->avatar_path ?? $client->avatar_url ?? null) : null;
  $avatarSrc   = $avatar
                  ? (Str::startsWith($avatar, ['http://','https://','/']) ? $avatar : asset($avatar))
                  : null;

  // Local assets (no leading slash to avoid //)
  $cssBootstrap       = asset('store_files/css/bootstrap.min.css');
  $cssBootstrapIcons  = asset('store_files/css/bootstrap-icons.css');
  $jsBootstrap        = asset('store_files/js/bootstrap.bundle.min.js');

  // RTL detection
  $rtlLocales = ['ar','he','fa','ur'];
@endphp
<!doctype html>
<html lang="{{ str_replace('_','-', app()->getLocale() ?? 'en') }}" dir="{{ in_array(app()->getLocale(), $rtlLocales) ? 'rtl' : 'ltr' }}">
<head>
  <meta charset="utf-8" />
  <title>{{ $title }}</title>
  <meta name="description" content="{{ $desc }}" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="currency" content="{{ $s->currency_code ?? '$' }}">
  <script>window.__LOGGED_IN__ = @json(Auth::guard('store')->check());</script>

  @if(!empty($s->favicon_path))
    <link rel="icon" href="{{ $assetPath($s->favicon_path) }}" />
  @endif

  {{-- Local styles (no CDNs) --}}
  <link href="{{ $cssBootstrap }}" rel="stylesheet">
  <link href="{{ $cssBootstrapIcons }}" rel="stylesheet">

  <style>
    :root{ --brand: {{ $primary }}; --brand-2: {{ $secondary }}; }
    body{ font-family: {{ $s->font_family ?? "Arial, sans-serif" }}; }
    .topbar{background:linear-gradient(90deg,var(--brand),var(--brand-2));color:#fff;}
    .brand-badge{background:#fff;color:var(--brand);padding:.25rem .6rem;border-radius:1rem;font-weight:600;font-size:.75rem}
    .hero{background: radial-gradient(1200px 300px at 10% 0%, #e9e7ff 0%, transparent 60%),
                   radial-gradient(900px 260px at 90% 0%, #ddf6ff 0%, transparent 60%),
                   linear-gradient(180deg,#fff, #f7f8fb);}
    .navbar .btn .badge { transform: translate(-30%, -30%); }
    .avatar-initials{ width:32px;height:32px;line-height:32px;display:inline-block;background:var(--brand);
      color:#fff;border-radius:50%;text-align:center;font-weight:600;letter-spacing:.3px; }
    {!! $s->custom_css ?? '' !!}
  </style>
</head>
<body>

  <!-- Page Loader -->
  <div id="page-loader" class="d-flex justify-content-center align-items-center position-fixed top-0 start-0 w-100 h-100 bg-white" style="z-index:2000;">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">@lang('messages.Loading')</span>
      </div>
  </div>

  {{-- Topbar --}}
  <div class="topbar py-2">
    <div class="container d-flex align-items-center justify-content-between small">
      <div>{{ $s->topbar_text_left ?? __('messages.TopbarLeft') }}</div>
      <div class="d-none d-md-block"><span class="brand-badge">{{ __('messages.New') }}</span> {{ $s->topbar_text_right ?? __('messages.TopbarRight') }}</div>
    </div>
  </div>

  {{-- Navbar --}}
  <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container">
      <a class="navbar-brand fw-bold d-flex align-items-center" href="{{ route('store.index') }}">
        @if(!empty($s->logo_path))
          <img src="{{ $assetPath($s->logo_path) }}" alt="{{ $s->store_name ?? __('messages.Store') }}" height="32" class="me-2">
        @endif
        <span class="text-primary">{{ $s->store_name ?? __('messages.Store') }}</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navMain">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="{{ route('store.index') }}">{{ __('messages.Home') }}</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="{{ route('store.shop') }}">{{ __('messages.Shop') }}</a>
          </li>

          <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="langDropdown" role="button"
            data-bs-toggle="dropdown" aria-expanded="false">
            🌐 {{ strtoupper(app()->getLocale()) }}
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="langDropdown">
            <li><a class="dropdown-item" href="{{ route('lang.switch', 'en') }}">English</a></li>
            <li><a class="dropdown-item" href="{{ route('lang.switch', 'fr') }}">Français</a></li>
            <li><a class="dropdown-item" href="{{ route('lang.switch', 'ar') }}">Arabic</a></li>
            <li><a class="dropdown-item" href="{{ route('lang.switch', 'es') }}">Español</a></li>
          </ul>
        </li>

        </ul>

        {{-- Account (right side) --}}
        <div class="d-flex align-items-center gap-2 ms-lg-3 mt-3 mt-lg-0">

          @if($client)
            {{-- Account dropdown --}}
            <div class="dropdown">
              <a class="btn btn-light d-flex align-items-center gap-2 dropdown-toggle" href="#" id="accountMenu"
                 data-bs-toggle="dropdown" aria-expanded="false">
                @if($avatarSrc)
                  <img src="{{ $avatarSrc }}"
                       onerror="this.src='{{ asset('images/avatar-placeholder.png') }}'"
                       alt="avatar" width="32" height="32" class="rounded-circle">
                @else
                  <span class="avatar-initials">{{ $initial }}</span>
                @endif
                <span class="d-none d-md-inline text-truncate" style="max-width:160px;">
                  {{ Str::limit($displayName, 18) }}
                </span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="accountMenu">
                <li>
                  <a class="dropdown-item" href="{{ $accountUrl }}">
                    <i class="bi bi-person me-2"></i> {{ __('messages.Profile') }}
                  </a>
                </li>
                <li>
                  <a class="dropdown-item" href="{{ $ordersUrl }}">
                    <i class="bi bi-receipt me-2"></i> {{ __('messages.MyOrders') }}
                  </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                  <form method="POST" action="{{ $logoutUrl }}">
                    @csrf
                    <button type="submit" class="dropdown-item">
                      <i class="bi bi-box-arrow-right me-2"></i> {{ __('messages.Logout') }}
                    </button>
                  </form>
                </li>
              </ul>
            </div>
          @else
            {{-- Guest: Sign in / Register --}}
            <a class="btn btn-sm btn-outline-primary" href="{{ $loginUrl }}">{{ __('messages.SignIn') }}</a>
            <a class="btn btn-sm btn-primary" href="{{ $registerUrl }}">{{ __('messages.CreateAccount') }}</a>
          @endif

          {{-- Cart button (LocalStorage offcanvas) --}}
          <button type="button"
                  class="btn btn-outline-secondary position-relative"
                  data-bs-toggle="offcanvas"
                  data-bs-target="#miniCart"
                  aria-controls="miniCart"
                  title="{{ __('messages.Cart') }}">
            <i class="bi bi-cart"></i>
            <span id="cart-badge"
                  class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                  style="min-width:1.25rem; font-size:.7rem; line-height:1;">0</span>
          </button>
        </div>
      </div>
    </div>
  </nav>

  {{-- Page content --}}
  @yield('content')

  {{-- Footer --}}
  <footer class="pt-5 bg-dark text-light mt-5">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-4">
          <h5>{{ $s->store_name ?? __('messages.Store') }}</h5>
          <p class="small">{{ $s->footer_text ?? __('messages.FooterAbout') }}</p>
        </div>

        <div class="col-6 col-md-2">
          <h6>{{ __('messages.Shop') }}</h6>
          <ul class="list-unstyled small">
            <li><a class="link-light text-decoration-none" href="{{ route('store.shop') }}">{{ __('messages.AllProducts') }}</a></li>
          </ul>
        </div>

        <div class="col-6 col-md-2">
          <h6>{{ __('messages.Support') }}</h6>
          <ul class="list-unstyled small">
            <li><a class="link-light text-decoration-none" href="{{ route('store.contact') }}">{{ __('messages.ContactUs') }}</a></li>
          </ul>
        </div>

       <div class="col-md-4">
          <h6>{{ __('messages.FollowUs') }}</h6>
          <div class="d-flex gap-2">
            @php
              $iconMap = [
                'facebook' => 'facebook',
                'instagram' => 'instagram',
                'x' => 'twitter-x',
                'twitter' => 'twitter-x',
                'youtube' => 'youtube',
                'tiktok' => 'tiktok',
                'github' => 'github',
                'linkedin' => 'linkedin',
                'telegram' => 'telegram',
                'slack' => 'slack',
                'discord' => 'discord',
                'reddit' => 'reddit',
                'whatsapp' => 'whatsapp',
                'pinterest' => 'pinterest',
                'snapchat' => 'snapchat',
                'dribbble' => 'dribbble',
                'behance' => 'behance',
                'medium' => 'medium',
                'vk' => 'vk',
                'weibo' => 'weibo',
                'wechat' => 'wechat',
              ];
              $defaultIcon = 'link-45deg'; // fallback if platform icon doesn't exist
            @endphp

            @foreach(($social ?? []) as $item)
              @php
                $platform = is_array($item) ? ($item['platform'] ?? '') : (is_string($item) ? $item : '');
                $url = is_array($item) ? ($item['url'] ?? '#') : '#';

                $key = strtolower(trim($platform));
                $icon = $iconMap[$key] ?? $defaultIcon;
              @endphp

              @if($platform && $url)
                <a href="{{ $url }}" class="btn btn-outline-light btn-sm"
                  target="_blank" rel="noopener"
                  aria-label="{{ ucfirst($platform) }}">
                  <i class="bi bi-{{ $icon }}"></i>
                </a>
              @endif
            @endforeach
          </div>
        </div>


      <div class="border-top border-secondary mt-4 py-3 small text-center">
        © {{ date('Y') }} {{ $s->store_name ?? __('messages.Store') }}. {{ __('messages.AllRightsReserved') }}
      </div>
    </div>
  </footer>

  {{-- Mini Cart Offcanvas (pure LocalStorage, no routes) --}}
  <div class="offcanvas offcanvas-end" tabindex="-1" id="miniCart" aria-labelledby="miniCartLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="miniCartLabel">{{ __('messages.YourCart') }}</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('messages.Close') }}"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
      <div id="mc-empty" class="text-center text-muted my-4 d-none">
        <div class="display-6">🛒</div>
        <div class="mt-2">{{ __('messages.YourCartEmpty') }}</div>
      </div>

      <div id="mc-list" class="list-group list-group-flush flex-grow-1 overflow-auto"></div>

      <div class="border-top pt-3 mt-3">
        <div class="d-flex justify-content-between small text-muted">
          <span>{{ __('messages.Subtotal') }}</span>
          <strong id="mc-subtotal">$0.00</strong>
        </div>
        <div class="d-flex justify-content-between h6 mt-2">
          <span>{{ __('messages.GrandTotal') }}</span>
          <strong id="mc-grand">$0.00</strong>
        </div>

        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-outline-danger w-50" id="mc-clear">{{ __('messages.Clear') }}</button>
          <button class="btn btn-primary w-50" id="mc-checkout">{{ __('messages.Checkout') }}</button>
        </div>
      </div>
    </div>
  </div>

  {{-- Auth Modal (login or register before checkout) --}}
  <div class="modal fade" id="authModal" tabindex="-1" aria-labelledby="authModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4">
        <div class="modal-header">
          <h5 class="modal-title" id="authModalLabel">{{ __('messages.SignInToContinue') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('messages.Close') }}"></button>
        </div>

        <div class="modal-body">
          <ul class="nav nav-pills justify-content-center mb-3" id="authTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#tab-login" type="button" role="tab">
                <i class="bi bi-box-arrow-in-right me-1"></i>{{ __('messages.Login') }}
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#tab-register" type="button" role="tab">
                <i class="bi bi-person-plus me-1"></i>{{ __('messages.Register') }}
              </button>
            </li>
          </ul>

          <div class="tab-content">
            {{-- Login --}}
            <div class="tab-pane fade show active" id="tab-login" role="tabpanel" aria-labelledby="login-tab">
              <form method="POST" action="{{ route('store.login') }}">
                @csrf
                <input type="hidden" name="redirect" value="{{ route('checkout') }}">
                <div class="mb-3">
                  <label class="form-label">{{ __('messages.Email') }}</label>
                  <input type="email" name="email" class="form-control" required autocomplete="email">
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ __('messages.Password') }}</label>
                  <input type="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="d-flex align-items-center justify-content-between">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember_me">
                    <label class="form-check-label" for="remember_me">{{ __('messages.RememberMe') }}</label>
                  </div>
                </div>
                <div class="mt-3 d-grid">
                  <button type="submit" class="btn btn-primary">{{ __('messages.SignIn') }}</button>
                </div>
              </form>
            </div>

            {{-- Register --}}
            <div class="tab-pane fade" id="tab-register" role="tabpanel" aria-labelledby="register-tab">
              <form method="POST" action="{{ route('store.register') }}">
                @csrf
                <input type="hidden" name="redirect" value="{{ route('checkout') }}">
                <div class="mb-3">
                  <label class="form-label">{{ __('messages.Name') }}</label>
                  <input type="text" name="name" class="form-control" required autocomplete="name">
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ __('messages.Email') }}</label>
                  <input type="email" name="email" class="form-control" required autocomplete="email">
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ __('messages.Password') }}</label>
                  <input type="password" name="password" class="form-control" required autocomplete="new-password">
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ __('messages.ConfirmPassword') }}</label>
                  <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                </div>
                <div class="mt-3 d-grid">
                  <button type="submit" class="btn btn-success">{{ __('messages.CreateAccount') }}</button>
                </div>
              </form>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <style>
    .mc-thumb{ width:54px; height:54px; object-fit:cover; border-radius:.5rem; }
    .mc-item .btn { padding: .15rem .4rem; }

    #page-loader { transition: opacity .3s ease; }
    #page-loader.hidden { opacity: 0; pointer-events: none; }
  </style>

  {{-- Local script (no CDN) --}}
  <script src="{{ $jsBootstrap }}"></script>

  <script>
    window.addEventListener("load", function() {
      const loader = document.getElementById("page-loader");
      if (loader) {
        loader.classList.add("hidden");
        setTimeout(() => loader.style.display = "none", 300);
      }
    });
  </script>

  {{-- LocalStorage cart + offcanvas renderer --}}
  <script>
  // Static fallback image to avoid Blade-in-template-string quoting issues
  const NOIMG = "{{ asset('images/products/no-image.png') }}";

  /* 1) Minimal LocalStorage cart */
  (function(){
    if (window.CartLS) return;
    const KEY = 'shop.cart.v1';
    var curMeta = document.querySelector('meta[name="currency"]');
    var cur = (curMeta && curMeta.content) ? curMeta.content : '$';

    function load(){
      try { var c = JSON.parse(localStorage.getItem(KEY)||'{}'); if (Array.isArray(c.items)) return calc(c); } catch(e){}
      return calc({items:[], currency: cur});
    }
    function save(c){ localStorage.setItem(KEY, JSON.stringify(c)); window.dispatchEvent(new CustomEvent('cart:changed',{detail:c})); }
    function calc(c){ c.subtotal = c.items.reduce(function(a,i){return a + (Number(i.price)||0)*(Number(i.qty)||0)}, 0);
                      c.grand = c.subtotal; return c; }
    function idx(c,id){ return c.items.findIndex(function(i){return String(i.id)===String(id)}); }

    window.CartLS = {
      get: function(){ return load(); },
      add: function(item, qty){
        qty = qty == null ? 1 : qty;
        var c = load(); var i = idx(c, item.id);
        if (i>-1) c.items[i].qty += qty;
        else c.items.push({ id:String(item.id), name:item.name||'', price:Number(item.price)||0,
                            qty:Math.max(1,Number(qty)||1), image:item.image||'', slug:item.slug||'',
                            currency:item.currency||c.currency });
        save(calc(c)); window.dispatchEvent(new CustomEvent('cart:add-item')); return c;
      },
      setQty: function(id, q){ var c=load(); var i=idx(c,id); if(i>-1){ c.items[i].qty=Math.max(1,Number(q)||1); save(calc(c)); } return c; },
      remove: function(id){ var c=load(); c.items=c.items.filter(function(i){return String(i.id)!==String(id)}); save(calc(c)); return c; },
      clear: function(){ var base=load(); var c={items:[], currency:base.currency, subtotal:0, grand:0}; save(c); return c; }
    };
  })();

  /* 2) Badge updater + offcanvas renderer */
  (function(){
    var badge = document.getElementById('cart-badge');
    function money(v, c){ return (c||'$') + (Number(v||0).toFixed(2)); }
    function updateBadge(){
      if(!badge) return;
      var c = CartLS.get();
      var count = c.items.reduce(function(a,i){return a + (i.qty||0)}, 0);
      badge.textContent = count;
    }
    updateBadge();
    window.addEventListener('cart:changed', updateBadge);
    window.addEventListener('cart:add-item', updateBadge);

    // Mini-cart UI
    var oc = document.getElementById('miniCart');
    var list = document.getElementById('mc-list');
    var empty = document.getElementById('mc-empty');
    var subEl = document.getElementById('mc-subtotal');
    var grandEl = document.getElementById('mc-grand');
    var clearBtn = document.getElementById('mc-clear');
    var checkoutBtn = document.getElementById('mc-checkout');

    function render(){
      var c = CartLS.get();
      if (!c.items.length){
        empty.classList.remove('d-none');
        list.innerHTML = '';
        subEl.textContent = money(0,c.currency);
        grandEl.textContent= money(0,c.currency);
        return;
      }
      empty.classList.add('d-none');
      list.innerHTML = '';
      c.items.forEach(function(it){
        var row = document.createElement('div');
        row.className = 'list-group-item mc-item';
        row.dataset.id = it.id;
        row.innerHTML = '\
          <div class="d-flex align-items-center gap-3">\
            <img class="mc-thumb" src="'+(it.image || NOIMG)+'" alt="'+(it.name||'')+'">\
            <div class="flex-grow-1">\
              <div class="fw-semibold text-truncate" title="'+(it.name||'')+'">'+(it.name||'')+'</div>\
              <div class="small text-muted">'+money(it.price, it.currency)+'</div>\
              <div class="d-flex align-items-center gap-1 mt-1">\
                <button class="btn btn-outline-secondary btn-sm js-dec" type="button">−</button>\
                <input type="number" class="form-control form-control-sm text-center js-qty" value="'+(it.qty||1)+'" min="1" style="width:70px">\
                <button class="btn btn-outline-secondary btn-sm js-inc" type="button">+</button>\
                <div class="ms-auto fw-semibold js-line">'+money((it.price||0)*(it.qty||1), it.currency)+'</div>\
                <button class="btn btn-outline-danger btn-sm ms-2 js-remove" type="button"><i class="bi bi-trash"></i></button>\
              </div>\
            </div>\
          </div>';
        list.appendChild(row);
      });
      subEl.textContent  = money(c.subtotal, c.currency);
      grandEl.textContent= money(c.grand, c.currency);
    }

    if (oc) {
      oc.addEventListener('shown.bs.offcanvas', render);
      window.addEventListener('cart:changed', render);

      list.addEventListener('click', function(e){
        var row = e.target.closest('.mc-item'); if(!row) return;
        var id = row.dataset.id;
        if (e.target.closest('.js-dec')) {
          var inp = row.querySelector('.js-qty');
          inp.value = Math.max(1, parseInt(inp.value||'1',10) - 1);
          CartLS.setQty(id, inp.value); return;
        }
        if (e.target.closest('.js-inc')) {
          var inp2 = row.querySelector('.js-qty');
          inp2.value = Math.max(1, parseInt(inp2.value||'1',10) + 1);
          CartLS.setQty(id, inp2.value); return;
        }
        if (e.target.closest('.js-remove')) {
          CartLS.remove(id); return;
        }
      });

      list.addEventListener('change', function(e){
        var inp = e.target.closest('.js-qty'); if(!inp) return;
        var row = inp.closest('.mc-item');
        CartLS.setQty(row.dataset.id, Math.max(1, parseInt(inp.value||'1',10)));
      });

      if (clearBtn) clearBtn.addEventListener('click', function(){ CartLS.clear(); });

      var CHECKOUT_URL = '{{ route('checkout') }}';
      if (checkoutBtn) checkoutBtn.addEventListener('click', function(e){
        e.preventDefault();
        if (window.__LOGGED_IN__) { window.location.href = CHECKOUT_URL; return; }
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('authModal'));
        modal.show();
      });
    }
  })();
  </script>

  <script>
  // Global wireup for .js-add-to-cart buttons
  if (!window.__CART_WIREUP__) {
    window.__CART_WIREUP__ = true;

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.js-add-to-cart');
      if (!btn) return;

      if (btn.dataset.lock === '1') return;
      btn.dataset.lock = '1';
      setTimeout(function(){ btn.dataset.lock = ''; }, 400);

      var card = btn.closest('.product-card');
      var status = card ? card.querySelector('.js-add-status') : null;

      var curMeta = document.querySelector('meta[name="currency"]');
      var currency = (curMeta && curMeta.content) ? curMeta.content : '$';

      var item = {
        id: btn.dataset.id,
        name: btn.dataset.name || (card && card.querySelector('.product-title') ? card.querySelector('.product-title').textContent.trim() : ''),
        price: parseFloat(btn.dataset.price || '0'),
        image: btn.dataset.image || (card && card.querySelector('img') ? card.querySelector('img').src : ''),
        slug: btn.dataset.slug || '',
        currency: btn.dataset.currency || currency,
      };

      var qty = parseInt(btn.dataset.qty || '1', 10) || 1;
      CartLS.add(item, qty);

      var original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>{{ __("messages.Added") }}';
      if (status) status.textContent = '{{ __("messages.AddedToCart") }}';
      setTimeout(function () {
        btn.disabled = false;
        btn.innerHTML = original;
        if (status) status.textContent = '';
      }, 800);
    });
  }
  </script>

  {!! $s->custom_js ?? '' !!}
</body>
</html>


