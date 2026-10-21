<?php

namespace Modules\Configuration\Services;

use Modules\Configuration\Models\Company;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;

class EmailService
{
    /**
     * Send email using configured mail service
     *
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param array $credentials
     * @param string $type Template type: 'Test Email', 'Quotation', 'Birthday', 'Anniversary', 'Customer', 'SaaS Company Created', 'SaaS Contact', 'SaaS Contact Reply', 'SaaS Upgrade Invite'
     * @param array|null $attachment Optional PDF attachment: ['content' => string, 'filename' => string]
     * @param array $extraViewData Optional extra data for the email view (e.g. referenceNo, customerName)
     * @return array
     */
    public function sendEmail($to, $subject, $message, $credentials = [], $type = 'Test Email', $attachment = null, $extraViewData = [])
    {
        try {
            $companyId = (int) ($extraViewData['company_id'] ?? session('company.company_id', 1));
            $company = Company::find($companyId > 0 ? $companyId : 1);
            
            if (!$company) {
                return [
                    'status' => 'Error',
                    'message' => __('Company not found')
                ];
            }

            // Prepare email data
            $result = (object)[
                'company_id' => $company->id,
                'message' => $message,
                'company' => $company,
                'extraViewData' => $extraViewData,
            ];

            // Get mail settings from credentials or database
            if (!empty($credentials)) {
                $mailType = $credentials['mail_type'] ?? 'smtp';
                $hostAddress = $credentials['host_address'] ?? '';
                $mailPort = $credentials['mail_port'] ?? null;
                $encryption = $credentials['encryption'] ?? 'tls';
                $mailUsername = $credentials['mail_username'] ?? '';
                $mailPassword = $credentials['mail_password'] ?? '';
                $mailFromEmail = $credentials['mail_from'] ?? '';
                $mailFromName = $credentials['mail_from_name'] ?? '';
                $mailApiKey = $credentials['mail_api_key'] ?? '';
            } else {
                $smtpDetails = json_decode($company->smtp_details ?? '{}', true);
                $mailType = $company->smtp_type ?? 'smtp';
                $hostAddress = $smtpDetails['host_name'] ?? '';
                $mailPort = $smtpDetails['port_address'] ?? '';
                $encryption = $smtpDetails['encryption'] ?? 'tls';
                $mailUsername = $smtpDetails['user_name'] ?? '';
                $mailPassword = $smtpDetails['password'] ?? '';
                $mailFromEmail = $smtpDetails['from_email'] ?? '';
                $mailFromName = $smtpDetails['from_name'] ?? '';
                $mailApiKey = $smtpDetails['api_key'] ?? '';
            }

            // Ensure port is int for Laravel Mail DSN (avoids "Argument #5 ($port) must be of type ?int, string given")
            $mailPort = $mailPort !== '' && $mailPort !== null ? (int) $mailPort : null;

            $config = [
                'host' => $hostAddress,
                'port' => $mailPort,
                'encryption' => $encryption,
                'username' => $mailUsername,
                'password' => $mailPassword,
                'fromEmail' => $mailFromEmail,
                'fromName' => $mailFromName,
                'mailApiKey' => $mailApiKey,
                'to' => $to,
                'subject' => $subject,
                'result' => $result,
                'type' => $type,
                'attachment' => $attachment,
            ];

            // Map SMTP type names
            $mailType = strtolower($mailType);
            if ($mailType === 'gmail') {
                return $this->sendViaSMTP($config);
            } elseif ($mailType === 'sendinblue') {
                return $this->sendViaBrevo($config);
            } else {
                return $this->sendViaSMTP($config);
            }
        } catch (\Exception $e) {
            Log::error('Email sending failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return [
                'status' => 'Error',
                'message' => __('Email could not be sent. Please check your mail configuration in settings.')
            ];
        }
    }

    /**
     * Send email via SMTP
     *
     * @param array $config
     * @return array
     */
    private function sendViaSMTP($config)
    {
        try {
            Config::set('mail.mailers.smtp', [
                'transport' => 'smtp',
                'host' => $config['host'],
                'port' => $config['port'],
                'encryption' => $config['encryption'],
                'username' => $config['username'],
                'password' => $config['password'],
            ]);
            Config::set('mail.default', 'smtp');
            Config::set('mail.from.address', $config['fromEmail']);
            Config::set('mail.from.name', $config['fromName']);
            app()->forgetInstance('mailer');

            $senderCompany = $config['result']->company;
            $data = array_merge([
                'subject' => $config['subject'],
                'companyName' => $senderCompany->business_name ?? '',
                'companyEmail' => $senderCompany->email ?? '',
                'companyPhone' => $senderCompany->phone ?? '',
                'message_test' => $config['result']->message ?? '',
            ], $config['result']->extraViewData ?? []);

            $templatePath = $this->getEmailTemplate($config['type']);
            Mail::send($templatePath, $data, function (Message $message) use ($config) {
                $message->to($config['to'])
                    ->subject($config['subject'])
                    ->from($config['fromEmail'], $config['fromName']);
                if (!empty($config['attachment']) && !empty($config['attachment']['content']) && !empty($config['attachment']['filename'])) {
                    $message->attachData($config['attachment']['content'], $config['attachment']['filename'], ['mime' => 'application/pdf']);
                }
            });

            return [
                'status' => 'Success',
                'message' => __('Email sent successfully via SMTP')
            ];
        } catch (\Exception $e) {
            Log::error('SMTP email failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return [
                'status' => 'Error',
                'message' => __('Email could not be sent. Please check your mail configuration in settings.')
            ];
        }
    }

    /**
     * Send email via Brevo (Sendinblue)
     *
     * @param array $config
     * @return array
     */
    private function sendViaBrevo($config)
    {
        try {
            $senderCompany = $config['result']->company;
            $data = array_merge([
                'subject' => $config['subject'],
                'companyName' => $senderCompany->business_name ?? '',
                'companyEmail' => $senderCompany->email ?? '',
                'companyPhone' => $senderCompany->phone ?? '',
                'message_test' => $config['result']->message ?? '',
            ], $config['result']->extraViewData ?? []);

            $payload = [
                'sender' => [
                    'name' => $config['fromName'],
                    'email' => $config['username']
                ],
                'to' => [
                    [
                        'email' => $config['to']
                    ]
                ],
                'subject' => $config['subject'],
                'htmlContent' => view($this->getEmailTemplate($config['type']), $data)->render(),
            ];

            // Add PDF attachment if provided (Brevo expects base64-encoded content)
            $attachment = $config['attachment'] ?? null;
            if ($attachment && !empty($attachment['content']) && !empty($attachment['filename'])) {
                $payload['attachment'] = [
                    [
                        'name' => $attachment['filename'],
                        'content' => base64_encode($attachment['content']),
                    ]
                ];
            }

            $response = Http::withHeaders([
                'api-key' => $config['mailApiKey'],
                'content-type' => 'application/json'
            ])->post('https://api.brevo.com/v3/smtp/email', $payload);

            if ($response->successful()) {
                Log::info('Brevo API email sent', ['messageId' => $response->json()['messageId'] ?? '']);
                return [
                    'status' => 'Success',
                    'message' => __('Email sent successfully via Brevo API'),
                    'messageId' => $response->json()['messageId'] ?? ''
                ];
            }

            Log::error('Brevo API send failed', ['response' => $response->body()]);
            return [
                'status' => 'Error',
                'message' => __('Failed to send email via Brevo API'),
                'details' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('Brevo API exception', ['exception' => $e]);
            return [
                'status' => 'Error',
                'message' => __('Email could not be sent. Please check your mail configuration in settings.')
            ];
        }
    }

    /**
     * Get email template based on type
     *
     * @param string $type Template type: 'Test Email', 'Quotation', 'Birthday', 'Anniversary', 'Customer', 'SaaS Company Created', 'SaaS Contact', 'SaaS Contact Reply', 'SaaS Upgrade Invite'
     * @return string
     */
    private function getEmailTemplate($type)
    {
        switch ($type) {
            case 'Test Email':
                return 'configuration::email.test-email';
            case 'Quotation':
                return 'configuration::email.quotation';
            case 'Birthday':
                return 'configuration::email.birthday';
            case 'Anniversary':
                return 'configuration::email.anniversary';
            case 'Customer':
                return 'configuration::email.customer';
            case 'Sale':
                return 'configuration::email.sale';
            case 'SaaS Company Created':
                return $this->saasTemplateOrFallback('saas::emails.company-created');
            case 'SaaS Contact':
                return $this->saasTemplateOrFallback('saas::emails.saas-contact');
            case 'SaaS Contact Reply':
                return $this->saasTemplateOrFallback('saas::emails.saas-contact-reply');
            case 'SaaS Upgrade Invite':
                return $this->saasTemplateOrFallback('saas::emails.upgrade-invite');
            case 'Ecommerce Email Verification':
                return $this->moduleTemplateOrFallback('ecommerce::emails.verify-email');
            case 'Ecommerce Password Reset':
                return $this->moduleTemplateOrFallback('ecommerce::emails.reset-password');
            case 'Ecommerce Contact':
                return $this->moduleTemplateOrFallback('ecommerce::emails.contact-notify');
            case 'Ecommerce Contact Reply':
                return $this->moduleTemplateOrFallback('ecommerce::emails.contact-reply');
            default:
                return 'configuration::email.test-email';
        }
    }

    /**
     * When Modules/SaaS is not installed, saas:: views are missing — use core test template.
     */
    private function saasTemplateOrFallback(string $saasView): string
    {
        return $this->moduleTemplateOrFallback($saasView);
    }

    /**
     * When a module view namespace is not registered, fall back to the core test template.
     */
    private function moduleTemplateOrFallback(string $moduleView): string
    {
        return View::exists($moduleView) ? $moduleView : 'configuration::email.test-email';
    }
}
