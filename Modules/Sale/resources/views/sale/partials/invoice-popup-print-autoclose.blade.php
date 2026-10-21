{{-- Popup invoice window: auto-print on load then close after print dialog ends (opened via window.open from POS). --}}
<script>
(function () {
    function posInvoicePrintPopup() {
        try {
            window.print();
        } catch (e) {}
    }

    window.addEventListener('load', function () {
        posInvoicePrintPopup();
    });

    window.addEventListener('afterprint', function () {
        window.close();
    });
})();
</script>
