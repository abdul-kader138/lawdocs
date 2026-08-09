<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'label', 'description', 'is_public', 'is_system'];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'float'   => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($this->value, true),
            default   => $this->value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            return $setting ? $setting->getTypedValue() : $default;
        });
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        $attributes = ['value' => is_array($value) ? json_encode($value) : $value, 'group' => $group];

        // An array value only round-trips through getTypedValue() correctly as
        // 'json' — without this, a brand new setting falls back to the type
        // column's DB default ('string') and comes back out as a raw JSON string.
        if (is_array($value)) {
            $attributes['type'] = 'json';
        }

        static::updateOrCreate(['key' => $key], $attributes);
        Cache::forget("setting:{$key}");
    }

    public static function flushAll(): void
    {
        static::all()->each(fn ($s) => Cache::forget("setting:{$s->key}"));
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}
