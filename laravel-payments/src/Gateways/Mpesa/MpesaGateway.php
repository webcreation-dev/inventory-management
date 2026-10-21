<?php

namespace Mdiqbal\LaravelPayments\Gateways\Mpesa;

use Mdiqbal\LaravelPayments\Core\AbstractGateway;
use Mdiqbal\LaravelPayments\DTO\PaymentRequest;
use Mdiqbal\LaravelPayments\DTO\PaymentResponse;
use Mdiqbal\LaravelPayments\Exceptions\PaymentException;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class MpesaGateway extends AbstractGateway
{
    private ?Client $httpClient = null;
    private ?string $accessToken = null;

    public function gatewayName(): string
    {
        return 'mpesa';
    }

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    public function pay(PaymentRequest $request): PaymentResponse
    {
        try {
            $consumerKey = $this->getConsumerKey();
            $consumerSecret = $this->getConsumerSecret();
            $shortcode = $this->getShortcode();
            $passkey = $this->getPasskey();

            if (!$consumerKey || !$consumerSecret || !$shortcode || !$passkey) {
                throw new PaymentException('Mpesa credentials not configured. Please check your payment method settings.');
            }

            // Get access token
            $this->accessToken = $this->getAccessToken();

            // Initialize STK Push payment
            $stkPushResponse = $this->initiateSTKPush($request);

            $this->log('info', 'Mpesa STK Push initiated', [
                'checkout_request_id' => $stkPushResponse['CheckoutRequestID'] ?? null,
                'merchant_request_id' => $stkPushResponse['MerchantRequestID'] ?? null,
                'amount' => $request->getAmount(),
                'currency' => $request->getCurrency()
            ]);

            return PaymentResponse::success([
                'transaction_id' => $stkPushResponse['CheckoutRequestID'] ?? $stkPushResponse['MerchantRequestID'] ?? Str::random(20),
                'checkout_request_id' => $stkPushResponse['CheckoutRequestID'] ?? null,
                'merchant_request_id' => $stkPushResponse['MerchantRequestID'] ?? null,
                'customer_message' => $stkPushResponse['CustomerMessage'] ?? 'Please complete the payment on your phone',
                'response_code' => $stkPushResponse['ResponseCode'] ?? null,
                'response_description' => $stkPushResponse['ResponseDescription'] ?? null,
                'amount' => $request->getAmount(),
                'currency' => $request->getCurrency(),
                'status' => 'pending',
                'message' => 'STK Push sent. Please check your phone to complete payment.'
            ]);

        } catch (\Exception $e) {
            $this->log('error', 'Mpesa payment creation failed', [
                'error' => $e->getMessage(),
                'amount' => $request->getAmount()
            ]);
            
            // Provide user-friendly error message
            $errorMessage = $e->getMessage();
            if (stripos($errorMessage, 'credential') !== false || 
                stripos($errorMessage, 'not configured') !== false) {
                throw new PaymentException('Mpesa credentials are not configured correctly. Please check your payment method settings.');
            } elseif (stripos($errorMessage, 'timeout') !== false || 
                      stripos($errorMessage, 'connection') !== false) {
                throw new PaymentException('Mpesa connection error. Please check your internet connection and try again.');
            }
            
            throw new PaymentException('Mpesa payment failed. Please try again or contact support.');
        }
    }

    public function verify(array $payload): PaymentResponse
    {
        try {
            $checkoutRequestId = $payload['CheckoutRequestID'] ?? $payload['checkout_request_id'] ?? null;
            $resultCode = $payload['ResultCode'] ?? $payload['result_code'] ?? null;
            $resultDesc = $payload['ResultDesc'] ?? $payload['result_description'] ?? null;
            $mpesaReceiptNumber = $payload['MpesaReceiptNumber'] ?? $payload['mpesa_receipt_number'] ?? null;

            if (!$checkoutRequestId) {
                throw new PaymentException('Invalid Mpesa transaction response');
            }

            // Check payment status
            $paymentStatus = match($resultCode) {
                0 => 'completed', // Success
                1032 => 'canceled', // User canceled
                1037 => 'timeout', // Timeout
                default => 'failed'
            };

            $isSuccess = $resultCode === 0;

            $this->log('info', 'Mpesa transaction verified', [
                'checkout_request_id' => $checkoutRequestId,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'mpesa_receipt_number' => $mpesaReceiptNumber,
                'status' => $paymentStatus
            ]);

            return new PaymentResponse(
                success: $isSuccess,
                transactionId: $mpesaReceiptNumber ?? $checkoutRequestId,
                status: $paymentStatus,
                data: [
                    'checkout_request_id' => $checkoutRequestId,
                    'mpesa_receipt_number' => $mpesaReceiptNumber,
                    'result_code' => $resultCode,
                    'result_description' => $resultDesc,
                    'amount' => ($payload['Amount'] ?? $payload['amount'] ?? 0) / 1, // Mpesa sends amount as integer
                    'phone_number' => $payload['PhoneNumber'] ?? $payload['phone_number'] ?? null,
                    'transaction_date' => $payload['TransactionDate'] ?? $payload['transaction_date'] ?? null,
                    'verification_data' => $payload
                ]
            );

        } catch (\Exception $e) {
            $this->log('error', 'Mpesa payment verification failed', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            throw new PaymentException('Mpesa payment verification failed: ' . $e->getMessage());
        }
    }

    public function refund(string $transactionId, float $amount): bool
    {
        try {
            $refund = $this->initiateRefund($transactionId, $amount);

            $this->log('info', 'Mpesa refund processed', [
                'refund_id' => $refund['ConversationID'] ?? null,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'status' => $refund['ResponseCode'] ?? 'PENDING'
            ]);

            return true;

        } catch (\Exception $e) {
            $this->log('error', 'Mpesa refund failed', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw new PaymentException('Mpesa refund failed: ' . $e->getMessage());
        }
    }

    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * Get access token from Mpesa OAuth
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $consumerKey = $this->getConsumerKey();
        $consumerSecret = $this->getConsumerSecret();

        if (!$consumerKey || !$consumerSecret) {
            throw new PaymentException('Mpesa consumer key and secret not configured');
        }

        $baseUrl = $this->isSandbox()
            ? 'https://sandbox.safaricom.co.ke'
            : 'https://api.safaricom.co.ke';

        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

        try {
            $response = $this->httpClient->get($baseUrl . '/oauth/v1/generate?grant_type=client_credentials', [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                ],
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (!isset($responseData['access_token'])) {
                throw new PaymentException('Failed to get Mpesa access token');
            }

            $this->accessToken = $responseData['access_token'];
            return $this->accessToken;

        } catch (\Exception $e) {
            $this->log('error', 'Mpesa access token generation failed', [
                'error' => $e->getMessage()
            ]);
            throw new PaymentException('Mpesa authentication failed. Please check your credentials.');
        }
    }

    /**
     * Initiate STK Push payment
     */
    private function initiateSTKPush(PaymentRequest $request): array
    {
        $shortcode = $this->getShortcode();
        $passkey = $this->getPasskey();
        $callbackUrl = $request->getReturnUrl() ?? $request->getCallbackUrl();

        if (!$callbackUrl) {
            throw new PaymentException('Mpesa callback URL is required. Please provide a valid return URL.');
        }

        // Ensure callback URL is absolute
        if (!filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            $callbackUrl = url($callbackUrl);
        }

        $amount = (int) round($request->getAmount());
        
        // Get phone number from customer or metadata
        $customer = $request->getCustomer();
        $metadata = $request->getMetadata();
        $phoneNumber = $customer['phone'] ?? $metadata['phone'] ?? $metadata['customer_phone'] ?? null;
        
        if (!$phoneNumber) {
            throw new PaymentException('Phone number is required for Mpesa payment. Please ensure the customer has a phone number.');
        }
        
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        $accountReference = $request->getTransactionId() ?? $this->generateOrderId();
        $transactionDesc = $request->getDescription() ?? 'Payment';

        // Generate timestamp
        $timestamp = date('YmdHis');
        
        // Generate password (Base64 encoded)
        $password = base64_encode($shortcode . $passkey . $timestamp);

        $baseUrl = $this->isSandbox()
            ? 'https://sandbox.safaricom.co.ke'
            : 'https://api.safaricom.co.ke';

        $requestBody = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $transactionDesc,
        ];

        try {
            $response = $this->httpClient->post($baseUrl . '/mpesa/stkpush/v1/processrequest', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestBody,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (!isset($responseData['ResponseCode']) || $responseData['ResponseCode'] !== '0') {
                $errorMessage = $responseData['CustomerMessage'] ?? $responseData['errorMessage'] ?? 'STK Push failed';
                $errorCode = $responseData['ResponseCode'] ?? 'UNKNOWN';
                
                $this->log('error', 'Mpesa STK Push failed', [
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'response' => $responseData
                ]);
                
                // Provide user-friendly error messages based on error code
                $userFriendlyMessage = $errorMessage;
                if ($errorCode === '1032') {
                    $userFriendlyMessage = 'Payment was cancelled. Please try again.';
                } elseif ($errorCode === '1037') {
                    $userFriendlyMessage = 'Payment request timed out. Please try again.';
                } elseif ($errorCode === '1' || $errorCode === '17' || $errorCode === '20') {
                    $userFriendlyMessage = 'Mpesa service error. Please try again later.';
                } elseif ($errorCode === '1033' || $errorCode === '1034') {
                    $userFriendlyMessage = 'Invalid phone number. Please check your phone number and try again.';
                }
                
                throw new PaymentException("Mpesa payment error [{$errorCode}]: {$userFriendlyMessage}");
            }

            return $responseData;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $errorMessage = $e->getMessage();
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorBody, true);
                
                $this->log('error', 'Mpesa API request exception', [
                    'error' => $e->getMessage(),
                    'response_body' => $errorData,
                    'status_code' => $e->getResponse()->getStatusCode(),
                ]);
                
                if (is_array($errorData) && isset($errorData['errorMessage'])) {
                    $errorMessage = $errorData['errorMessage'];
                } else {
                    $errorMessage = 'Mpesa API error: ' . $errorBody;
                }
            }
            throw new PaymentException($errorMessage);
        }
    }

    /**
     * Initiate refund
     */
    private function initiateRefund(string $transactionId, float $amount): array
    {
        $shortcode = $this->getShortcode();
        $passkey = $this->getPasskey();
        $initiatorName = $this->getConfig('initiator_name') ?? $shortcode;
        $initiatorPassword = $this->getConfig('initiator_password') ?? '';

        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $passkey . $timestamp);

        $baseUrl = $this->isSandbox()
            ? 'https://sandbox.safaricom.co.ke'
            : 'https://api.safaricom.co.ke';

        $requestBody = [
            'Initiator' => $initiatorName,
            'SecurityCredential' => $this->getSecurityCredential(),
            'CommandID' => 'TransactionReversal',
            'TransactionID' => $transactionId,
            'Amount' => (int) round($amount),
            'ReceiverParty' => $shortcode,
            'RecieverIdentifierType' => '4',
            'ResultURL' => $this->getConfig('refund_callback_url') ?? url('/payment-gateway/mpesa/refund-callback'),
            'QueueTimeOutURL' => $this->getConfig('refund_timeout_url') ?? url('/payment-gateway/mpesa/refund-timeout'),
            'Remarks' => 'Refund for transaction ' . $transactionId,
            'Occasion' => 'Refund',
        ];

        $response = $this->httpClient->post($baseUrl . '/mpesa/reversal/v1/request', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $requestBody,
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Format phone number for Mpesa (254XXXXXXXXX format)
     */
    private function formatPhoneNumber(?string $phone): string
    {
        if (!$phone) {
            throw new PaymentException('Phone number is required for Mpesa payment');
        }

        // Remove any non-digit characters
        $phone = preg_replace('/\D/', '', $phone);

        // Convert to Mpesa format (254XXXXXXXXX)
        if (strlen($phone) === 9 && strpos($phone, '0') === 0) {
            // 0XXXXXXXX -> 254XXXXXXXX
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) === 10 && strpos($phone, '0') === 0) {
            // 0XXXXXXXXX -> 254XXXXXXXXX
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) === 12 && strpos($phone, '254') === 0) {
            // Already in correct format
        } elseif (strlen($phone) === 13 && strpos($phone, '+254') === 0) {
            // +254XXXXXXXXX -> 254XXXXXXXXX
            $phone = substr($phone, 1);
        } else {
            throw new PaymentException('Invalid phone number format. Please use format: 07XXXXXXXX or +2547XXXXXXXX');
        }

        return $phone;
    }

    /**
     * Get consumer key
     */
    private function getConsumerKey(): ?string
    {
        return $this->getModeConfig('consumer_key');
    }

    /**
     * Get consumer secret
     */
    private function getConsumerSecret(): ?string
    {
        return $this->getModeConfig('consumer_secret');
    }

    /**
     * Get shortcode (PayBill or Till Number)
     */
    private function getShortcode(): ?string
    {
        return $this->getModeConfig('shortcode');
    }

    /**
     * Get passkey
     */
    private function getPasskey(): ?string
    {
        return $this->getModeConfig('passkey');
    }

    /**
     * Get security credential (encrypted initiator password)
     */
    private function getSecurityCredential(): string
    {
        $securityCredential = $this->getModeConfig('security_credential');
        
        if (!$securityCredential) {
            throw new PaymentException('Mpesa security credential not configured');
        }
        
        return $securityCredential;
    }

    /**
     * Retrieve payment status (for STK Push polling)
     */
    public function retrievePayment(string $transactionId): PaymentResponse
    {
        try {
            $consumerKey = $this->getConsumerKey();
            $consumerSecret = $this->getConsumerSecret();
            $shortcode = $this->getShortcode();
            $passkey = $this->getPasskey();

            if (!$consumerKey || !$consumerSecret || !$shortcode || !$passkey) {
                throw new PaymentException('Mpesa credentials not configured. Please check your payment method settings.');
            }

            // Get access token
            $this->accessToken = $this->getAccessToken();

            // Query STK Push status
            $timestamp = date('YmdHis');
            $password = base64_encode($shortcode . $passkey . $timestamp);

            $baseUrl = $this->isSandbox()
                ? 'https://sandbox.safaricom.co.ke'
                : 'https://api.safaricom.co.ke';

            $requestBody = [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $transactionId,
            ];

            $response = $this->httpClient->get($baseUrl . '/mpesa/stkpushquery/v1/query', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json',
                ],
                'query' => $requestBody,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (!isset($responseData['ResponseCode'])) {
                throw new PaymentException('Failed to retrieve Mpesa payment status');
            }

            $resultCode = $responseData['ResponseCode'];
            $resultDesc = $responseData['ResultDesc'] ?? '';
            $mpesaReceiptNumber = $responseData['MpesaReceiptNumber'] ?? null;

            $paymentStatus = match($resultCode) {
                0 => 'completed', // Success
                1032 => 'canceled', // User canceled
                1037 => 'timeout', // Timeout
                default => 'pending'
            };

            $isSuccess = $resultCode === 0;

            $this->log('info', 'Mpesa payment status retrieved', [
                'checkout_request_id' => $transactionId,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
                'mpesa_receipt_number' => $mpesaReceiptNumber,
                'status' => $paymentStatus
            ]);

            return new PaymentResponse(
                success: $isSuccess,
                transactionId: $mpesaReceiptNumber ?? $transactionId,
                status: $paymentStatus,
                data: [
                    'checkout_request_id' => $transactionId,
                    'mpesa_receipt_number' => $mpesaReceiptNumber,
                    'result_code' => $resultCode,
                    'result_description' => $resultDesc,
                    'amount' => ($responseData['Amount'] ?? 0) / 1,
                    'phone_number' => $responseData['PhoneNumber'] ?? null,
                    'transaction_date' => $responseData['TransactionDate'] ?? null,
                ]
            );

        } catch (\Exception $e) {
            $this->log('error', 'Mpesa payment retrieval failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId
            ]);
            
            // Provide user-friendly error message
            $errorMessage = $e->getMessage();
            if (stripos($errorMessage, 'credential') !== false || 
                stripos($errorMessage, 'not configured') !== false) {
                throw new PaymentException('Mpesa credentials are not configured correctly. Please check your payment method settings.');
            } elseif (stripos($errorMessage, 'timeout') !== false || 
                      stripos($errorMessage, 'connection') !== false) {
                throw new PaymentException('Mpesa connection error. Please check your internet connection and try again.');
            }
            
            throw new PaymentException('Failed to retrieve Mpesa payment status. Please try again.');
        }
    }

    /**
     * Get supported payment methods
     */
    public function getSupportedPaymentMethods(): array
    {
        return [
            'mpesa_stk_push',
            'mpesa_paybill',
            'mpesa_till'
        ];
    }
}
