@extends('layouts.store')

@section('content')
@php
  /** @var \App\Models\EcommerceClient|null $me */
  $me = Auth::guard('store')->user();
  $updateUrl = url('/online_store/account');
  $ordersUrl = url('/online_store/account/orders');
@endphp

<section class="border-bottom bg-gradient-subtle">
  <div class="container py-4 d-flex align-items-center justify-content-between">
    <div>
      <h1 class="h4 mb-1">{{ __('messages.MyAccount') }}</h1>
      <div class="text-muted small">{{ __('messages.ManageProfileAndOrders') }}</div>
    </div>
    <a href="{{ $ordersUrl }}" class="btn btn-outline-primary">
      <i class="bi bi-receipt me-1"></i> {{ __('messages.MyOrders') }}
    </a>
  </div>
</section>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-8">

      @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
      @endif
      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
          </ul>
        </div>
      @endif

      {{-- Profile --}}
      <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
          <h5 class="mb-3">{{ __('messages.Profile') }}</h5>

          @if(!$me)
            <div class="alert alert-warning mb-0">
              {{ __('messages.MustBeSignedIn') }}
            </div>
          @else
            <form method="POST" action="{{ $updateUrl }}">
              @csrf
              @method('PUT')

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">{{ __('messages.Username') }}</label>
                  <input name="username" type="text" class="form-control" value="{{ old('username', $me->username) }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">{{ __('messages.Email') }}</label>
                  <input name="email" type="email" class="form-control" value="{{ old('email', $me->email) }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">{{ __('messages.NewPassword') }}</label>
                  <input name="password" type="password" class="form-control" autocomplete="new-password" placeholder="••••••••">
                </div>
                <div class="col-md-6">
                  <label class="form-label">{{ __('messages.ConfirmPassword') }}</label>
                  <input name="password_confirmation" type="password" class="form-control" autocomplete="new-password" placeholder="••••••••">
                </div>
              </div>

              <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">
                  <i class="bi bi-save me-1"></i> {{ __('messages.SaveChanges') }}
                </button>
                <a href="{{ $ordersUrl }}" class="btn btn-outline-secondary">
                  <i class="bi bi-receipt me-1"></i> {{ __('messages.ViewOrders') }}
                </a>
              </div>
            </form>
          @endif
        </div>
      </div>

      {{-- Account meta (read-only) --}}
      @if($me)
        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-body p-4">
            <h6 class="mb-3">{{ __('messages.AccountDetails') }}</h6>
            <div class="row small">
              <div class="col-md-6 mb-2">
                <div class="text-muted">{{ __('messages.ClientID') }}</div>
                <div class="fw-semibold">{{ $me->client_id ?? '—' }}</div>
              </div>
              <div class="col-md-6 mb-2">
                <div class="text-muted">{{ __('messages.Status') }}</div>
                <div class="fw-semibold">
                  @if((int)$me->status === 1)
                    <span class="badge text-bg-success">{{ __('messages.Active') }}</span>
                  @else
                    <span class="badge text-bg-secondary">{{ __('messages.Inactive') }}</span>
                  @endif
                </div>
              </div>
              <div class="col-md-6 mb-2">
                <div class="text-muted">{{ __('messages.CreatedAt') }}</div>
                <div class="fw-semibold">{{ optional($me->created_at)->toDateTimeString() ?? '—' }}</div>
              </div>
              <div class="col-md-6 mb-2">
                <div class="text-muted">{{ __('messages.UpdatedAt') }}</div>
                <div class="fw-semibold">{{ optional($me->updated_at)->toDateTimeString() ?? '—' }}</div>
              </div>
            </div>
          </div>
        </div>
      @endif

    </div>
  </div>
</div>
@endsection
