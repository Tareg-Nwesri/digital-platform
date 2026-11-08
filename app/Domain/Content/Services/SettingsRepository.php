<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Platform settings (LLD 08 §1): stored values win, config/platform.php
 * supplies the defaults. Read from a cache that every write clears.
 */
final class SettingsRepository
{
    private const CACHE_KEY = 'platform.settings';

    public function get(string $key): mixed
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $stored) ? $stored[$key] : $this->default($key);
    }

    public function set(string $key, mixed $value, ?User $by = null): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $by?->getKey()]);
        Cache::forget(self::CACHE_KEY);

        activity('settings')->causedBy($by)
            ->withProperties(['key' => $key, 'value' => $value])
            ->event('setting.updated')
            ->log('Setting updated');
    }

    public function default(string $key): mixed
    {
        return (config('platform.settings') ?? [])[$key] ?? null;
    }
}
