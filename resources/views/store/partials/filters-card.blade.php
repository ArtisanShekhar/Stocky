@php
  // Defaults expected from include: $q, $cat, $collection, $min, $max, $sort, $categories, $collections
  $isOffcanvas = $isOffcanvas ?? false;
@endphp

<form method="get" action="{{ route('store.shop') }}" class="filters-sticky">
  {{-- When rendering from offcanvas, preserve current query --}}
  @foreach(request()->except(['page']) as $k => $v)
    @if(!in_array($k, ['q','category','collection','min','max','sort']))
      @if(is_array($v))
        @foreach($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach
      @else
        <input type="hidden" name="{{ $k }}" value="{{ $v }}">
      @endif
    @endif
  @endforeach

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="mb-0">{{ __('messages.Filters') }}</h6>
        <a href="{{ route('store.shop') }}" class="small text-muted">{{ __('messages.Reset') }}</a>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('messages.Search') }}</label>
        <input
          type="text"
          name="q"
          value="{{ $q }}"
          class="form-control"
          placeholder="{{ __('messages.SearchProducts') }}"
        >
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('messages.Category') }}</label>
        <select name="category" class="form-select">
          <option value="">{{ __('messages.AllCategories') }}</option>
          @foreach($categories as $c)
            <option value="{{ $c->id }}" @selected((string)$cat === (string)$c->id)>{{ $c->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('messages.Collection') }}</label>
        <select name="collection" class="form-select">
          <option value="">{{ __('messages.AllCollections') }}</option>
          @foreach($collections as $co)
            @php $isSelected = (string)$collection === (string)$co->slug || (string)$collection === (string)$co->id; @endphp
            <option value="{{ $co->slug }}" @selected($isSelected)>{{ $co->title ?: $co->slug }}</option>
          @endforeach
        </select>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="form-label">{{ __('messages.MinPrice') }}</label>
          <input type="number" step="0.01" min="0" name="min" value="{{ $min }}" class="form-control" placeholder="0">
        </div>
        <div class="col-6">
          <label class="form-label">{{ __('messages.MaxPrice') }}</label>
          <input type="number" step="0.01" min="0" name="max" value="{{ $max }}" class="form-control" placeholder="9999">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('messages.Sort') }}</label>
        <select name="sort" class="form-select">
          <option value="latest" @selected(($sort ?? 'latest') === 'latest')>{{ __('messages.Latest') }}</option>
          <option value="price_asc" @selected($sort === 'price_asc')>{{ __('messages.PriceUp') }}</option>
          <option value="price_desc" @selected($sort === 'price_desc')>{{ __('messages.PriceDown') }}</option>
        </select>
      </div>

      <button class="btn btn-primary w-100">
        <i class="bi bi-funnel"></i>
        {{ $isOffcanvas ? __('messages.ApplyAndClose') : __('messages.ApplyFilters') }}
      </button>
    </div>
  </div>
</form>
