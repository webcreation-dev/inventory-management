{{-- Include in POS or module pages that need printing --}}
@include('printmanager::partials.scripts')
@include('printmanager::partials.config-script')
<script>
    window.PrintManagerConfig = Object.assign(window.PrintManagerConfig || {}, {
        routes: Object.assign(window.PrintManagerConfig.routes || {}, {
            receipt: @json(route('printmanager.print.receipt')),
            html: @json(route('printmanager.print.html')),
            raw: @json(route('printmanager.print.raw')),
            pdf: @json(route('printmanager.print.pdf')),
            log: @json(route('printmanager.print.log')),
        }),
        branch_id: @json(session('outlet.outlet_id')),
    });
</script>
<script src="{{ asset('public/backend_assets/js/printmanager/qz-manager.js') }}?v={{ @filemtime(public_path('backend_assets/js/printmanager/qz-manager.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/printmanager/printer.js') }}?v={{ @filemtime(public_path('backend_assets/js/printmanager/printer.js')) ?: time() }}"></script>
<script src="{{ asset('public/backend_assets/js/printmanager/receipt-printer.js') }}?v={{ @filemtime(public_path('backend_assets/js/printmanager/receipt-printer.js')) ?: time() }}"></script>
