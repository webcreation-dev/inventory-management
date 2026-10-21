<?php

namespace Modules\PrintManager\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\PrintManager\Exceptions\QzSecurityException;
use Modules\PrintManager\Http\Requests\QzSignRequest;
use Modules\PrintManager\Services\PrintLogService;
use Modules\PrintManager\Services\QzSecurityService;

/**
 * Official QZ Tray 2.x certificate and signing endpoints.
 *
 * @see https://qz.io/docs/signing
 */
class QzTraySecurityController extends Controller
{
    public function __construct(
        protected QzSecurityService $qzSecurity,
        protected PrintLogService $printLogs,
    ) {
    }

    /**
     * GET /print/qz/certificate
     *
     * Returns the PEM x509 certificate (digital-certificate.txt) from storage.
     */
    public function certificate(Request $request): JsonResponse|Response
    {
        if ($this->qzSecurity->isDevelopment() && !$this->qzSecurity->certificateExists()) {
            return response()->json([
                'success' => false,
                'message' => 'Development mode: no certificate file configured. QZ Tray will show trust prompts.',
                'error_code' => 'development_unsigned',
                'security' => $this->qzSecurity->securityStatus(),
            ], 200);
        }

        try {
            $certificate = $this->qzSecurity->getCertificate();
        } catch (QzSecurityException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode,
                'security' => $this->qzSecurity->securityStatus(),
            ], $exception->httpStatus());
        }

        if ($request->expectsJson() && !$request->boolean('plain')) {
            return response()->json([
                'success' => true,
                'certificate' => $certificate,
                'security' => $this->qzSecurity->securityStatus(),
            ]);
        }

        return response($certificate, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * POST /print/qz/sign
     *
     * Signs the QZ request using SHA512 + private-key.pem (official workflow).
     */
    public function sign(QzSignRequest $request): JsonResponse|Response
    {
        $payload = $request->signingPayload();

        if ($payload === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing request payload to sign.',
                'error_code' => 'missing_request',
            ], 422);
        }

        if ($this->qzSecurity->isDevelopment() && !$this->qzSecurity->privateKeyExists()) {
            $this->logSigningEvent('failed', 'Development unsigned mode - signing skipped.');

            return response()->json([
                'success' => false,
                'message' => 'Development mode: private key not configured. Use QZ Tray Allow prompt or add demo keys.',
                'error_code' => 'development_unsigned',
            ], 503);
        }

        try {
            $signature = $this->qzSecurity->signRequest($payload);
        } catch (QzSecurityException $exception) {
            $this->logSigningEvent('failed', $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->errorCode,
            ], $exception->httpStatus());
        }

        $this->logSigningEvent('success', 'QZ request signed.');

        if ($request->expectsJson() && !$request->boolean('plain')) {
            return response()->json([
                'success' => true,
                'signature' => $signature,
            ]);
        }

        return response($signature, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * GET /print/qz/status - optional diagnostics for settings UI.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'security' => $this->qzSecurity->securityStatus(),
        ]);
    }

    protected function logSigningEvent(string $status, string $message): void
    {
        if (!config('printmanager.logging.enabled', true)) {
            return;
        }

        $this->printLogs->create([
            'company_id' => session('company.company_id'),
            'branch_id' => session('outlet.outlet_id'),
            'user_id' => auth()->id(),
            'module' => 'QZSecurity',
            'printer' => null,
            'print_method' => 'qz_tray',
            'status' => $status === 'success' ? 'success' : 'failed',
            'error_message' => $status === 'success' ? null : $message,
            'payload' => ['action' => 'sign'],
        ]);
    }
}
