{{-- resources/views/store/auth/register.blade.php --}}
@extends('layouts.store')

@section('content')
@php
  $redirect = $redirect ?? request('redirect', route('checkout'));
@endphp

<section class="border-bottom bg-gradient-subtle">
  <div class="container py-4">
    <h1 class="h4 mb-0">{{ __('messages.CreateAccount') }}</h1>
  </div>
</section>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $e)
              <li>{{ $e }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">
          <div class="text-center mb-3">
            <div class="display-6 mb-2">🧾</div>
            <h5 class="mb-0">{{ __('messages.JoinUs') }}</h5>
            <div class="text-muted small">{{ __('messages.CreateStoreAccountFaster') }}</div>
          </div>

          <form method="POST" action="{{ route('store.register') }}" novalidate>
            @csrf
            <input type="hidden" name="redirect" value="{{ $redirect }}"/>

            <div class="mb-3">
              <label class="form-label">{{ __('messages.FullName') }}</label>
              <input
                type="text"
                name="name"
                value="{{ old('name') }}"
                class="form-control @error('name') is-invalid @enderror"
                autocomplete="name"
                required
              >
              @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('messages.Email') }}</label>
              <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror"
                autocomplete="email"
                required
              >
              @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('messages.Password') }}</label>
              <div class="input-group">
                <input
                  type="password"
                  name="password"
                  id="regPass"
                  class="form-control @error('password') is-invalid @enderror"
                  autocomplete="new-password"
                  required
                >
                <button class="btn btn-outline-secondary" type="button" onclick="togglePass('regPass', this)">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <small class="text-muted">{{ __('messages.Minimum6Chars') }}</small>
              @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
              <label class="form-label">{{ __('messages.ConfirmPassword') }}</label>
              <input
                type="password"
                name="password_confirmation"
                class="form-control"
                autocomplete="new-password"
                required
              >
            </div>

            <button type="submit" class="btn btn-success w-100 btn-lg">
              <i class="bi bi-person-plus me-1"></i> {{ __('messages.CreateAccount') }}
            </button>
          </form>

          <div class="text-center mt-3 small">
            {{ __('messages.AlreadyHaveAccountQ') }}
            <a href="{{ route('store.login.show', ['redirect' => $redirect]) }}">{{ __('messages.SignIn') }}</a>
          </div>
        </div>
      </div>

      <div class="text-center mt-3">
        <a class="btn btn-link" href="{{ route('store.shop') }}">
          <i class="bi bi-arrow-left"></i> {{ __('messages.BackToShop') }}
        </a>
      </div>
    </div>
  </div>
</div>

<script>
  function togglePass(id, btn){
    const el = document.getElementById(id);
    if (!el) return;
    const isText = el.type === 'text';
    el.type = isText ? 'password' : 'text';
    btn.innerHTML = isText ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
  }
</script>
@endsection
