<?php

namespace App\Payments;

use GuzzleHttp\Exception\RequestException;
use Mdiqbal\LaravelPayments\Core\AbstractGateway;
use Mdiqbal\LaravelPayments\DTO\PaymentRequest;
use Mdiqbal\LaravelPayments\DTO\PaymentResponse;
use Mdiqbal\LaravelPayments\Exceptions\PaymentException;

/**
 * Paystack via REST API (replaces vendor gateway that depended on missing Oladejo\LaravelPaystack).
 */
class GuzzlePaystackGateway extends AbstractGateway
{
    private const API_BASE = 'https://api.paystack.co';

    public function gatewayName(): string
    {
        return 'paystack';
    }

    public function pay(PaymentRequest $request): PaymentResponse
    {
        $secretKey = $this->getSecretKey();
        if (! $secretKey) {
            throw new PaymentException('Paystack secret key not configured');
        }

        $metadata = $request->getMetadata();
        $email = $request->getCustomerEmail();
        if ($email === '' || $email === null) {
            $email = (string) ($metadata['email'] ?? 'customer@example.com');
        }

        $reference = $request->getOrderId();
        $amountKobo = (int) round($request->getAmount() * 100);
        $currency = strtoupper($request->getCurrency() ?: 'NGN');

        $body = [
            'amount' => $amountKobo,
            'email' => $email,
            'reference' => $reference,
            'currency' => $currency,
            'callback_url' => $request->getReturnUrl(),
            'metadata' => array_filter([
                'description' => $request->getDescription() ?? __('Payment'),
            ], fn ($v) => $v !== null && $v !== ''),
        ];

        $response = $this->paystackRequest('POST', '/transaction/initialize', $secretKey, $body);

        if (! ($response['status'] ?? false)) {
            $this->handleError($response);
        }

        $data = $response['data'] ?? [];
        $authUrl = $data['authorization_url'] ?? null;
        if (! $authUrl) {
            throw new PaymentException($response['message'] ?? 'Paystack did not return a payment URL');
        }

        return PaymentResponse::redirect($authUrl, [
            'transaction_id' => $data['reference'] ?? $reference,
            'reference' => $data['reference'] ?? $reference,
            'access_code' => $data['access_code'] ?? null,
            'authorization_url' => $authUrl,
            'status' => 'created',
            'message' => __('Transaction created successfully'),
        ]);
    }

    public function verify(array $payload): PaymentResponse
    {
        $reference = $payload['reference']
            ?? ($payload['trxref'] ?? null)
            ?? ($payload['data']['reference'] ?? null);

        if (! $reference) {
            throw new PaymentException('No Paystack reference found in payload');
        }

        $secretKey = $this->getSecretKey();
        if (! $secretKey) {
            throw new PaymentException('Paystack secret key not configured');
        }

        $response = $this->paystackRequest(
            'GET',
            '/transaction/verify/'.rawurlencode((string) $reference),
            $secretKey,
            null,
        );

        if (! ($response['status'] ?? false)) {
            $this->handleError($response);
        }

        $tx = $response['data'] ?? [];
        $status = (string) ($tx['status'] ?? '');

        $normalized = match ($status) {
            'success' => 'completed',
            'failed' => 'failed',
            'abandoned' => 'canceled',
            'reversed' => 'refunded',
            default => 'unknown',
        };

        $success = $status === 'success';

        return new PaymentResponse(
            success: $success,
            transactionId: isset($tx['reference']) ? (string) $tx['reference'] : (string) $reference,
            redirectUrl: null,
            message: $response['message'] ?? null,
            status: $normalized,
            data: [
                'reference' => $tx['reference'] ?? $reference,
                'amount' => isset($tx['amount']) ? (float) $tx['amount'] / 100 : null,
                'currency' => $tx['currency'] ?? null,
                'gateway_response' => $tx['gateway_response'] ?? null,
                'paid_at' => $tx['paid_at'] ?? null,
                'channel' => $tx['channel'] ?? null,
                'verification_data' => $payload,
            ],
        );
    }

    public function refund(string $transactionId, float $amount): bool
    {
        $secretKey = $this->getSecretKey();
        if (! $secretKey) {
            throw new PaymentException('Paystack secret key not configured');
        }

        $body = ['transaction' => $transactionId];
        if ($amount > 0) {
            $body['amount'] = (int) round($amount * 100);
        }

        $response = $this->paystackRequest('POST', '/refund', $secretKey, $body);

        if (! ($response['status'] ?? false)) {
            $this->handleError($response);
        }

        return true;
    }

    public function supportsRefund(): bool
    {
        return true;
    }

    private function getSecretKey(): ?string
    {
        return $this->getModeConfig('secret_key');
    }

    /**
     * Paystack HTTP calls must not use {@see AbstractGateway::makeRequest()}: it passes Guzzle
     * instances to {@see PaymentException}'s $previous param, which is wrongly typed as
     * `Mdiqbal\LaravelPayments\Exceptions\Throwable` instead of PHP's `\Throwable`.
     *
     * @param  array<string, mixed>|null  $jsonBody
     * @return array<string, mixed>
     */
    private function paystackRequest(string $method, string $path, string $secretKey, ?array $jsonBody): array
    {
        $url = rtrim(self::API_BASE, '/').'/'.ltrim($path, '/');
        $options = [
            'headers' => [
                'Authorization' => 'Bearer '.$secretKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'http_errors' => false,
        ];
        if ($jsonBody !== null && strtoupper($method) !== 'GET') {
            $options['json'] = $jsonBody;
        }

        try {
            $httpResponse = $this->client->request($method, $url, $options);
        } catch (RequestException $e) {
            $message = 'Paystack connection error: '.$e->getMessage();
            if ($e->hasResponse()) {
                $body = (string) $e->getResponse()->getBody();
                $data = json_decode($body, true);
                if (is_array($data) && isset($data['message'])) {
                    $message = is_string($data['message']) ? $data['message'] : json_encode($data['message']);
                }
            }
            throw new PaymentException($message);
        } catch (\Throwable $e) {
            throw new PaymentException('Paystack request failed: '.$e->getMessage());
        }

        $body = (string) $httpResponse->getBody();
        $data = json_decode($body, true);

        if (! is_array($data)) {
            throw new PaymentException(__('Invalid JSON response from Paystack.'));
        }

        $statusCode = $httpResponse->getStatusCode();
        if ($statusCode >= 400) {
            $message = is_string($data['message'] ?? null)
                ? $data['message']
                : __('Paystack error (HTTP :code).', ['code' => $statusCode]);

            throw new PaymentException($message);
        }

        return $data;
    }
}
