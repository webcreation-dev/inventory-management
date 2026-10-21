<?php

namespace Modules\Ecommerce\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\Configuration\Services\EmailService;
use Modules\Sale\Models\Customer;

class CustomerAuthService
{
    public const SESSION_KEY = 'ecommerce_customer';

    public function check(): bool
    {
        return (bool) $this->customer();
    }

    public function customer(): ?array
    {
        $data = Session::get(self::SESSION_KEY);

        return is_array($data) && ! empty($data['id']) ? $data : null;
    }

    public function id(): ?int
    {
        $customer = $this->customer();

        return $customer ? (int) $customer['id'] : null;
    }

    public function login(string $email, string $password, int $companyId = 0): array
    {
        $query = Customer::query()
            ->where('del_status', 'Live')
            ->where('email', $email);

        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        $customer = $query->first();

        if (! $customer || ! $this->passwordMatches($customer, $password)) {
            throw new \InvalidArgumentException(__('Invalid email or password.'));
        }

        if (! $this->isEmailVerified($customer)) {
            throw new \InvalidArgumentException(__('Please verify your email before logging in. Check your inbox for the activation link.'));
        }

        // Upgrade legacy plain-text passwords to hashed.
        if (! empty($customer->password) && ! $this->isHashed((string) $customer->password)) {
            $customer->password = Hash::make($password);
            $customer->save();
        }

        $payload = $this->toSessionPayload($customer);
        Session::put(self::SESSION_KEY, $payload);

        return $payload;
    }

    public function register(array $data, int $companyId = 0): array
    {
        if ($companyId <= 0) {
            throw ValidationException::withMessages([
                'email' => [__('Store is not configured yet. Please try again later.')],
            ]);
        }

        $emailExists = Customer::query()
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('email', $data['email'])
            ->exists();

        if ($emailExists) {
            throw ValidationException::withMessages([
                'email' => [__('This email is already registered.')],
            ]);
        }

        $phoneExists = Customer::query()
            ->where('company_id', $companyId)
            ->where('del_status', 'Live')
            ->where('phone', $data['phone'])
            ->exists();

        if ($phoneExists) {
            throw ValidationException::withMessages([
                'phone' => [__('This phone number is already registered.')],
            ]);
        }

        $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        $customer = Customer::query()->create([
            'name' => $name,
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'address' => $data['address'] ?? null,
            'customer_type' => '1',
            'price_type' => '1',
            'opening_balance' => 0,
            'opening_balance_type' => 'Credit',
            'credit_limit' => 0,
            'discount' => 0,
            'company_id' => $companyId,
            'user_id' => null,
            'del_status' => 'Live',
            'email_verified' => false,
        ]);

        $emailSent = $this->sendVerificationEmail($customer, $companyId);

        return [
            'customer' => $this->toSessionPayload($customer),
            'email_sent' => $emailSent,
        ];
    }

    public function sendVerificationEmail(Customer $customer, ?int $companyId = null): bool
    {
        $companyId = $companyId ?: (int) ($customer->company_id ?: 1);
        $verifyUrl = URL::temporarySignedRoute(
            'ecommerce.verify-email',
            now()->addHours(48),
            ['customer' => $customer->id]
        );

        $customerName = trim((string) $customer->name) ?: __('Customer');
        $subject = __('Verify your email address');
        $message = __('Please confirm your email to activate your store account and start shopping.');

        $branding = [];
        try {
            $branding = app(EcommerceContentService::class)->branding();
        } catch (\Throwable) {
            $branding = [];
        }

        $palette = \Modules\Ecommerce\Support\BrandColor::palette($branding['brandColor'] ?? null);
        $siteName = (string) ($branding['siteName'] ?? '');
        $siteLogo = $branding['siteLogo'] ?? null;
        if (is_string($siteLogo) && $siteLogo !== '' && ! str_starts_with($siteLogo, 'http')) {
            $siteLogo = url($siteLogo);
        }

        try {
            $result = app(EmailService::class)->sendEmail(
                (string) $customer->email,
                $subject,
                $message,
                [],
                'Ecommerce Email Verification',
                null,
                [
                    'company_id' => $companyId,
                    'customerName' => $customerName,
                    'verifyUrl' => $verifyUrl,
                    'activateLabel' => __('Activate Account'),
                    'expiresLabel' => __('This link expires in 48 hours.'),
                    'siteName' => $siteName !== '' ? $siteName : null,
                    'siteLogo' => $siteLogo,
                    'brandColor' => $palette['hex'],
                    'brandAccent' => $palette['accent'],
                    'brandStrong' => $palette['strong'],
                    'brandSoft' => $palette['soft'],
                    'brandGlow' => $palette['glow'],
                    'brandRgb' => $palette['rgb'],
                ]
            );

            return ($result['status'] ?? '') === 'Success';
        } catch (\Throwable $e) {
            Log::error('Ecommerce verification email failed', [
                'customer_id' => $customer->id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function resendVerification(string $email, int $companyId = 0): bool
    {
        $query = Customer::query()
            ->where('del_status', 'Live')
            ->where('email', $email);

        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        $customer = $query->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                'email' => [__('We could not find an account with that email.')],
            ]);
        }

        if ($this->isEmailVerified($customer)) {
            throw ValidationException::withMessages([
                'email' => [__('This email is already verified. You can log in.')],
            ]);
        }

        if (! $this->sendVerificationEmail($customer, $companyId ?: (int) $customer->company_id)) {
            throw ValidationException::withMessages([
                'email' => [__('Unable to send verification email. Please try again later.')],
            ]);
        }

        return true;
    }

    public function verifyEmailById(int $customerId): Customer
    {
        $customer = Customer::query()
            ->where('id', $customerId)
            ->where('del_status', 'Live')
            ->first();

        if (! $customer) {
            throw new \InvalidArgumentException(__('Invalid or expired verification link.'));
        }

        if (! $this->isEmailVerified($customer)) {
            $customer->email_verified = true;
            $customer->save();
        }

        return $customer;
    }

    public function isEmailVerified(Customer $customer): bool
    {
        $value = $customer->email_verified;

        return $value === true || $value === 1 || $value === '1';
    }

    public function updateProfile(array $data): array
    {
        $session = $this->customer();
        if (! $session) {
            throw new \InvalidArgumentException(__('Please login first.'));
        }

        $customer = Customer::query()
            ->where('id', (int) $session['id'])
            ->where('del_status', 'Live')
            ->first();

        if (! $customer) {
            throw new \InvalidArgumentException(__('Customer not found.'));
        }

        $companyId = (int) ($customer->company_id ?? 0);

        if (! empty($data['email']) && $data['email'] !== $customer->email) {
            $emailExists = Customer::query()
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where('email', $data['email'])
                ->where('id', '!=', $customer->id)
                ->exists();
            if ($emailExists) {
                throw ValidationException::withMessages([
                    'email' => [__('This email is already registered.')],
                ]);
            }
        }

        if (! empty($data['phone']) && $data['phone'] !== $customer->phone) {
            $phoneExists = Customer::query()
                ->where('company_id', $companyId)
                ->where('del_status', 'Live')
                ->where('phone', $data['phone'])
                ->where('id', '!=', $customer->id)
                ->exists();
            if ($phoneExists) {
                throw ValidationException::withMessages([
                    'phone' => [__('This phone number is already registered.')],
                ]);
            }
        }

        $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        $customer->name = $name !== '' ? $name : $customer->name;
        $customer->email = $data['email'] ?? $customer->email;
        $customer->phone = $data['phone'] ?? $customer->phone;
        $customer->address = $data['address'] ?? $customer->address;

        if (! empty($data['photo_path'])) {
            $this->deletePhotoFile($customer->photo);
            $customer->photo = $data['photo_path'];
        }

        if (! empty($data['password'])) {
            $customer->password = Hash::make($data['password']);
        }
        $customer->save();

        $payload = $this->toSessionPayload($customer);
        Session::put(self::SESSION_KEY, $payload);

        return $payload;
    }

    public function updatePassword(string $password): array
    {
        return $this->updateProfile(['password' => $password]);
    }

    public function sendPasswordResetEmail(string $email, int $companyId = 0): bool
    {
        $query = Customer::query()
            ->where('del_status', 'Live')
            ->where('email', $email);

        if ($companyId > 0) {
            $query->where('company_id', $companyId);
        }

        $customer = $query->first();

        if (! $customer) {
            throw ValidationException::withMessages([
                'email' => [__('We could not find an account with that email.')],
            ]);
        }

        $companyId = $companyId > 0 ? $companyId : (int) ($customer->company_id ?: 1);
        $resetUrl = URL::temporarySignedRoute(
            'ecommerce.reset-password',
            now()->addMinutes(60),
            ['customer' => $customer->id]
        );

        $customerName = trim((string) $customer->name) ?: __('Customer');
        $subject = __('Reset your password');
        $message = __('We received a request to reset the password for your store account.');

        $branding = [];
        try {
            $branding = app(EcommerceContentService::class)->branding();
        } catch (\Throwable) {
            $branding = [];
        }

        $palette = \Modules\Ecommerce\Support\BrandColor::palette($branding['brandColor'] ?? null);
        $siteName = (string) ($branding['siteName'] ?? '');
        $siteLogo = $branding['siteLogo'] ?? null;
        if (is_string($siteLogo) && $siteLogo !== '' && ! str_starts_with($siteLogo, 'http')) {
            $siteLogo = url($siteLogo);
        }

        try {
            $result = app(EmailService::class)->sendEmail(
                (string) $customer->email,
                $subject,
                $message,
                [],
                'Ecommerce Password Reset',
                null,
                [
                    'company_id' => $companyId,
                    'customerName' => $customerName,
                    'resetUrl' => $resetUrl,
                    'resetLabel' => __('Reset Password'),
                    'expiresLabel' => __('This link expires in 60 minutes.'),
                    'siteName' => $siteName !== '' ? $siteName : null,
                    'siteLogo' => $siteLogo,
                    'brandColor' => $palette['hex'],
                    'brandAccent' => $palette['accent'],
                    'brandStrong' => $palette['strong'],
                    'brandSoft' => $palette['soft'],
                    'brandGlow' => $palette['glow'],
                    'brandRgb' => $palette['rgb'],
                ]
            );

            if (($result['status'] ?? '') !== 'Success') {
                throw ValidationException::withMessages([
                    'email' => [__('Unable to send password reset email. Please try again later.')],
                ]);
            }

            return true;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Ecommerce password reset email failed', [
                'customer_id' => $customer->id,
                'exception' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => [__('Unable to send password reset email. Please try again later.')],
            ]);
        }
    }

    public function resetPasswordById(int $customerId, string $password): Customer
    {
        $customer = Customer::query()
            ->where('id', $customerId)
            ->where('del_status', 'Live')
            ->first();

        if (! $customer) {
            throw new \InvalidArgumentException(__('Invalid or expired password reset link.'));
        }

        $customer->password = Hash::make($password);
        $customer->save();

        return $customer;
    }

    public function logout(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public function storeCroppedPhoto(string $base64Image): string
    {
        if (! str_starts_with($base64Image, 'data:image')) {
            throw ValidationException::withMessages([
                'photo' => [__('Invalid image data.')],
            ]);
        }

        $extension = 'jpg';
        if (str_starts_with($base64Image, 'data:image/png')) {
            $extension = 'png';
        } elseif (str_starts_with($base64Image, 'data:image/webp')) {
            $extension = 'webp';
        }

        $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image), true);
        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'photo' => [__('Unable to process image.')],
            ]);
        }

        if (strlen($binary) > 500 * 1024) {
            throw ValidationException::withMessages([
                'photo' => [__('Image must be 500KB or smaller.')],
            ]);
        }

        $info = @getimagesizefromstring($binary);
        if (! $info || empty($info[0]) || empty($info[1])) {
            throw ValidationException::withMessages([
                'photo' => [__('Invalid image file.')],
            ]);
        }

        if ((int) $info[0] > 250 || (int) $info[1] > 250) {
            throw ValidationException::withMessages([
                'photo' => [__('Image dimensions must be 250×250px or smaller.')],
            ]);
        }

        $dir = public_path('uploads/customers');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fileName = 'c' . time() . substr(uniqid(), -5) . '.' . $extension;
        file_put_contents($dir . DIRECTORY_SEPARATOR . $fileName, $binary);

        return 'uploads/customers/' . $fileName;
    }

    public function photoUrl(?string $photo): ?string
    {
        if (! $photo) {
            return null;
        }

        $path = ltrim($photo, '/');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('public/' . $path);
    }

    protected function deletePhotoFile(?string $photo): void
    {
        if (! $photo) {
            return;
        }

        $path = public_path(ltrim($photo, '/'));
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function toSessionPayload(Customer $customer): array
    {
        $name = trim((string) $customer->name);
        $parts = preg_split('/\s+/', $name, 2) ?: [];
        $photo = (string) ($customer->photo ?? '');

        return [
            'id' => (int) $customer->id,
            'name' => $name,
            'first_name' => $parts[0] ?? $name,
            'last_name' => $parts[1] ?? '',
            'email' => (string) ($customer->email ?? ''),
            'phone' => (string) ($customer->phone ?? ''),
            'address' => (string) ($customer->delivery_address ?: $customer->address ?: $customer->shipping_address ?: ''),
            'delivery_address' => (string) ($customer->delivery_address ?? ''),
            'photo' => $photo,
            'photo_url' => $this->photoUrl($photo ?: null),
            'email_verified' => $this->isEmailVerified($customer),
        ];
    }

    protected function passwordMatches(Customer $customer, string $password): bool
    {
        $stored = (string) ($customer->password ?? '');

        if ($stored === '') {
            return false;
        }

        if ($this->isHashed($stored)) {
            return Hash::check($password, $stored);
        }

        return hash_equals($stored, $password);
    }

    protected function isHashed(string $value): bool
    {
        return str_starts_with($value, '$2y$')
            || str_starts_with($value, '$2a$')
            || str_starts_with($value, '$argon');
    }
}
