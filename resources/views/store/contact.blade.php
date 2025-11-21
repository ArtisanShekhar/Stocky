@extends('layouts.store')

@section('content')
@php
  $title   = __('messages.ContactUs');
  $email   = $s->contact_email ?? '';
  $phone   = $s->contact_phone ?? '';
  $address = $s->contact_address ?? '';
  use App\Models\StoreSetting;
  $s = $s ?? StoreSetting::first();
@endphp

<section class="py-4 border-bottom" style="background:linear-gradient(90deg,var(--brand, #6c5ce7),var(--brand-2,#00c2ff));">
  <div class="container text-white">
    <div class="d-flex align-items-center justify-content-between flex-wrap">
      <div>
        <h1 class="h3 mb-1">{{ $title }}</h1>
        <div class="opacity-75">{{ __('messages.WeLoveToHearFromYou') }}</div>
      </div>
      <nav aria-label="breadcrumb" class="mt-2 mt-md-0">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item">
            <a class="link-light text-decoration-none" href="{{ route('store.index') }}">{{ __('messages.Home') }}</a>
          </li>
          <li class="breadcrumb-item active text-white" aria-current="page">{{ $title }}</li>
        </ol>
      </nav>
    </div>
  </div>
</section>

<section class="py-5">
  <div class="container">
    <div class="row g-4">
      {{-- Contact info --}}
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title mb-3">{{ __('messages.ContactInformation') }}</h5>

            <div class="d-flex align-items-start mb-3">
              <span class="me-3 btn btn-light btn-sm rounded-circle"><i class="bi bi-envelope"></i></span>
              <div>
                <div class="small text-muted">{{ __('messages.Email') }}</div>
                @if($email)
                  <a href="mailto:{{ $email }}">{{ $email }}</a>
                @else
                  <span class="text-muted">{{ __('messages.NotProvided') }}</span>
                @endif
              </div>
            </div>

            <div class="d-flex align-items-start mb-3">
              <span class="me-3 btn btn-light btn-sm rounded-circle"><i class="bi bi-telephone"></i></span>
              <div>
                <div class="small text-muted">{{ __('messages.Phone') }}</div>
                @if($phone)
                  <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a>
                @else
                  <span class="text-muted">{{ __('messages.NotProvided') }}</span>
                @endif
              </div>
            </div>

            <div class="d-flex align-items-start">
              <span class="me-3 btn btn-light btn-sm rounded-circle"><i class="bi bi-geo-alt"></i></span>
              <div>
                <div class="small text-muted">{{ __('messages.Address') }}</div>
                @if($address)
                  <div>{{ $address }}</div>
                @else
                  <span class="text-muted">{{ __('messages.NotProvided') }}</span>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- AJAX form (no refresh) --}}
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title mb-3">{{ __('messages.SendUsAMessage') }}</h5>

            <form id="contactForm" method="POST" action="{{ route('store.contact.send') }}" class="row g-3" novalidate>
              @csrf

              <div class="col-md-6">
                <label class="form-label">{{ __('messages.YourName') }} *</label>
                <input type="text" name="name" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">{{ __('messages.EmailAddress') }} *</label>
                <input type="email" name="email" class="form-control" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">{{ __('messages.PhoneOptional') }}</label>
                <input type="text" name="phone" class="form-control">
              </div>

              <div class="col-md-6">
                <label class="form-label">{{ __('messages.Subject') }}</label>
                <input type="text" name="subject" class="form-control" placeholder="{{ __('messages.HowCanWeHelp') }}">
              </div>

              <div class="col-12">
                <label class="form-label">{{ __('messages.Message') }} *</label>
                <textarea name="message" rows="5" class="form-control" required></textarea>
              </div>

              {{-- Honeypot --}}
              <div style="position:absolute; left:-10000px; top:auto;">
                <input type="text" name="company" tabindex="-1" autocomplete="off">
              </div>

              <div class="col-12 d-flex align-items-center justify-content-between">
                <small class="text-muted">{{ __('messages.ReplyWithinOneBusinessDay') }}</small>
                <button id="contactSubmit" type="submit" class="btn btn-primary">
                  <i class="bi bi-send"></i> {{ __('messages.SendMessage') }}
                </button>
              </div>

              {{-- Inline alert (ARIA live) --}}
              <div class="col-12">
                <div id="contactAlert" class="alert d-none mt-2" role="alert" aria-live="polite"></div>
              </div>
            </form>
          </div>
        </div>

        {{-- Map --}}
        @if($address)
          <div class="card border-0 shadow-sm mt-3">
            <div class="card-body p-0">
              <div class="ratio ratio-16x9">
                <iframe
                  src="https://www.google.com/maps?q={{ urlencode($address) }}&output=embed"
                  loading="lazy"
                  referrerpolicy="no-referrer-when-downgrade"
                  allowfullscreen>
                </iframe>
              </div>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>
</section>

{{-- No-refresh JS --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('contactForm');
  var btn  = document.getElementById('contactSubmit');
  var box  = document.getElementById('contactAlert');

  function clearValidation() {
    var invalids = form.querySelectorAll('.is-invalid');
    for (var i = 0; i < invalids.length; i++) invalids[i].classList.remove('is-invalid');
    var dyn = form.querySelectorAll('.invalid-feedback.js-dyn');
    for (var j = 0; j < dyn.length; j++) dyn[j].parentNode.removeChild(dyn[j]);
  }

  function showAlert(type, html) {
    box.className = 'alert mt-2 alert-' + type;
    box.innerHTML = html;
    box.classList.remove('d-none');
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearValidation();
    box.classList.add('d-none');

    var originalBtnHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __("messages.Sending") }}';

    var fd = new FormData(form);

    fetch(form.action, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
      },
      body: fd
    })
    .then(function (resp) {
      var ct = (resp.headers.get('content-type') || '').toLowerCase();
      if (ct.indexOf('application/json') !== -1) {
        return resp.json().then(function (json) { return { ok: resp.ok, status: resp.status, data: json }; });
      }
      return resp.text().then(function (text) { return { ok: resp.ok, status: resp.status, data: { message: text } }; });
    })
    .then(function (res) {
      if (res.ok) {
        showAlert('success', (res.data && res.data.message) ? res.data.message : '{{ __("messages.ContactSuccess") }}');
        form.reset();
        return;
      }

      if (res.status === 422 && res.data && res.data.errors) {
        var errors = res.data.errors || {};
        var listHtml = '<strong>{{ __("messages.FixFollowingAndTryAgain") }}</strong><ul class="mb-0">';
        for (var field in errors) {
          if (!errors.hasOwnProperty(field)) continue;
          var msgs = errors[field];
          for (var k = 0; k < msgs.length; k++) listHtml += '<li>' + msgs[k] + '</li>';
          var input = form.querySelector('[name="' + field + '"]');
          if (input) {
            input.classList.add('is-invalid');
            var div = document.createElement('div');
            div.className = 'invalid-feedback js-dyn';
            div.textContent = msgs[0];
            if (input.parentNode) input.parentNode.appendChild(div);
          }
        }
        listHtml += '</ul>';
        showAlert('danger', listHtml);
        return;
      }

      var msg = (res.data && (res.data.message || res.data.error)) || '{{ __("messages.SomethingWentWrong") }}';
      showAlert('danger', msg);
    })
    .catch(function () {
      showAlert('danger', '{{ __("messages.NetworkErrorTryAgain") }}');
    })
    .finally(function () {
      btn.disabled = false;
      btn.innerHTML = originalBtnHtml;
    });
  });
});
</script>
@endsection
