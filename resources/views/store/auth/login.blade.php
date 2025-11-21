{{-- resources/views/store/auth/login.blade.php --}}
@extends('layouts.store')

@section('content')
@php
  // Fallback if controller didn't send $redirect
  $redirect = $redirect ?? route('checkout');
@endphp

<section class="border-bottom bg-gradient-subtle">
  <div class="container py-4">
    <h1 class="h4 mb-0">{{ __('messages.SignIn') }}</h1>
  </div>
</section>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
      {{-- Already logged in (store guard) --}}
      @if(Auth::guard('store')->check())
        <div class="alert alert-success d-flex align-items-center" role="alert">
          <i class="bi bi-check-circle me-2"></i>
          <div>{{ __('messages.AlreadySignedIn') }}</div>
        </div>
      @endif

      {{-- Errors --}}
      @if ($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach ($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
          <div class="text-center mb-3">
            <div class="display-6">🛍️</div>
            <h2 class="h5 mt-2 mb-0">{{ __('messages.WelcomeBack') }}</h2>
            <div class="text-muted">{{ __('messages.SignInContinueCheckout') }}</div>
          </div>

          <form method="POST" action="{{ route('store.login') }}" novalidate>
            @csrf
            <input type="hidden" name="redirect" value="{{ $redirect }}">

            <div class="mb-3">
              <label class="form-label">{{ __('messages.EmailAddress') }}</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input
                  type="email"
                  name="email"
                  class="form-control"
                  value="{{ old('email') }}"
                  required
                  autocomplete="email"
                  placeholder="{{ __('messages.EmailPlaceholder') }}"
                >
              </div>
            </div>

            <div class="mb-3">
             
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input
                  type="password"
                  name="password"
                  class="form-control"
                  required
                  autocomplete="current-password"
                  placeholder="••••••••"
                >
              </div>
            </div>

            <div class="mb-3 form-check">
              <input class="form-check-input" type="checkbox" name="remember" id="remember_me" value="1">
              <label class="form-check-label" for="remember_me">{{ __('messages.RememberMe') }}</label>
            </div>

            <button class="btn btn-primary w-100" type="submit">
              <i class="bi bi-box-arrow-in-right me-1"></i>{{ __('messages.SignIn') }}
            </button>
          </form>

          <div class="text-center mt-3 small">
            {{ __('messages.DontHaveAccountQ') }}
            <a href="{{ route('store.register.show', ['redirect' => $redirect]) }}">{{ __('messages.CreateOne') }}</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .bg-gradient-subtle{ background: linear-gradient(90deg, rgba(108,92,231,.05), rgba(0,194,255,.05)); }
</style>
@endsection
