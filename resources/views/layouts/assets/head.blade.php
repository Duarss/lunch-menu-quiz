<meta charset="utf-8" />
<meta
  name="viewport"
  content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
/>

<title>{{ config('app.surname') }} @if(isset($title)) | {{ $title }} @endif </title>

<meta name="description" content="" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="asset-url" content="{{ asset('') }}">
@auth
  <meta name="user-name" content="{{ auth()->user()->username }}">
@endauth
<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="{{ asset('tktw/favicon.png') }}" />

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link
  href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
  rel="stylesheet"
/>

<!-- Icons. Uncomment required icon fonts -->
<link rel="stylesheet" href="{{ asset('assets/vendor/fonts/boxicons.css') }}" />

<!-- Core CSS -->
<link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" class="template-customizer-core-css" />
<link rel="stylesheet" href="{{ asset('assets/vendor/css/theme-default.css') }}" class="template-customizer-theme-css" />
{{-- <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" /> --}}
<link rel="stylesheet" href="{{ asset('css/my.css') }}">
<link rel="stylesheet" href="{{ asset('css/tktw.css') }}">

<!-- Vendors CSS -->
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />

<!-- Page CSS -->
@yield('css')
@stack('css')

<!-- Background -->
<style>
  aside{
    background-image: url('{{ asset('assets/bg-view2.jpg') }}');
    background-repeat: no-repeat;
    /* background-size: cover; */
  }
  body{
    background-image: url('{{ asset('assets/bg-view2.jpg') }}');
    background-repeat: no-repeat;
    background-size: cover;
    background-attachment: fixed;
  }
</style>
<!-- Helpers -->
<script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>

<!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
<!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
<script src="{{ asset('assets/js/config.js') }}"></script>
