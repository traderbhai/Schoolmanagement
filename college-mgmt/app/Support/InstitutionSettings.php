<?php

namespace App\Support;

class InstitutionSettings
{
    public static function all(): array
    {
        $settings = self::defaults();
        $path = storage_path('app/settings.json');

        if (is_file($path)) {
            $stored = json_decode((string) file_get_contents($path), true);
            if (is_array($stored)) {
                $settings = array_merge($settings, array_filter($stored, fn ($value) => $value !== null));
            }
        }

        return $settings;
    }

    public static function name(): string
    {
        return trim((string) (self::all()['institute_name'] ?? '')) ?: config('app.name', 'Institute');
    }

    public static function addressLine(): string
    {
        $settings = self::all();

        return trim((string) ($settings['address'] ?? ''))
            ?: 'Institution address not configured';
    }

    public static function contactLine(): string
    {
        $settings = self::all();
        $parts = array_filter([
            trim((string) ($settings['phone'] ?? '')),
            trim((string) ($settings['email'] ?? '')),
            trim((string) ($settings['website'] ?? '')),
        ]);

        return implode(' | ', $parts);
    }

    public static function footerLine(): string
    {
        return trim(implode(' • ', array_filter([
            self::name(),
            self::addressLine(),
            self::contactLine(),
        ])));
    }

    public static function defaults(): array
    {
        return [
            'institute_name' => config('app.name', 'Institute'),
            'short_name' => config('app.name', 'Institute'),
            'address' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'primary_color' => '#4f46e5',
        ];
    }
}
