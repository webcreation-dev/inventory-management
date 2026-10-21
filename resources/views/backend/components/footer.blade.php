@php
  $whiteLabel = getWhiteLabel();
  $lpIsBd = defined('LP') && strtoupper((string) LP) === 'BD';
@endphp
<footer class="content-footer footer bg-footer-theme">
  <div class="container-xxl">
    <div
      class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
      <div class="text-body">
        @if($lpIsBd)
          <span id="app-footer-text"></span>
        @else
          ©
          <script>
            document.write(new Date().getFullYear());
          </script>
          , {{ session('white_label.site_footer') ?? '' }} by <a href="{{ session('white_label.site_link') ?? '' }}" target="_blank" class="footer-link">{{ session('white_label.site_title') ?? '' }}</a>
        @endif
      </div>
    </div>
  </div>
</footer>