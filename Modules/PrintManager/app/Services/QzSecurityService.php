<?php

namespace Modules\PrintManager\Services;

use Illuminate\Support\Facades\Log;
use Modules\PrintManager\Exceptions\QzSecurityException;
use OpenSSLAsymmetricKey;

/**
 * Official QZ Tray 2.x certificate + SHA512 signing workflow.
 *
 * @see https://qz.io/docs/signing
 */
class QzSecurityService
{
    public function isProduction(): bool
    {
        return strtolower((string) config('printmanager.env', 'development')) === 'production';
    }

    public function isDevelopment(): bool
    {
        return !$this->isProduction();
    }

    public function signatureAlgorithm(): string
    {
        return strtoupper((string) config('printmanager.qz.signature_algorithm', 'SHA512'));
    }

    public function certificatePath(): string
    {
        return (string) config('printmanager.qz.certificate_path');
    }

    public function privateKeyPath(): string
    {
        return (string) config('printmanager.qz.private_key_path');
    }

    public function certificateExists(): bool
    {
        $path = $this->certificatePath();

        return $path !== '' && is_readable($path) && is_file($path);
    }

    public function privateKeyExists(): bool
    {
        $path = $this->privateKeyPath();

        return $path !== '' && is_readable($path) && is_file($path);
    }

    /**
     * Return PEM certificate contents from disk.
     *
     * @throws QzSecurityException
     */
    public function getCertificate(): string
    {
        if (!$this->certificateExists()) {
            $this->logFailure('certificate_missing', 'QZ certificate file not found or not readable.', [
                'path' => $this->certificatePath(),
            ]);

            throw new QzSecurityException(
                'QZ Tray certificate is not configured. Generate keys in storage/app/qz/ (see README).',
                'certificate_missing',
                503
            );
        }

        $certificate = trim((string) file_get_contents($this->certificatePath()));

        if ($certificate === '' || !str_contains($certificate, 'BEGIN CERTIFICATE')) {
            $this->logFailure('certificate_invalid', 'Certificate file is empty or not PEM x509 format.');

            throw new QzSecurityException(
                'QZ Tray certificate file is invalid. Expected PEM x509 (digital-certificate.txt).',
                'certificate_invalid',
                500
            );
        }

        return $certificate;
    }

    /**
     * Sign QZ request payload using private key + SHA512 (QZ Tray 2.1+).
     *
     * @throws QzSecurityException
     */
    public function signRequest(string $request): string
    {
        if ($request === '') {
            throw new QzSecurityException('Missing signing request payload.', 'missing_request', 422);
        }

        if ($this->isProduction() && !$this->privateKeyExists()) {
            $this->logFailure('private_key_missing', 'Private key missing in production mode.', [
                'path' => $this->privateKeyPath(),
            ]);

            throw new QzSecurityException(
                'QZ Tray private key is not configured for production signing.',
                'private_key_missing',
                503
            );
        }

        if (!$this->privateKeyExists()) {
            throw new QzSecurityException(
                'QZ Tray private key is not configured.',
                'private_key_missing',
                503
            );
        }

        $privateKey = $this->loadPrivateKey();
        $algorithm = $this->opensslAlgorithm();
        $signature = '';

        $signed = openssl_sign($request, $signature, $privateKey, $algorithm);

        if (!$signed || $signature === '') {
            $this->logFailure('signing_failed', 'OpenSSL could not sign QZ request.', [
                'openssl_error' => openssl_error_string(),
            ]);

            throw new QzSecurityException(
                'Failed to sign QZ Tray request.',
                'signing_failed',
                500
            );
        }

        Log::channel($this->logChannel())->info('QZ Tray request signed successfully.', [
            'algorithm' => $this->signatureAlgorithm(),
            'request_length' => strlen($request),
        ]);

        return base64_encode($signature);
    }

    /**
     * @return array<string, mixed>
     */
    public function securityStatus(): array
    {
        return [
            'env' => config('printmanager.env'),
            'production' => $this->isProduction(),
            'certificate_configured' => $this->certificateExists(),
            'private_key_configured' => $this->privateKeyExists(),
            'signature_algorithm' => $this->signatureAlgorithm(),
            'certificate_path' => $this->certificatePath(),
        ];
    }

    /**
     * @throws QzSecurityException
     */
    protected function loadPrivateKey(): OpenSSLAsymmetricKey
    {
        $keyContents = file_get_contents($this->privateKeyPath());
        if ($keyContents === false || trim($keyContents) === '') {
            throw new QzSecurityException(
                'Unable to read QZ Tray private key file.',
                'private_key_unreadable',
                500
            );
        }

        $privateKey = openssl_pkey_get_private($keyContents);

        if ($privateKey === false) {
            $this->logFailure('private_key_invalid', 'Private key file is not a valid PEM PKCS#8/RSA key.');

            throw new QzSecurityException(
                'QZ Tray private key file is invalid.',
                'private_key_invalid',
                500
            );
        }

        return $privateKey;
    }

    protected function opensslAlgorithm(): int
    {
        return match ($this->signatureAlgorithm()) {
            'SHA512' => OPENSSL_ALGO_SHA512,
            'SHA256' => OPENSSL_ALGO_SHA256,
            'SHA1' => OPENSSL_ALGO_SHA1,
            default => OPENSSL_ALGO_SHA512,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logFailure(string $code, string $message, array $context = []): void
    {
        Log::channel($this->logChannel())->error('QZ Tray security failure: '.$message, array_merge([
            'code' => $code,
            'user_id' => auth()->id(),
        ], $context));
    }

    protected function logChannel(): string
    {
        return (string) config('printmanager.logging.channel', 'stack');
    }
}
