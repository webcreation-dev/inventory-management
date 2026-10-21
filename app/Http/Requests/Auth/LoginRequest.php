<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Name stays `email` for routes/views; value may be email or username (`name`).
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Custom attribute labels so built-in "required" messages read correctly.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('email, username, or phone'),
            'password' => __('password'),
        ];
    }

    /**
     * Resolve a user by login identifier: email, username (`name`), or phone (exact or digits-only match).
     */
    protected function resolveUserFromIdentifier(string $identifier): ?User
    {
        $user = User::query()
            ->where('del_status', 'Live')
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                    ->orWhere('phone', $identifier);
            })
            ->first();

        if ($user !== null) {
            return $user;
        }

        $digits = preg_replace('/\D+/', '', $identifier);
        if ($digits === '' || strlen($digits) < 7) {
            return null;
        }

        return User::query()
            ->where('del_status', 'Live')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->first(static function (User $u) use ($digits) {
                $stored = preg_replace('/\D+/', '', (string) $u->phone);

                return $stored !== '' && $stored === $digits;
            });
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = $this->string('email')->trim()->toString();
        $password = $this->string('password')->toString();

        $user = $this->resolveUserFromIdentifier($identifier);

        if ($user === null || ! Hash::check($password, $user->getAuthPassword())) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        Auth::login($user, $this->boolean('remember'));

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
