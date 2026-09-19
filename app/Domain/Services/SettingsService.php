<?php

namespace App\Domain\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingsService
{
    /**
     * Get a setting value by key, with an optional default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("system_settings.{$key}", function () use ($key, $default) {
            $setting = SystemSetting::where('key', $key)->first();
            return $setting ? $setting->parsed_value : $default;
        });
    }

    /**
     * Set a setting value and clear its cache.
     */
    public function set(string $key, mixed $value, string $type = 'string', string $description = null): SystemSetting
    {
        $existing = SystemSetting::where('key', $key)->first();
        
        $setting = SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'type' => $type,
                'description' => $description ?? ($existing ? $existing->description : null),
            ]
        );

        Cache::forget("system_settings.{$key}");

        return $setting;
    }

    /**
     * Retrieve all settings.
     */
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return SystemSetting::all();
    }
}
