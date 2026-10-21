<?php

/**
 * @deprecated Use config('printmanager.qz.*') and Modules\PrintManager\Services\QzSecurityService.
 * Kept for backward compatibility with legacy code only.
 */
return [
    'certificate_path' => config('printmanager.qz.certificate_path'),
    'private_key_path' => config('printmanager.qz.private_key_path'),
    'invoice_footer' => config('printmanager.receipt.footer'),
];
