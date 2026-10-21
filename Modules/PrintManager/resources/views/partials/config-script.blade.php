@php
    $qzSecurity = app(\Modules\PrintManager\Services\QzSecurityService::class);
    $qzStatus = $qzSecurity->securityStatus();
@endphp
<script>
    window.PrintManagerConfig = Object.assign(window.PrintManagerConfig || {}, {
        printEnv: @json(config('printmanager.env', 'development')),
        csrfToken: @json(csrf_token()),
        qz: {
            env: @json(config('printmanager.env', 'development')),
            signature_algorithm: @json($qzStatus['signature_algorithm'] ?? 'SHA512'),
            certificateConfigured: @json($qzStatus['certificate_configured'] ?? false),
            privateKeyConfigured: @json($qzStatus['private_key_configured'] ?? false),
            reconnect_interval_ms: @json(config('printmanager.qz.reconnect_interval_ms', 3000)),
            connection_timeout_ms: @json(config('printmanager.qz.connection_timeout_ms', 10000)),
            host: @json(config('printmanager.qz.host', 'localhost')),
        },
        routes: Object.assign((window.PrintManagerConfig && window.PrintManagerConfig.routes) || {}, {
            certificate: @json(route('printmanager.qz.certificate')),
            sign: @json(route('printmanager.qz.sign')),
            status: @json(route('printmanager.qz.status')),
        })
    });
</script>
