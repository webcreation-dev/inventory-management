<?php

namespace App\Support;

/**
 * Maps values stored in users.language to Laravel app locale codes.
 */
class UserLocalePreference
{
    /**
     * Convert a stored user preference (legacy name or locale code) to an app locale, or null if unknown.
     */
    public static function toAppLocale(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        $stored = strtolower(trim($stored));
        $aliases = [
            'english' => 'en',
            'arabic' => 'ar',
            'bengali' => 'bn',
            'french' => 'fr',
            'spanish' => 'es',
            'sinhala' => 'si',
            'hindi' => 'hi',
            'urdu' => 'ur',
            'vietnamese' => 'vi',
        ];

        if (isset($aliases[$stored])) {
            return $aliases[$stored];
        }

        $available = config('app.available_locales', []);

        return in_array($stored, $available, true) ? $stored : null;
    }
}
