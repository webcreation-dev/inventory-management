<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap" rel="stylesheet" />
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/fonts/iconify-icons.css') }}" />
<!-- Core CSS -->
<!-- build:css assets/vendor/css/theme.css  -->
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/node-waves/node-waves.css') }}" />
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/pickr/pickr-themes.css') }}" />
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/css/core.css') }}" />
<link rel="stylesheet" href="{{ asset('public/backend_assets/css/demo.css') }}" />
<link rel="stylesheet" href="{{ asset('public/backend_assets/css/custom.css') }}" />
<link rel="stylesheet" href="{{ asset('public/backend_assets/css/table_custom.css') }}" />
<!-- Vendors CSS -->
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
<!-- endbuild -->
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}" />
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}" />
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/fonts/flag-icons.css') }}" />
<!-- Flat Picker -->
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/flatpickr/flatpickr.css') }}" />
<!-- Notification -->
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/notyf/notyf.css') }}" />
<!-- Select 2 -->
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/select2/select2.css') }}" />
<link rel="stylesheet" href="{{ asset('public/backend_assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
<!-- Helpers -->
<script src="{{ asset('public/backend_assets/vendor/js/helpers.js') }}?v={{ @filemtime(public_path('backend_assets/vendor/js/helpers.js')) ?: time() }}"></script>
<!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
<!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
<script src="{{ asset('public/backend_assets/vendor/js/template-customizer.js') }}"></script>
<!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
<script src="{{ asset('public/backend_assets/js/config.js') }}"></script>
<style>
  #layout-menu button.menu-link.menu-toggle {
    width: calc(100% - 1rem);
    border: 0;
    background: transparent;
    font: inherit;
    text-align: start;
    cursor: pointer;
  }
  #layout-menu button.menu-link.menu-toggle:focus-visible {
    outline: 2px solid var(--bs-primary);
    outline-offset: -2px;
  }
</style>
