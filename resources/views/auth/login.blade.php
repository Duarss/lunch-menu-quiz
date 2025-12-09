<!DOCTYPE html>

<!-- =========================================================
* Sneat - Bootstrap 5 HTML Admin Template - Pro | v1.0.0
==============================================================

* Product Page: https://themeselection.com/products/sneat-bootstrap-html-admin-template/
* Created by: ThemeSelection
* License: You must have a valid license purchased in order to legally use the theme for your project.
* Copyright ThemeSelection (https://themeselection.com)

=========================================================
 -->
<!-- beautify ignore:start -->
<html
  lang="{{ config('app.locale') }}"
  class="light-style customizer-hide"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="{{ asset('assets') }}"
  data-template="vertical-menu-template-free"
>
  <head>
    @include('layouts.assets.head',['title' => 'Login'])
    <!-- Page CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-auth.css') }}" />
  </head>
  <body>
    <!-- Content -->
    <div class="container-xxl">
      <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner">
          <!-- Register -->
          <div class="card">
            @if (cache('status'))
                <div class="alert alert-success" id="status-alert">
                    {{ cache('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" id="error-alert">
                    {{ $errors->first() }}
                </div>
            @endif
            <div class="card-body">
              <!-- Logo -->
              <div class="app-brand justify-content-center">
                <div>
                  <img src="{{ asset('tktw/logo-avian.png') }}" alt="Logo" width="85%">
                </div>
              </div>
              <!-- /Logo -->
              <h4 class="mb-2">Selamat datang! 👋</h4>
              <p class="mb-4">Silakan memasukan username dan password anda untuk masuk ke dalam {{ config('app.name') }} "{{ config('app.surname') }}"</p>

              <form id="formAuthentication" class="mb-3" action="{{ route('login') }}" method="POST">
                {{ csrf_field() }}
                <div class="mb-3">
                  <label for="username" class="form-label">Username</label>
                  <input type="text" class="form-control" id="username" name="username" placeholder="username.." value="{{ old('username') }}" autofocus/>
                  <input type="hidden" id="access-token" name="accessToken">
                  @if ($errors->has('username'))
                      <span class="help-block text-danger">
                          <strong>{{ $errors->first('username') }}</strong>
                      </span>
                  @endif
                </div>
                <div class="mb-3 form-password-toggle">
                  <div class="d-flex justify-content-between">
                    <label class="form-label" for="password">Password</label>
                  </div>
                  <div class="input-group input-group-merge">
                    <input type="password" id="password" class="form-control" name="password" placeholder="password.." aria-describedby="password"/>
                    <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                  </div>
                  @if ($errors->has('password'))
                      <span class="help-block text-danger">
                          <strong>{{ $errors->first('password') }}</strong>
                      </span>
                  @endif
                </div>
                {{-- <div class="mb-3">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember-me" {{ old('remember') ? 'checked' : '' }}/>
                    <label class="form-check-label" for="remember-me"> Ingat saya </label>
                  </div>
                </div> --}}
                <div class="mb-3">
                  <button class="btn btn-primary d-grid w-100" type="submit">Login</button>
                </div>
              </form>

              <p class="text-center">
                <span>Lupa kata sandi?. Reset <a href="https://tirtaapps.tirtakencana.com/link-tirta/forgot-password">di sini</a></span>
              </p>
            </div>
          </div>
          <!-- /Register -->
        </div>
      </div>
    </div>
    <!-- / Content -->
    @include('layouts.assets.foot')
    {{-- <script src="{{ asset('https://tirtaapps.tirtakencana.com/link-tirta/js/autologin.js') }}"></script>
    <script>
      $(document).ready(function(){
        autologin(window.location.search,function(username,token){
          if(username && token){
            $('#username').val(username)
            $('#password').val(token)
            $('#access-token').val(token)
            document.getElementById('formAuthentication').submit()
          }
        })
      })
    </script> --}}
  </body>
</html>

<script>
  $(function(){
    $(document).ready(function () {
        setTimeout(function() {
            $('#status-alert').fadeOut('slow')
            $('#error-alert').fadeOut('slow')
            $("#api-token-alert").fadeOut('slow')
        }, 2500)
    })

    $('#formAuthentication').on('submit', function(e) {
        if ($(this).data('submitted')) {
            e.preventDefault()
            return false
        }
        $(this).data('submitted', true)
    })
  })
</script>