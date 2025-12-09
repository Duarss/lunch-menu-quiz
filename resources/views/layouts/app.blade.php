<!DOCTYPE html>

<html lang="{{ config('app.locale') }}" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="{{ asset('assets') }}" data-template="vertical-menu-template-free">

<head>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  @include('layouts.assets.head')
</head>

<body>
<!-- Layout wrapper -->
{{-- <div class="layout-wrapper layout-content-navbar layout-without-menu"> --}}
<div class="layout-wrapper layout-content-navbar @isset($withoutSidebar) layout-without-menu @endisset">
  <div class="layout-container">
    <!-- Menu -->
    @empty($withoutSidebar)
      @include('layouts.sidebar')
    @endempty
    <!-- / Menu -->
    <!-- Layout container -->
    <div class="layout-page">
      <!-- Navbar -->
      @include('layouts.navbar')
      <!-- / Navbar -->
      <!-- Content wrapper -->
      <div class="content-wrapper">
        <!-- Content -->
        <div class="container-xxxl flex-grow-1 mx-4">
          @yield('content')
        </div>
        <!-- / Content -->
        <!-- Footer -->
        @yield('modal')
        @include('layouts.footer')
        <!-- / Footer -->
        <div class="content-backdrop fade"></div>
      </div>
      <!-- Content wrapper -->
    </div>
    <!-- / Layout page -->
  </div>
</div>
<!-- / Layout wrapper -->
<!-- Core JS -->
@include('layouts.assets.foot')
<script>
  let authUser = {}
  @auth
    authUser = @json(auth()->user());
  @endauth

  setLocal('url-previous-'+authUser.username,'{{ url()->previous() }}')
  $('.btn-back').attr('href', getLocal('url-previous-'+authUser.username))

  $(function(){
    $(document).ready(function () {
        setTimeout(function() {
            $('#status-alert').fadeOut('slow')
            $('#error-alert').fadeOut('slow')
            $("#api-token-alert").fadeOut('slow')
        }, 2500)
    })
  })
</script>

@stack('scripts')
</body>
</html>
