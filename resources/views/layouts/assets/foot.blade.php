<!-- build:js assets/vendor/js/core.js -->
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
<script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
<script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
<script src="{{ asset('js/sweetalert2@11.js') }}"></script>
<script src="{{ asset('js/select2.min.js') }}"></script>
<script src="{{ asset('js/Kirana.js') }}"></script>
<script src="{{ asset('js/moment-with-locales.min.js') }}"></script>
<!-- endbuild -->
<!-- Vendors JS -->
<!-- Main JS -->
<script src="{{ asset('assets/js/main.js') }}"></script>
<!-- Page JS -->
@stack('js')
<!-- Place this tag in your head or just before your close body tag. -->
<script async defer src="{{ asset('assets/js/buttons.js') }}"></script>
