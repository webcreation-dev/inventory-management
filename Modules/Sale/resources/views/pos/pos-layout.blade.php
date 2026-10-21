<!doctype html>
<html
  lang="en"
  class="layout-navbar-fixed layout-menu-fixed layout-compact"
  dir="ltr"
  data-skin="default"
  data-assets-path="/../public/backend_assets/"
  data-template="vertical-menu-template"
  data-bs-theme="light">
  <head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <title>{{ session('white_label.site_name') ?? '' }} | @yield('page-title')</title>
    <meta name="description" content="" />
    @include('backend.components.pwa_meta')
    <!-- Favicon -->
    <link rel="icon" type="{{ lpFaviconLogoType() }}" href="{!! lpFaviconLogoUrl(asset('public/uploads/whitelabel/' . (session('white_label.site_favicon') ?? 'initial-fav.ico'))) !!}" />
    <!-- Header CSS Part -->
    @include('backend.components.pos_header_css_part')
    @stack('page-css')
  </head>

  <body style="overflow: hidden; margin: 0; padding: 0; height: 100vh;">
    <!-- Include Global Usaged Data -->
    @include('backend.components.global_used_data')
    <!-- POS Layout - Full Screen without Sidebar -->
    <div style="height: 100vh; width: 100vw; overflow: hidden; position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
      <!-- Content -->
      @yield('page-content')
      <!-- / Content -->
    </div>

    <!-- POS Search Autocomplete Container -->
    <div id="pos-autocomplete"></div>

    <!-- POS  Modals -->
    @include('sale::pos.component.customer')
    @include('sale::pos.component.sales')
    @include('sale::pos.component.sale-returns')
    @include('sale::pos.component.item-info')
    @include('sale::pos.component.tax-breakdown')
    @include('sale::pos.component.payment')
    @include('sale::pos.component.hold')
    @include('sale::pos.component.calculator')
    @include('sale::pos.component.register-summary')
    <!-- POS Modals End -->

    <!-- Main JS Part -->
    @include('backend.components.pos_main_js_part')
    <!-- Main JS -->
    @stack('page-js')
    <script src="{{ asset('public/backend_assets/js/main.js') }}?v={{ @filemtime(public_path('backend_assets/js/main.js')) ?: time() }}"></script>
    <script src="{{ asset('public/backend_assets/js/custom_js/helper_main.js') }}?v={{ @filemtime(public_path('backend_assets/js/custom_js/helper_main.js')) ?: time() }}"></script>
    <script src="{{ asset('public/backend_assets/js/custom_js/register.js') }}?v={{ @filemtime(public_path('backend_assets/js/custom_js/register.js')) ?: time() }}"></script>
    <script src="{{ asset('public/pos_assets/js/pos_indexeddb.js') }}?v={{ @filemtime(public_path('pos_assets/js/pos_indexeddb.js')) ?: time() }}"></script>
    <script src="{{ asset('public/pos_assets/js/pos_invoice_offline.js') }}?v={{ @filemtime(public_path('pos_assets/js/pos_invoice_offline.js')) ?: time() }}"></script>
    <script src="{{ asset('public/pos_assets/js/pos_main.js') }}?v={{ @filemtime(public_path('pos_assets/js/pos_main.js')) ?: time() }}"></script>
    <script src="{{ asset('public/pos_assets/js/pos_cart.js') }}?v={{ @filemtime(public_path('pos_assets/js/pos_cart.js')) ?: time() }}"></script>
    <script src="{{ asset('public/pos_assets/js/pos_products.js') }}?v={{ @filemtime(public_path('pos_assets/js/pos_products.js')) ?: time() }}"></script>
    <script src="{{ asset('public/pos_assets/js/pos_payment.js') }}?v={{ @filemtime(public_path('pos_assets/js/pos_payment.js')) ?: time() }}"></script>
    <script src="{{ asset('public/pos_assets/js/sales.js') }}?v={{ @filemtime(public_path('pos_assets/js/sales.js')) ?: time() }}"></script>
    <script src="{{ asset('public/pos_assets/js/sale_returns.js') }}?v={{ @filemtime(public_path('pos_assets/js/sale_returns.js')) ?: time() }}"></script>
    <script src="{{ asset('public/pos_assets/js/hold_sale.js') }}?v={{ @filemtime(public_path('pos_assets/js/hold_sale.js')) ?: time() }}"></script>
    <script src="{{ asset('public/pos_assets/js/pos_utilities.js') }}?v={{ @filemtime(public_path('pos_assets/js/pos_utilities.js')) ?: time() }}"></script>
    <script src="{{ asset('public/backend_assets/js/pages_js/add_sale_return.js') }}?v={{ @filemtime(public_path('backend_assets/js/pages_js/add_sale_return.js')) ?: time() }}"></script>
    <!-- @include('backend.components.pwa_scripts') -->
    @include('printmanager::partials.integration-scripts')
  </body>
</html>
