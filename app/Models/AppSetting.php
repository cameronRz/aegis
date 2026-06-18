<?php

namespace App\Models;

use App\Enum\SettingKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class AppSetting extends Model
{
    public static function get(SettingKey $key, mixed $default = null): mixed
    {
        return Cache::remember("app_settings.{$key->value}", 300, function () use ($key, $default): mixed {
            $setting = static::where('key', $key->value)->first();

            return $setting !== null ? json_decode($setting->value, true) : $default;
        });
    }

    public static function set(SettingKey $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key->value], ['value' => json_encode($value)]);
        Cache::forget("app_settings.{$key->value}");
    }
}
